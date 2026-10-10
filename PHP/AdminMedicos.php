<?php
// 3.8 CRUD de médicos (rol Administrador)
// - Especialidades: mínimo 1 y máximo 3 (el máximo también lo valida un trigger).
// - Centros: al menos 1.
// - Quitar una especialidad o centro con citas futuras lo impide un trigger; como todo
//   se hace en una transacción, si algo falla no se guarda ningún cambio.
// - No se puede eliminar un médico con citas (clave foránea con RESTRICT).
require_once __DIR__ . '/auth.php';
requerir_rol([ROL_ADMIN]);

$especialidades = $pdo->query('SELECT id_especialidad, nombre_especialidad FROM especialidad ORDER BY nombre_especialidad')->fetchAll();
$centros = $pdo->query('SELECT id_centro, nombre_centro, comuna FROM centro_medico ORDER BY nombre_centro')->fetchAll();
$ids_esp_validos = array_map('intval', array_column($especialidades, 'id_especialidad'));
$ids_cen_validos = array_map('intval', array_column($centros, 'id_centro'));

// Deja en la tabla de relación exactamente los ids indicados:
// primero quita los que sobran (el trigger puede impedirlo) y luego agrega los nuevos.
function sincronizar_relacion(PDO $pdo, string $tabla, string $columna, int $id_medico, array $nuevos): void
{
    $st = $pdo->prepare("SELECT $columna FROM $tabla WHERE id_medico = ?");
    $st->execute([$id_medico]);
    $actuales = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));

    foreach (array_diff($actuales, $nuevos) as $quitar) {
        $pdo->prepare("DELETE FROM $tabla WHERE id_medico = ? AND $columna = ?")->execute([$id_medico, $quitar]);
    }
    foreach (array_diff($nuevos, $actuales) as $agregar) {
        $pdo->prepare("INSERT INTO $tabla (id_medico, $columna) VALUES (?, ?)")->execute([$id_medico, $agregar]);
    }
}

// ---------- POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $idm = (int) ($_POST['id_medico'] ?? 0);
    $volver = 'admin_medicos.php' . ($accion === 'editar' && $idm ? '?editar=' . $idm : '');

    try {
        if ($accion === 'eliminar') {
            $pdo->prepare('DELETE FROM medico WHERE id_medico = ?')->execute([$idm]);
            flash('success', 'Médico eliminado.');
            $volver = 'admin_medicos.php';

        } elseif (in_array($accion, ['crear', 'editar'])) {
            $rut    = strtoupper(trim($_POST['rut'] ?? ''));
            $nombre = trim($_POST['nombre_completo'] ?? '');
            $email  = strtolower(trim($_POST['email_institucional'] ?? ''));
            $clave  = $_POST['contrasena'] ?? '';
            $esp    = array_values(array_unique(array_map('intval', (array) ($_POST['especialidades'] ?? []))));
            $cen    = array_values(array_unique(array_map('intval', (array) ($_POST['centros'] ?? []))));

            if ($nombre === '' || $email === '' || ($accion === 'crear' && ($rut === '' || $clave === ''))) {
                throw new RuntimeException('Completa todos los campos obligatorios.');
            }
            if ($accion === 'crear' && !rut_formato_valido($rut)) {
                throw new RuntimeException('El RUT debe tener el formato 12345678-9 (sin puntos y con guion).');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('El e-mail no es válido.');
            }
            if ($clave !== '' && ($msg = validar_contrasena($clave)) !== null) {
                throw new RuntimeException($msg);
            }
            if (count($esp) < 1 || count($esp) > 3) {
                throw new RuntimeException('El médico debe tener entre 1 y 3 especialidades.');
            }
            if (count($cen) < 1) {
                throw new RuntimeException('El médico debe atender en al menos 1 centro.');
            }
            if (array_diff($esp, $ids_esp_validos) || array_diff($cen, $ids_cen_validos)) {
                throw new RuntimeException('Especialidad o centro no válido.');
            }

            $pdo->beginTransaction();
            if ($accion === 'crear') {
                $pdo->prepare('INSERT INTO medico (rut, nombre_completo, email_institucional, contrasena) VALUES (?, ?, ?, ?)')
                    ->execute([$rut, $nombre, $email, password_hash($clave, PASSWORD_DEFAULT)]);
                $idm = (int) $pdo->lastInsertId();
            } else {
                $pdo->prepare('UPDATE medico SET nombre_completo = ?, email_institucional = ? WHERE id_medico = ?')
                    ->execute([$nombre, $email, $idm]);
                if ($clave !== '') {
                    $pdo->prepare('UPDATE medico SET contrasena = ? WHERE id_medico = ?')
                        ->execute([password_hash($clave, PASSWORD_DEFAULT), $idm]);
                }
            }
            sincronizar_relacion($pdo, 'medico_especialidad', 'id_especialidad', $idm, $esp);
            sincronizar_relacion($pdo, 'medico_centro', 'id_centro', $idm, $cen);
            $pdo->commit();

            flash('success', $accion === 'crear' ? 'Médico creado.' : 'Médico actualizado.');
            $volver = 'admin_medicos.php';
        }
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        flash('danger', $e->getMessage());
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $num = (int) ($e->errorInfo[1] ?? 0);
        if ($num === 1062) {
            flash('danger', 'Ya existe un médico con ese RUT o ese e-mail.');
        } elseif ($num === 1451) {
            flash('danger', 'No se puede eliminar el médico porque tiene citas registradas.');
        } else {
            flash('danger', msg_error($e));
        }
    }
    header('Location: ' . $volver);
    exit;
}

$editar = null;
$esp_sel = [];
$cen_sel = [];
$editar_id = (int) ($_GET['editar'] ?? 0);
if ($editar_id) {
    $st = $pdo->prepare('SELECT * FROM medico WHERE id_medico = ?');
    $st->execute([$editar_id]);
    $editar = $st->fetch() ?: null;
    if ($editar) {
        $st = $pdo->prepare('SELECT id_especialidad FROM medico_especialidad WHERE id_medico = ?');
        $st->execute([$editar_id]);
        $esp_sel = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        $st = $pdo->prepare('SELECT id_centro FROM medico_centro WHERE id_medico = ?');
        $st->execute([$editar_id]);
        $cen_sel = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
}

$medicos = $pdo->query(
    "SELECT m.id_medico, m.rut, m.nombre_completo, m.email_institucional,
            (SELECT GROUP_CONCAT(e.nombre_especialidad ORDER BY e.nombre_especialidad SEPARATOR ', ')
               FROM medico_especialidad me JOIN especialidad e ON e.id_especialidad = me.id_especialidad
              WHERE me.id_medico = m.id_medico) AS especialidades,
            (SELECT GROUP_CONCAT(cm.nombre_centro ORDER BY cm.nombre_centro SEPARATOR ', ')
               FROM medico_centro mc JOIN centro_medico cm ON cm.id_centro = mc.id_centro
              WHERE mc.id_medico = m.id_medico) AS centros,
            (SELECT COUNT(*) FROM cita c WHERE c.id_medico = m.id_medico) AS citas
       FROM medico m ORDER BY m.nombre_completo"
)->fetchAll();

$titulo = 'Médicos';
require __DIR__ . '/cabecera.php';
?>
<h2 class="mb-3">Médicos</h2>

<form method="post" class="card card-body mb-4">
    <h5><?= $editar ? 'Editar médico' : 'Nuevo médico' ?></h5>
    <input type="hidden" name="accion" value="<?= $editar ? 'editar' : 'crear' ?>">
    <?php if ($editar): ?><input type="hidden" name="id_medico" value="<?= $editar['id_medico'] ?>"><?php endif; ?>
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <label class="form-label">RUT</label>
            <input type="text" name="rut" class="form-control" placeholder="12345678-9" maxlength="10"
                   <?= $editar ? 'readonly' : 'required' ?> value="<?= h($editar['rut'] ?? '') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Nombre completo</label>
            <input type="text" name="nombre_completo" class="form-control" maxlength="255" required value="<?= h($editar['nombre_completo'] ?? '') ?>">
        </div>
        <div class="col-md-5">
            <label class="form-label">E-mail institucional</label>
            <input type="email" name="email_institucional" class="form-control" maxlength="100" required value="<?= h($editar['email_institucional'] ?? '') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label"><?= $editar ? 'Nueva contraseña (dejar vacío para no cambiarla)' : 'Contraseña inicial' ?></label>
            <input type="password" name="contrasena" class="form-control" <?= $editar ? '' : 'required' ?>>
            <div class="form-text">Mínimo 6 caracteres y 2 números.</div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Especialidades (entre 1 y 3)</label>
            <?php foreach ($especialidades as $e): ?>
                <div class="form-check">
                    <input class="form-check-input chk-esp" type="checkbox" name="especialidades[]"
                           id="esp<?= $e['id_especialidad'] ?>" value="<?= $e['id_especialidad'] ?>"
                           <?= in_array((int) $e['id_especialidad'], $esp_sel, true) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="esp<?= $e['id_especialidad'] ?>"><?= h($e['nombre_especialidad']) ?></label>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="col-md-6">
            <label class="form-label">Centros donde atiende (al menos 1)</label>
            <?php foreach ($centros as $c): ?>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="centros[]"
                           id="cen<?= $c['id_centro'] ?>" value="<?= $c['id_centro'] ?>"
                           <?= in_array((int) $c['id_centro'], $cen_sel, true) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="cen<?= $c['id_centro'] ?>"><?= h($c['nombre_centro']) ?> (<?= h($c['comuna']) ?>)</label>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="mt-3">
        <button class="btn btn-primary" type="submit"><?= $editar ? 'Guardar cambios' : 'Crear médico' ?></button>
        <?php if ($editar): ?><a href="admin_medicos.php" class="btn btn-outline-secondary">Cancelar</a><?php endif; ?>
    </div>
</form>

<div class="table-responsive">
<table class="table table-striped bg-white align-middle">
    <thead><tr><th>RUT</th><th>Nombre</th><th>E-mail</th><th>Especialidades</th><th>Centros</th><th class="text-end">Citas</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($medicos as $m): ?>
        <tr>
            <td><?= h($m['rut']) ?></td>
            <td><?= h($m['nombre_completo']) ?></td>
            <td><?= h($m['email_institucional']) ?></td>
            <td><?= h($m['especialidades']) ?></td>
            <td><?= h($m['centros']) ?></td>
            <td class="text-end"><?= (int) $m['citas'] ?></td>
            <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-primary" href="admin_medicos.php?editar=<?= $m['id_medico'] ?>">Editar</a>
                <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar a este médico?');">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id_medico" value="<?= $m['id_medico'] ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$medicos): ?>
        <tr><td colspan="7" class="text-center text-muted">No hay médicos registrados.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>

<script>
// Máximo 3 especialidades por médico
document.querySelectorAll('.chk-esp').forEach(function (c) {
    c.addEventListener('change', function () {
        if (document.querySelectorAll('.chk-esp:checked').length > 3) {
            c.checked = false;
            alert('Un médico puede tener como máximo 3 especialidades.');
        }
    });
});
</script>

<?php require __DIR__ . '/pie.php'; ?>