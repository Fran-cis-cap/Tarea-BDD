<?php
// Página principal del paciente: accesos rápidos y próximas citas
require_once __DIR__ . '/auth.php';
requerir_rol([ROL_PACIENTE]);
$idp = (int) $_SESSION['id'];

$st = $pdo->prepare(
    'SELECT * FROM vista_citas_detalle
      WHERE id_paciente = ? AND id_estado IN (1, 2) AND fecha_hora >= NOW()
      ORDER BY fecha_hora LIMIT 3'
);
$st->execute([$idp]);
$proximas = $st->fetchAll();

$titulo = 'Inicio';
require __DIR__ . '/cabecera.php';
?>
<h2 class="mb-1">Hola, <?= h($_SESSION['nombre_usuario'] ?? '') ?></h2>
<p class="text-muted mb-4">¿Qué quieres hacer hoy?</p>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <h5>Agendar hora</h5>
            <p class="text-muted">Elige especialidad, centro, médico y un bloque disponible.</p>
            <a href="agendar.php" class="btn btn-primary">Agendar</a>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <h5>Mis citas</h5>
            <p class="text-muted">Revisa, reprograma o cancela tus citas.</p>
            <a href="mis_citas.php" class="btn btn-outline-primary">Ver mis citas</a>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <h5>Mi historial</h5>
            <p class="text-muted">Consulta tus atenciones, diagnósticos y recetas.</p>
            <a href="historial.php" class="btn btn-outline-primary">Ver historial</a>
        </div></div>
    </div>
</div>

<h4>Próximas citas</h4>
<div class="table-responsive">
<table class="table table-striped bg-white align-middle">
    <thead><tr><th>Fecha y hora</th><th>Médico</th><th>Especialidad</th><th>Centro</th><th>Estado</th></tr></thead>
    <tbody>
    <?php foreach ($proximas as $c): ?>
        <tr>
            <td><?= h(fecha_hora_legible($c['fecha_hora'])) ?></td>
            <td><?= h($c['nombre_medico']) ?></td>
            <td><?= h($c['nombre_especialidad']) ?></td>
            <td><?= h($c['nombre_centro']) ?></td>
            <td><?= badge_estado($c['nombre_estado']) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$proximas): ?>
        <tr><td colspan="5" class="text-center text-muted">No tienes citas próximas.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/pie.php'; ?>