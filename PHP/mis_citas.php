<?php
// 3.4.2 Mis citas + 3.6 CRUD de citas (rol Paciente)
// Próximas = Reservada/Confirmada con fecha futura; el resto va a Históricas.
require_once __DIR__ . '/auth.php';
requerir_rol([ROL_PACIENTE]);
$idp = (int) $_SESSION['id'];

// ---------- POST: cancelar una cita propia ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cancelar') {
    $idc = (int) ($_POST['id_cita'] ?? 0);
    // Regla: solo Reservada o Confirmada y con fecha que no haya pasado
    $st = $pdo->prepare(
        'UPDATE cita SET id_estado = 5
          WHERE id_cita = ? AND id_paciente = ?
            AND id_estado IN (1, 2) AND fecha_hora >= NOW()'
    );
    $st->execute([$idc, $idp]);
    if ($st->rowCount() > 0) {
        flash('success', 'La cita fue cancelada.');
    } else {
        flash('danger', 'No se pudo cancelar: solo se pueden cancelar citas Reservadas o Confirmadas cuya fecha no haya pasado.');
    }
    header('Location: mis_citas.php');
    exit;
}

$st = $pdo->prepare(
    'SELECT v.*, (SELECT a.id_atencion FROM atencion a WHERE a.id_cita = v.id_cita) AS id_atencion
       FROM vista_citas_detalle v
      WHERE v.id_paciente = ?
      ORDER BY v.fecha_hora'
);
$st->execute([$idp]);
$todas = $st->fetchAll(PDO::FETCH_ASSOC);

$ahora = date('Y-m-d H:i:s');
$proximas = [];
$historicas = [];
foreach ($todas as $c) {
    if (in_array($c['id_estado'], [1, 2]) && $c['fecha_hora'] >= $ahora) {
        $proximas[] = $c;
    } else {
        $historicas[] = $c;
    }
}
$historicas = array_reverse($historicas); // las más recientes primero

$titulo = 'Mis citas';
require __DIR__ . '/cabecera.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Mis citas</h2>
    <a href="agendar.php" class="btn btn-primary">Agendar nueva hora</a>
</div>

<h4>Próximas</h4>
<div class="table-responsive mb-4">
<table class="table table-striped bg-white align-middle">
    <thead><tr><th>Fecha y hora</th><th>Médico</th><th>Especialidad</th><th>Centro</th><th>Estado</th><th>Acciones</th></tr></thead>
    <tbody>
    <?php foreach ($proximas as $c): ?>
        <tr>
            <td><?= h(fecha_hora_legible($c['fecha_hora'])) ?></td>
            <td><?= h($c['nombre_medico']) ?></td>
            <td><?= h($c['nombre_especialidad']) ?></td>
            <td><?= h($c['nombre_centro']) ?></td>
            <td><?= badge_estado($c['nombre_estado']) ?></td>
            <td>
                <a class="btn btn-sm btn-outline-primary" href="reprogramar.php?id=<?= $c['id_cita'] ?>">Reprogramar</a>
                <form method="post" class="d-inline" onsubmit="return confirm('¿Cancelar esta cita?');">
                    <input type="hidden" name="accion" value="cancelar">
                    <input type="hidden" name="id_cita" value="<?= $c['id_cita'] ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit">Cancelar</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$proximas): ?>
        <tr><td colspan="6" class="text-center text-muted">No tienes citas próximas.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>

<h4>Históricas</h4>
<div class="table-responsive">
<table class="table table-striped bg-white align-middle">
    <thead><tr><th>Fecha y hora</th><th>Médico</th><th>Especialidad</th><th>Centro</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($historicas as $c): ?>
        <tr>
            <td><?= h(fecha_hora_legible($c['fecha_hora'])) ?></td>
            <td><?= h($c['nombre_medico']) ?></td>
            <td><?= h($c['nombre_especialidad']) ?></td>
            <td><?= h($c['nombre_centro']) ?></td>
            <td><?= badge_estado($c['nombre_estado']) ?></td>
            <td>
                <?php if ($c['id_atencion']): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="historial.php">Ver en mi historial</a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$historicas): ?>
        <tr><td colspan="6" class="text-center text-muted">Aún no tienes citas históricas.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/pie.php'; ?>