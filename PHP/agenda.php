<?php
// 3.4.3 Agenda (rol Médico) + cambio de estado de citas (3.6)
// Supuestos (README):
//  - Confirmar: solo citas Reservadas que aún no han pasado.
//  - Atendida / No Asistió: solo citas Reservadas o Confirmadas del día de hoy o anteriores.
require_once __DIR__ . '/auth.php';
requerir_rol([ROL_MEDICO]);
$idm = (int) $_SESSION['id'];

$fecha = $_GET['fecha'] ?? date('Y-m-d');
if (!fecha_valida($fecha)) {
    $fecha = date('Y-m-d');
}

// ---------- POST: cambiar estado ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idc   = (int) ($_POST['id_cita'] ?? 0);
    $nuevo = (int) ($_POST['nuevo_estado'] ?? 0);
    $volver = 'agenda.php?fecha=' . urlencode($_POST['fecha'] ?? $fecha);

    $st = $pdo->prepare('SELECT id_estado, fecha_hora FROM cita WHERE id_cita = ? AND id_medico = ?');
    $st->execute([$idc, $idm]);
    $c = $st->fetch(PDO::FETCH_ASSOC);

    if (!$c) {
        flash('danger', 'La cita no existe o no pertenece a este médico.');
    } elseif (!in_array($nuevo, [2, 3, 4])) {
        flash('danger', 'Estado no permitido.');
    } elseif ($nuevo === 2 && ($c['id_estado'] != 1 || $c['fecha_hora'] < date('Y-m-d H:i:s'))) {
        flash('danger', 'Solo se puede confirmar una cita Reservada que aún no ha pasado.');
    } elseif (in_array($nuevo, [3, 4]) && (!in_array($c['id_estado'], [1, 2]) || substr($c['fecha_hora'], 0, 10) > date('Y-m-d'))) {
        flash('danger', 'Solo se puede marcar como Atendida o No Asistió una cita Reservada o Confirmada del día de hoy o anterior.');
    } else {
        try {
            $up = $pdo->prepare('UPDATE cita SET id_estado = ? WHERE id_cita = ? AND id_medico = ?');
            $up->execute([$nuevo, $idc, $idm]);
            flash('success', 'Estado de la cita actualizado.');
        } catch (PDOException $e) {
            flash('danger', msg_error($e));
        }
    }
    header('Location: ' . $volver);
    exit;
}

$st = $pdo->prepare(
    'SELECT v.*, (SELECT a.id_atencion FROM atencion a WHERE a.id_cita = v.id_cita) AS id_atencion
       FROM vista_citas_detalle v
      WHERE v.id_medico = ? AND DATE(v.fecha_hora) = ?
      ORDER BY v.fecha_hora'
);
$st->execute([$idm, $fecha]);
$citas = $st->fetchAll(PDO::FETCH_ASSOC);

$titulo = 'Mi agenda';
require __DIR__ . '/cabecera.php';
?>
<h2 class="mb-3">Mi agenda</h2>

<form method="get" action="agenda.php" class="card card-body mb-4">
    <div class="row g-2 align-items-end">
        <div class="col-auto">
            <label class="form-label">Día</label>
            <input type="date" name="fecha" class="form-control" value="<?= h($fecha) ?>" onchange="this.form.submit()">
        </div>
        <div class="col-auto">
            <a class="btn btn-outline-secondary" href="agenda.php">Hoy</a>
        </div>
    </div>
</form>

<div class="table-responsive">
<table class="table table-striped bg-white align-middle">
    <thead><tr><th>Hora</th><th>Paciente</th><th>Previsión</th><th>Centro</th><th>Estado</th><th>Acciones</th></tr></thead>
    <tbody>
    <?php foreach ($citas as $c): ?>
        <tr>
            <td><?= h(date('H:i', strtotime($c['fecha_hora']))) ?></td>
            <td><?= h($c['nombre_paciente']) ?></td>
            <td><?= h($c['nombre_prevision']) ?></td>
            <td><?= h($c['nombre_centro']) ?></td>
            <td><?= badge_estado($c['nombre_estado']) ?></td>
            <td>
                <?php foreach ([2 => ['Confirmar', 'info'], 3 => ['Atendida', 'success'], 4 => ['No asistió', 'danger']] as $num => [$texto, $color]): ?>
                    <?php if ($c['id_estado'] != $num && in_array($c['id_estado'], [1, 2])): ?>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="id_cita" value="<?= $c['id_cita'] ?>">
                            <input type="hidden" name="nuevo_estado" value="<?= $num ?>">
                            <input type="hidden" name="fecha" value="<?= h($fecha) ?>">
                            <button class="btn btn-sm btn-outline-<?= $color ?>" type="submit"><?= $texto ?></button>
                        </form>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($c['id_estado'] == 3): ?>
                    <a class="btn btn-sm btn-primary" href="atencion.php?id_cita=<?= $c['id_cita'] ?>">
                        <?= $c['id_atencion'] ? 'Ver / editar atención' : 'Registrar atención' ?></a>
                <?php endif; ?>
                <a class="btn btn-sm btn-outline-secondary" href="historial.php?id_paciente=<?= $c['id_paciente'] ?>">Historial</a>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$citas): ?>
        <tr><td colspan="6" class="text-center text-muted">No tienes citas el <?= h(date('d-m-Y', strtotime($fecha))) ?>.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/pie.php'; ?>