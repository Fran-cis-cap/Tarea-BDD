<?php
// 3.3 Perfil: cada usuario ve y modifica sus datos y cambia su contraseña.
// Solo el paciente puede eliminar su cuenta (se borran también sus citas,
// atenciones, diagnósticos y recetas por ON DELETE CASCADE).
require_once __DIR__ . '/auth.php';
requerir_rol([ROL_PACIENTE, ROL_MEDICO, ROL_ADMIN]);
$rol = $_SESSION['rol'];
$id  = (int) $_SESSION['id'];

// Tabla, clave y columna de e-mail de cada rol (valores fijos, no vienen del usuario)
$cfg = [
    ROL_PACIENTE => ['tabla' => 'paciente',      'pk' => 'id_paciente', 'email' => 'email'],
    ROL_MEDICO   => ['tabla' => 'medico',        'pk' => 'id_medico',   'email' => 'email_institucional'],
    ROL_ADMIN    => ['tabla' => 'administrador', 'pk' => 'id_admin',    'email' => 'email'],
][$rol];

$previsiones = $pdo->query('SELECT id_prevision, nombre_prevision FROM prevision ORDER BY nombre_prevision')->fetchAll();

// ---------- POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    try {
        if ($accion === 'guardar_datos') {
            $nombre = trim($_POST['nombre_completo'] ?? '');
            $email  = strtolower(trim($_POST['email'] ?? ''));
            if ($nombre === '' || $email === '') {
                throw new RuntimeException('El nombre y el e-mail son obligatorios.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100 || mb_strlen($nombre) > 255) {
                throw new RuntimeException('El e-mail no es válido o algún dato es demasiado largo.');
            }

            if ($rol === ROL_PACIENTE) {
                $fecha    = trim($_POST['fecha_nacimiento'] ?? '');
                $sexo     = trim($_POST['sexo'] ?? '');
                $telefono = trim($_POST['telefono_contacto'] ?? '');
                $comuna   = trim($_POST['comuna_residencia'] ?? '');
                $idprev   = (int) ($_POST['id_prevision'] ?? 0);

                if (!fecha_valida($fecha) || $fecha > date('Y-m-d') || $fecha < '1900-01-01') {
                    throw new RuntimeException('La fecha de nacimiento no es válida.');
                }
                if (!in_array($sexo, ['M', 'F', 'O'], true)) {
                    throw new RuntimeException('Seleccione un sexo válido.');
                }
                if ($telefono !== '' && !preg_match('/^\+?[0-9]{8,14}$/', $telefono)) {
                    throw new RuntimeException('El teléfono debe tener entre 8 y 14 dígitos (ejemplo: +56912345678).');
                }
                if ($comuna === '' || mb_strlen($comuna) > 50) {
                    throw new RuntimeException('La comuna es obligatoria (máximo 50 caracteres).');
                }
                if (!in_array($idprev, array_map('intval', array_column($previsiones, 'id_prevision')), true)) {
                    throw new RuntimeException('Seleccione una previsión válida.');
                }
                $pdo->prepare('UPDATE paciente SET nombre_completo = ?, email = ?, fecha_nacimiento = ?, sexo = ?,
                                      telefono_contacto = ?, comuna_residencia = ?, id_prevision = ? WHERE id_paciente = ?')
                    ->execute([$nombre, $email, $fecha, $sexo, $telefono !== '' ? $telefono : null, $comuna, $idprev, $id]);
            } else {
                $pdo->prepare("UPDATE {$cfg['tabla']} SET nombre_completo = ?, {$cfg['email']} = ? WHERE {$cfg['pk']} = ?")
                    ->execute([$nombre, $email, $id]);
            }
            $_SESSION['nombre_usuario'] = $nombre;
            flash('success', 'Tus datos fueron actualizados.');

        } elseif ($accion === 'cambiar_contrasena') {
            $actual = $_POST['contrasena_actual'] ?? '';
            $nueva  = $_POST['contrasena_nueva'] ?? '';
            $conf   = $_POST['contrasena_confirmar'] ?? '';

            $st = $pdo->prepare("SELECT contrasena FROM {$cfg['tabla']} WHERE {$cfg['pk']} = ?");
            $st->execute([$id]);
            $hash = $st->fetchColumn();

            if (!$hash || !password_verify($actual, $hash)) {
                throw new RuntimeException('La contraseña actual no es correcta.');
            }
            if (($msg = validar_contrasena($nueva)) !== null) {
                throw new RuntimeException($msg);
            }
            if ($nueva !== $conf) {
                throw new RuntimeException('Las contraseñas nuevas no coinciden.');
            }
            $pdo->prepare("UPDATE {$cfg['tabla']} SET contrasena = ? WHERE {$cfg['pk']} = ?")
                ->execute([password_hash($nueva, PASSWORD_DEFAULT), $id]);
            flash('success', 'Tu contraseña fue cambiada.');

        } elseif ($accion === 'eliminar_cuenta' && $rol === ROL_PACIENTE) {
            $st = $pdo->prepare('SELECT contrasena FROM paciente WHERE id_paciente = ?');
            $st->execute([$id]);
            $hash = $st->fetchColumn();

            if (!$hash || !password_verify($_POST['contrasena_confirmacion'] ?? '', $hash)) {
                throw new RuntimeException('Para eliminar tu cuenta debes escribir tu contraseña correcta.');
            }
            $pdo->prepare('DELETE FROM paciente WHERE id_paciente = ?')->execute([$id]);

            // Se cierra la sesión y se vuelve al login
            $_SESSION = [];
            session_destroy();
            header('Location: login.php?cuenta_eliminada=1');
            exit;
        }
    } catch (RuntimeException $e) {
        flash('danger', $e->getMessage());
    } catch (PDOException $e) {
        $num = (int) ($e->errorInfo[1] ?? 0);
        flash('danger', $num === 1062 ? 'Ese e-mail ya está registrado por otro usuario.' : msg_error($e));
    }
    header('Location: perfil.php');
    exit;
}

// ---------- GET: datos actuales ----------
$st = $pdo->prepare("SELECT * FROM {$cfg['tabla']} WHERE {$cfg['pk']} = ?");
$st->execute([$id]);
$u = $st->fetch();
if (!$u) {
    header('Location: logout.php');
    exit;
}

$especialidades_medico = '';
$centros_medico = '';
if ($rol === ROL_MEDICO) {
    $st = $pdo->prepare('SELECT GROUP_CONCAT(e.nombre_especialidad ORDER BY e.nombre_especialidad SEPARATOR ", ")
                           FROM medico_especialidad me JOIN especialidad e ON e.id_especialidad = me.id_especialidad
                          WHERE me.id_medico = ?');
    $st->execute([$id]);
    $especialidades_medico = (string) $st->fetchColumn();
    $st = $pdo->prepare('SELECT GROUP_CONCAT(cm.nombre_centro ORDER BY cm.nombre_centro SEPARATOR ", ")
                           FROM medico_centro mc JOIN centro_medico cm ON cm.id_centro = mc.id_centro
                          WHERE mc.id_medico = ?');
    $st->execute([$id]);
    $centros_medico = (string) $st->fetchColumn();
}

$titulo = 'Mi perfil';
require __DIR__ . '/cabecera.php';
?>
<h2 class="mb-3">Mi perfil</h2>

<form method="post" class="card card-body mb-4">
    <h5>Mis datos</h5>
    <input type="hidden" name="accion" value="guardar_datos">
    <div class="row g-3">
        <div class="col-md-3">
            <label class="form-label">RUT</label>
            <input type="text" class="form-control" value="<?= h($u['rut']) ?>" readonly>
        </div>
        <div class="col-md-5">
            <label class="form-label">Nombre completo</label>
            <input type="text" name="nombre_completo" class="form-control" maxlength="255" required value="<?= h($u['nombre_completo']) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">E-mail</label>
            <input type="email" name="email" class="form-control" maxlength="100" required value="<?= h($u[$cfg['email']]) ?>">
        </div>

        <?php if ($rol === ROL_PACIENTE): ?>
        <div class="col-md-3">
            <label class="form-label">Fecha de nacimiento</label>
            <input type="date" name="fecha_nacimiento" class="form-control" max="<?= date('Y-m-d') ?>" required value="<?= h($u['fecha_nacimiento']) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Sexo</label>
            <select name="sexo" class="form-select">
                <option value="M" <?= $u['sexo'] === 'M' ? 'selected' : '' ?>>Masculino</option>
                <option value="F" <?= $u['sexo'] === 'F' ? 'selected' : '' ?>>Femenino</option>
                <option value="O" <?= $u['sexo'] === 'O' ? 'selected' : '' ?>>Otro</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Teléfono (opcional)</label>
            <input type="text" name="telefono_contacto" class="form-control" value="<?= h($u['telefono_contacto'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Comuna de residencia</label>
            <input type="text" name="comuna_residencia" class="form-control" maxlength="50" required value="<?= h($u['comuna_residencia']) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Previsión</label>
            <select name="id_prevision" class="form-select">
                <?php foreach ($previsiones as $p): ?>
                    <option value="<?= $p['id_prevision'] ?>" <?= $u['id_prevision'] == $p['id_prevision'] ? 'selected' : '' ?>><?= h($p['nombre_prevision']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <?php if ($rol === ROL_MEDICO): ?>
        <div class="col-md-6">
            <label class="form-label">Especialidades</label>
            <input type="text" class="form-control" value="<?= h($especialidades_medico) ?>" readonly>
        </div>
        <div class="col-md-6">
            <label class="form-label">Centros donde atiende</label>
            <input type="text" class="form-control" value="<?= h($centros_medico) ?>" readonly>
        </div>
        <div class="col-12"><div class="form-text">Las especialidades y los centros los gestiona el administrador.</div></div>
        <?php endif; ?>
    </div>
    <div class="mt-3"><button class="btn btn-primary" type="submit">Guardar cambios</button></div>
</form>

<form method="post" class="card card-body mb-4">
    <h5>Cambiar contraseña</h5>
    <input type="hidden" name="accion" value="cambiar_contrasena">
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Contraseña actual</label>
            <input type="password" name="contrasena_actual" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Nueva contraseña</label>
            <input type="password" name="contrasena_nueva" class="form-control" required>
            <div class="form-text">Mínimo 6 caracteres y 2 números.</div>
        </div>
        <div class="col-md-4">
            <label class="form-label">Confirmar nueva contraseña</label>
            <input type="password" name="contrasena_confirmar" class="form-control" required>
        </div>
    </div>
    <div class="mt-3"><button class="btn btn-outline-primary" type="submit">Cambiar contraseña</button></div>
</form>

<?php if ($rol === ROL_PACIENTE): ?>
<form method="post" class="card card-body border-danger"
      onsubmit="return confirm('Se eliminarán tu cuenta, tus citas y tu historial. Esta acción no se puede deshacer. ¿Continuar?');">
    <h5 class="text-danger">Eliminar mi cuenta</h5>
    <p class="text-muted mb-3">Se eliminarán también todas tus citas y tu historial de atenciones y recetas.</p>
    <input type="hidden" name="accion" value="eliminar_cuenta">
    <div class="row g-3 align-items-end">
        <div class="col-md-5">
            <label class="form-label">Escribe tu contraseña para confirmar</label>
            <input type="password" name="contrasena_confirmacion" class="form-control" required>
        </div>
        <div class="col-md-3"><button class="btn btn-danger" type="submit">Eliminar cuenta</button></div>
    </div>
</form>
<?php endif; ?>

<?php require __DIR__ . '/pie.php'; ?>