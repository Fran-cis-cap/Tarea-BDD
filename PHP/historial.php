<?php
// 3.7 Historial clínico (solo lectura)
//  - Paciente: ve su propio historial.
//  - Médico: ve el historial de los pacientes con los que tiene o tuvo citas
//    (incluye atenciones de otros médicos, para conocer los antecedentes).
require_once __DIR__ . '/auth.php';
requerir_rol([ROL_PACIENTE, ROL_MEDICO]);
$rol = $_SESSION['rol'];
$id  = (int) $_SESSION['id'];

$lista_pacientes = [];
$paciente = null;

if ($rol === ROL_PACIENTE) {
    $idpac = $id;
} else {
    $idpac = (int) ($_GET['id_paciente'] ?? 0);

    // Pacientes de este médico
    $st = $pdo->prepare(
        'SELECT DISTINCT p.id_paciente, p.rut, p.nombre_completo
           FROM paciente p
           JOIN cita c ON c.id_paciente = p.id_paciente
          WHERE c.id_medico = ?
          ORDER BY p.nombre_completo'
    );
    $st->execute([$id]);
    $lista_pacientes = $st->fetchAll(PDO::FETCH_ASSOC);

    // Solo puede consultar a pacientes con los que tiene alguna cita
    if ($idpac && !in_array($idpac, array_column($lista_pacientes, 'id_paciente'))) {
        flash('danger', 'Ese paciente no está asociado a tus citas.');
        header('Location: historial.php');
        exit;
    }
}

$atenciones = [];
if ($idpac) {
    $st = $pdo->prepare('SELECT nombre_completo, rut FROM paciente WHERE id_paciente = ?');
    $st->execute([$idpac]);
    $paciente = $st->fetch(PDO::FETCH_ASSOC);

    $st = $pdo->prepare('SELECT * FROM vista_historial_clinico WHERE id_paciente = ? ORDER BY fecha_atencion DESC');
    $st->execute([$idpac]);
    $atenciones = $st->fetchAll(PDO::FETCH_ASSOC);
}

$titulo = 'Historial clínico';
require __DIR__ . '/cabecera.php';
?>
<h2 class="mb-3">Historial clínico</h2>

<?php if ($rol === ROL_MEDICO && !$idpac): ?>
    <p>Selecciona un paciente para ver sus atenciones anteriores:</p>
    <div class="list-group">
        <?php foreach ($lista_pacientes as $p): ?>
            <a class="list-group-item list-group-item-action" href="historial.php?id_paciente=<?= $p['id_paciente'] ?>">
                <?= h($p['nombre_completo']) ?> <span class="text-muted">(<?= h($p['rut']) ?>)</span></a>
        <?php endforeach; ?>
        <?php if (!$lista_pacientes): ?>
            <div class="list-group-item text-muted">Aún no tienes pacientes.</div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <?php if ($rol === ROL_MEDICO && $paciente): ?>
        <p><strong>Paciente:</strong> <?= h($paciente['nombre_completo']) ?> (<?= h($paciente['rut']) ?>)
           &nbsp; <a href="historial.php">Cambiar paciente</a></p>
    <?php endif; ?>

    <?php foreach ($atenciones as $a): ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between">
                <strong><?= h(fecha_hora_legible($a['fecha_atencion'])) ?> · <?= h($a['nombre_especialidad']) ?></strong>
                <span><?= h($a['nombre_medico']) ?> · <?= h($a['nombre_centro']) ?></span>
            </div>
            <div class="card-body">
                <p class="mb-1"><strong>Motivo:</strong> <?= h($a['motivo_consulta']) ?></p>
                <p class="mb-1"><strong>Observaciones:</strong> <?= h($a['observaciones_clinicas']) ?></p>
                <p class="mb-1"><strong>Diagnósticos:</strong> <?= $a['diagnosticos'] ? h($a['diagnosticos']) : '<span class="text-muted">Sin diagnósticos</span>' ?></p>
                <p class="mb-0"><strong>Recetas:</strong> <?= $a['recetas'] ? h($a['recetas']) : '<span class="text-muted">Sin recetas</span>' ?></p>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$atenciones): ?>
        <div class="alert alert-info">No hay atenciones registradas.</div>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/pie.php'; ?>