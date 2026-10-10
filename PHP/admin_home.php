<?php
// 3.4.5 Panel de gestión (rol Administrador)
// Total de citas y % de inasistencia por centro (vista_panel_centros, que usa la
// function porcentaje_inasistencia) y los 5 diagnósticos más frecuentes de la red
// (vista_top_diagnosticos).
require_once __DIR__ . '/auth.php';
requerir_rol([ROL_ADMIN]);

$centros = $pdo->query('SELECT * FROM vista_panel_centros ORDER BY nombre_centro')->fetchAll();
$top = $pdo->query('SELECT * FROM vista_top_diagnosticos')->fetchAll();

$titulo = 'Panel de gestión';
require __DIR__ . '/cabecera.php';
?>
<h2 class="mb-4">Panel de gestión</h2>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><strong>Citas e inasistencia por centro médico</strong></div>
            <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle">
                <thead><tr><th>Centro</th><th class="text-end">Total de citas</th><th class="text-end">No asistió</th><th style="width:30%">% de inasistencia</th></tr></thead>
                <tbody>
                <?php foreach ($centros as $c): ?>
                    <tr>
                        <td><?= h($c['nombre_centro']) ?><br><small class="text-muted"><?= h($c['comuna']) ?>, <?= h($c['region']) ?></small></td>
                        <td class="text-end"><?= (int) $c['total_citas'] ?></td>
                        <td class="text-end"><?= (int) $c['total_no_asistio'] ?></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="progress flex-grow-1 me-2" style="height: 10px;">
                                    <div class="progress-bar bg-danger" style="width: <?= (float) $c['porcentaje_inasistencia'] ?>%"></div>
                                </div>
                                <span><?= number_format((float) $c['porcentaje_inasistencia'], 2, ',', '.') ?>%</span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$centros): ?>
                    <tr><td colspan="4" class="text-center text-muted">No hay centros registrados.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><strong>5 diagnósticos más frecuentes de la red</strong></div>
            <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle">
                <thead><tr><th>#</th><th>Código</th><th>Descripción</th><th class="text-end">Veces</th></tr></thead>
                <tbody>
                <?php foreach ($top as $i => $d): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><span class="badge bg-primary"><?= h($d['codigo_cie10']) ?></span></td>
                        <td><?= h($d['descripcion']) ?></td>
                        <td class="text-end"><?= (int) $d['total'] ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$top): ?>
                    <tr><td colspan="4" class="text-center text-muted">Aún no hay diagnósticos registrados.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>

<div class="mt-4">
    <a href="admin_centros.php" class="btn btn-outline-primary">Gestionar centros</a>
    <a href="admin_medicos.php" class="btn btn-outline-primary">Gestionar médicos</a>
</div>

<?php require __DIR__ . '/pie.php'; ?>