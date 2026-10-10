<?php
// 3.7 CRUD Atención, Diagnósticos y Recetas (rol Médico)
// Reglas: solo existe una atención si la cita está Atendida (lo valida el trigger
// trg_atencion_before_insert) y la cita debe pertenecer al médico que la registra.
require_once __DIR__ . '/auth.php';
requerir_rol([ROL_MEDICO]);
$idm = (int) $_SESSION['id'];
$idc = (int) ($_GET['id_cita'] ?? $_POST['id_cita'] ?? 0);

// Datos de la ficha (la cita debe ser de este médico)
$st = $pdo->prepare(
    'SELECT v.*, p.fecha_nacimiento, p.sexo, m.email_institucional
       FROM vista_citas_detalle v
       JOIN paciente p ON p.id_paciente = v.id_paciente
       JOIN medico m ON m.id_medico = v.id_medico
      WHERE v.id_cita = ? AND v.id_medico = ?'
);
$st->execute([$idc, $idm]);
$cita = $st->fetch(PDO::FETCH_ASSOC);
if (!$cita) {
    flash('danger', 'La cita no existe o no pertenece a este médico.');
    header('Location: agenda.php');
    exit;
}

$st = $pdo->prepare('SELECT * FROM atencion WHERE id_cita = ?');
$st->execute([$idc]);
$atencion = $st->fetch(PDO::FETCH_ASSOC);

// Busca un diagnóstico por código CIE-10 o lo crea si no existe
function diagnostico_id(PDO $pdo, string $codigo, string $descripcion): int
{
    $st = $pdo->prepare('SELECT id_diagnostico FROM diagnostico WHERE codigo_cie10 = ?');
    $st->execute([$codigo]);
    $id = $st->fetchColumn();
    if (!$id) {
        $pdo->prepare('INSERT INTO diagnostico (codigo_cie10, descripcion) VALUES (?, ?)')
            ->execute([$codigo, $descripcion]);
        $id = $pdo->lastInsertId();
    }
    return (int) $id;
}

// ---------- POST: todas las acciones ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    try {
        // ATENCIÓN
        if ($accion === 'guardar_atencion') {
            $motivo = trim($_POST['motivo_consulta'] ?? '');
            $obs    = trim($_POST['observaciones_clinicas'] ?? '');
            if ($motivo === '' || $obs === '') {
                throw new RuntimeException('El motivo de consulta y las observaciones son obligatorios.');
            }
            if ($atencion) {
                $pdo->prepare('UPDATE atencion SET motivo_consulta = ?, observaciones_clinicas = ? WHERE id_atencion = ?')
                    ->execute([$motivo, $obs, $atencion['id_atencion']]);
                flash('success', 'Atención actualizada.');
            } else {
                $pdo->prepare('INSERT INTO atencion (fecha_atencion, motivo_consulta, observaciones_clinicas, id_cita) VALUES (NOW(), ?, ?, ?)')
                    ->execute([$motivo, $obs, $idc]);
                flash('success', 'Atención registrada.');
            }
        } elseif ($accion === 'eliminar_atencion' && $atencion) {
            // ON DELETE CASCADE elimina también sus diagnósticos y recetas
            $pdo->prepare('DELETE FROM atencion WHERE id_atencion = ?')->execute([$atencion['id_atencion']]);
            flash('success', 'Atención eliminada junto con sus diagnósticos y recetas.');

        // DIAGNÓSTICOS y RECETAS: requieren que la atención exista
        } elseif (!$atencion) {
            throw new RuntimeException('Primero debes registrar la atención.');
        } else {
            $ida = (int) $atencion['id_atencion'];

            if (in_array($accion, ['add_diag', 'edit_diag'])) {
                $codigo = strtoupper(trim($_POST['codigo_cie10'] ?? ''));
                $desc   = trim($_POST['descripcion'] ?? '');
                if (!preg_match('/^[A-Z][0-9]{2}(\.[0-9A-Z]{1,4})?$/', $codigo)) {
                    throw new RuntimeException('El código CIE-10 no es válido (ejemplos: J00, I10, E11.9).');
                }
                if ($desc === '') {
                    throw new RuntimeException('La descripción del diagnóstico es obligatoria.');
                }
                $iddiag = diagnostico_id($pdo, $codigo, $desc);

                if ($accion === 'add_diag') {
                    $pdo->prepare('INSERT INTO atencion_diagnostico (id_atencion, id_diagnostico) VALUES (?, ?)')
                        ->execute([ $ida, $iddiag ]);
                    flash('success', 'Diagnóstico agregado.');
                } else {
                    $pdo->prepare('UPDATE atencion_diagnostico SET id_diagnostico = ? WHERE id_atencion = ? AND id_diagnostico = ?')
                        ->execute([$iddiag, $ida, (int) ($_POST['id_diagnostico_actual'] ?? 0)]);
                    flash('success', 'Diagnóstico actualizado.');
                }
            } elseif ($accion === 'del_diag') {
                $pdo->prepare('DELETE FROM atencion_diagnostico WHERE id_atencion = ? AND id_diagnostico = ?')
                    ->execute([$ida, (int) ($_POST['id_diagnostico_actual'] ?? 0)]);
                flash('success', 'Diagnóstico quitado de la atención.');

            } elseif (in_array($accion, ['add_receta', 'edit_receta'])) {
                $med   = trim($_POST['medicamento'] ?? '');
                $dosis = trim($_POST['dosis'] ?? '');
                $dias  = (int) ($_POST['dias_tratamiento'] ?? 0);
                if ($med === '' || $dosis === '' || $dias < 1) {
                    throw new RuntimeException('Medicamento, dosis y días de tratamiento (mayor a 0) son obligatorios.');
                }
                if ($accion === 'add_receta') {
                    $pdo->prepare('INSERT INTO receta (id_atencion, medicamento, dosis, dias_tratamiento) VALUES (?, ?, ?, ?)')
                        ->execute([$ida, $med, $dosis, $dias]);
                    flash('success', 'Receta agregada.');
                } else {
                    $pdo->prepare('UPDATE receta SET medicamento = ?, dosis = ?, dias_tratamiento = ? WHERE id_receta = ? AND id_atencion = ?')
                        ->execute([$med, $dosis, $dias, (int) ($_POST['id_receta'] ?? 0), $ida]);
                    flash('success', 'Receta actualizada.');
                }
            } elseif ($accion === 'del_receta') {
                $pdo->prepare('DELETE FROM receta WHERE id_receta = ? AND id_atencion = ?')
                    ->execute([(int) ($_POST['id_receta'] ?? 0), $ida]);
                flash('success', 'Receta eliminada.');
            }
        }
    } catch (RuntimeException $e) {
        flash('danger', $e->getMessage());
    } catch (PDOException $e) {
        flash('danger', msg_error($e));
    }
    header('Location: atencion.php?id_cita=' . $idc);
    exit;
}

// Diagnósticos y recetas actuales
$diagnosticos = [];
$recetas = [];
if ($atencion) {
    $st = $pdo->prepare(
        'SELECT d.id_diagnostico, d.codigo_cie10, d.descripcion
           FROM atencion_diagnostico ad
           JOIN diagnostico d ON d.id_diagnostico = ad.id_diagnostico
          WHERE ad.id_atencion = ? ORDER BY d.codigo_cie10'
    );
    $st->execute([$atencion['id_atencion']]);
    $diagnosticos = $st->fetchAll(PDO::FETCH_ASSOC);

    $st = $pdo->prepare('SELECT * FROM receta WHERE id_atencion = ? ORDER BY id_receta');
    $st->execute([$atencion['id_atencion']]);
    $recetas = $st->fetchAll(PDO::FETCH_ASSOC);
}

$titulo = 'Ficha de atención';
require __DIR__ . '/cabecera.php';
?>
<h2 class="mb-3">Ficha de atención</h2>

<div class="card card-body mb-4">
    <div class="row">
        <div class="col-md-6">
            <p class="mb-1"><strong>Nº de cita:</strong> <?= $cita['id_cita'] ?>
                <?php if ($atencion): ?> &nbsp; <strong>Nº de atención:</strong> <?= $atencion['id_atencion'] ?><?php endif; ?></p>
            <p class="mb-1"><strong>Fecha:</strong> <?= h(fecha_hora_legible($cita['fecha_hora'])) ?></p>
            <p class="mb-1"><strong>Centro:</strong> <?= h($cita['nombre_centro']) ?></p>
            <p class="mb-1"><strong>Estado de la cita:</strong> <?= badge_estado($cita['nombre_estado']) ?></p>
        </div>
        <div class="col-md-6">
            <p class="mb-1"><strong>Paciente:</strong> <?= h($cita['nombre_paciente']) ?> (<?= h($cita['rut_paciente']) ?>)</p>
            <p class="mb-1"><strong>Nacimiento:</strong> <?= h(date('d-m-Y', strtotime($cita['fecha_nacimiento']))) ?> &nbsp; <strong>Sexo:</strong> <?= h($cita['sexo']) ?></p>
            <p class="mb-1"><strong>Médico:</strong> <?= h($cita['nombre_medico']) ?> (<?= h($cita['email_institucional']) ?>)</p>
            <p class="mb-1"><strong>Especialidad:</strong> <?= h($cita['nombre_especialidad']) ?></p>
        </div>
    </div>
</div>

<?php if ($cita['id_estado'] != 3 && !$atencion): ?>
    <div class="alert alert-warning">
        Solo se puede registrar una atención si la cita está en estado <strong>Atendida</strong>.
        Cambia el estado desde tu agenda.
    </div>
<?php else: ?>

<!-- ATENCIÓN -->
<form method="post" class="card card-body mb-2">
    <input type="hidden" name="id_cita" value="<?= $idc ?>">
    <input type="hidden" name="accion" value="guardar_atencion">
    <h5>Atención</h5>
    <div class="mb-3">
        <label class="form-label">Motivo de consulta</label>
        <input type="text" name="motivo_consulta" maxlength="255" class="form-control" required
               value="<?= h($atencion['motivo_consulta'] ?? '') ?>">
    </div>
    <div class="mb-3">
        <label class="form-label">Observaciones clínicas</label>
        <textarea name="observaciones_clinicas" rows="3" class="form-control" required><?= h($atencion['observaciones_clinicas'] ?? '') ?></textarea>
    </div>
    <div>
        <button class="btn btn-primary" type="submit"><?= $atencion ? 'Guardar cambios' : 'Registrar atención' ?></button>
    </div>
</form>
<?php if ($atencion): ?>
<form method="post" class="mb-4" onsubmit="return confirm('¿Eliminar la atención con sus diagnósticos y recetas?');">
    <input type="hidden" name="id_cita" value="<?= $idc ?>">
    <input type="hidden" name="accion" value="eliminar_atencion">
    <button class="btn btn-outline-danger" type="submit">Eliminar atención</button>
</form>
<?php endif; ?>

<?php if ($atencion): ?>

<!-- DIAGNÓSTICOS -->
<div class="card card-body mb-4">
    <h5>Diagnósticos (CIE-10)</h5>
    <?php foreach ($diagnosticos as $d): ?>
        <form method="post" class="row g-2 mb-2 align-items-center">
            <input type="hidden" name="id_cita" value="<?= $idc ?>">
            <input type="hidden" name="id_diagnostico_actual" value="<?= $d['id_diagnostico'] ?>">
            <div class="col-md-2"><input type="text" name="codigo_cie10" class="form-control form-control-sm" maxlength="10" required value="<?= h($d['codigo_cie10']) ?>"></div>
            <div class="col-md-6"><input type="text" name="descripcion" class="form-control form-control-sm" maxlength="255" required value="<?= h($d['descripcion']) ?>"></div>
            <div class="col-md-4">
                <button class="btn btn-sm btn-outline-primary" name="accion" value="edit_diag" type="submit">Guardar</button>
                <button class="btn btn-sm btn-outline-danger" name="accion" value="del_diag" type="submit" formnovalidate>Quitar</button>
            </div>
        </form>
    <?php endforeach; ?>
    <?php if (!$diagnosticos): ?>
        <p class="text-muted">Sin diagnósticos todavía.</p>
    <?php endif; ?>
    <hr>
    <form method="post" class="row g-2">
        <input type="hidden" name="id_cita" value="<?= $idc ?>">
        <input type="hidden" name="accion" value="add_diag">
        <div class="col-md-2"><input type="text" name="codigo_cie10" class="form-control" maxlength="10" placeholder="Código (J00)" required></div>
        <div class="col-md-7"><input type="text" name="descripcion" class="form-control" maxlength="255" placeholder="Descripción" required></div>
        <div class="col-md-3"><button class="btn btn-success w-100" type="submit">Agregar diagnóstico</button></div>
    </form>
</div>

<!-- RECETAS -->
<div class="card card-body mb-4">
    <h5>Receta</h5>
    <?php foreach ($recetas as $r): ?>
        <form method="post" class="row g-2 mb-2 align-items-center">
            <input type="hidden" name="id_cita" value="<?= $idc ?>">
            <input type="hidden" name="id_receta" value="<?= $r['id_receta'] ?>">
            <div class="col-md-4"><input type="text" name="medicamento" class="form-control form-control-sm" maxlength="100" required value="<?= h($r['medicamento']) ?>"></div>
            <div class="col-md-3"><input type="text" name="dosis" class="form-control form-control-sm" maxlength="50" required value="<?= h($r['dosis']) ?>"></div>
            <div class="col-md-2"><input type="number" name="dias_tratamiento" class="form-control form-control-sm" min="1" required value="<?= h($r['dias_tratamiento']) ?>"></div>
            <div class="col-md-3">
                <button class="btn btn-sm btn-outline-primary" name="accion" value="edit_receta" type="submit">Guardar</button>
                <button class="btn btn-sm btn-outline-danger" name="accion" value="del_receta" type="submit" formnovalidate>Eliminar</button>
            </div>
        </form>
    <?php endforeach; ?>
    <?php if (!$recetas): ?>
        <p class="text-muted">Sin recetas todavía.</p>
    <?php endif; ?>
    <hr>
    <form method="post" class="row g-2">
        <input type="hidden" name="id_cita" value="<?= $idc ?>">
        <input type="hidden" name="accion" value="add_receta">
        <div class="col-md-4"><input type="text" name="medicamento" class="form-control" maxlength="100" placeholder="Medicamento" required></div>
        <div class="col-md-3"><input type="text" name="dosis" class="form-control" maxlength="50" placeholder="Dosis" required></div>
        <div class="col-md-2"><input type="number" name="dias_tratamiento" class="form-control" min="1" placeholder="Días" required></div>
        <div class="col-md-3"><button class="btn btn-success w-100" type="submit">Agregar línea</button></div>
    </form>
</div>

<?php endif; ?>
<?php endif; ?>

<a href="agenda.php?fecha=<?= h(substr($cita['fecha_hora'], 0, 10)) ?>" class="btn btn-secondary">Volver a la agenda</a>
<a href="historial.php?id_paciente=<?= $cita['id_paciente'] ?>" class="btn btn-outline-secondary">Historial del paciente</a>

<?php require __DIR__ . '/pie.php'; ?>