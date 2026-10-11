<?php
// Página principal del médico: resumen de las citas de hoy
require_once __DIR__ . '/auth.php';
requerir_rol([ROL_MEDICO]);
$idm = (int) $_SESSION['id'];

$st = $pdo->prepare(
    'SELECT * FROM vista_citas_detalle
      WHERE id_medico = ? AND DATE(fecha_hora) = CURDATE()
      ORDER BY fecha_hora'
);
$st->execute([$idm]);
$hoy = $st->fetchAll();

$pendientes = 0;
foreach ($hoy as $c) {
    if (in_array($c['id_estado'], [1, 2])) {
        $pendientes++;
    }
}

$titulo = 'Inicio';
require __DIR__ . '/cabecera.php';
?>
<h2 class="mb-1">Hola, <?= h($_SESSION['nombre_usuario'] ?? '') ?></h2>
<p class="text-muted mb-4">Hoy es <?= date('d-m-Y') ?>.</p>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card h-100 text-center"><div class="card-body">
            <div class="display-6"><?= count($hoy) ?></div>
            <div class="text-muted">citas hoy</div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 text-center"><div class="card-body">
            <div class="display-6"><?= $pendientes ?></div>
            <div class="text-muted">pendientes de atender</div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <h5>Mi agenda</h5>
            <p class="text-muted">Ver otros días, cambiar estados y registrar atenciones.</p>
            <a href="agenda.php" class="btn btn-primary">Ir a mi agenda</a>
        </div></div>
    </div>
</div>

<h4>Citas de hoy</h4>
<div class="table-responsive">
<table class="table table-striped bg-white align-middle">
    <thead><tr><th>Hora</th><th>Paciente</th><th>Previsión</th><th>Centro</th><th>Estado</th></tr></thead>
    <tbody>
    <?php foreach ($hoy as $c): ?>
        <tr>
            <td><?= h(date('H:i', strtotime($c['fecha_hora']))) ?></td>
            <td><?= h($c['nombre_paciente']) ?></td>
            <td><?= h($c['nombre_prevision']) ?></td>
            <td><?= h($c['nombre_centro']) ?></td>
            <td><?= badge_estado($c['nombre_estado']) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$hoy): ?>
        <tr><td colspan="5" class="text-center text-muted">No tienes citas hoy.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/pie.php'; ?>