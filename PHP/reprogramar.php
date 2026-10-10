<?php
// 3.6 Reprogramar cita (rol Paciente)
// Solo citas propias en estado Reservada o Confirmada y con fecha futura.
// Usa el procedimiento sp_reprogramar_cita; el trigger evita choques de horario.
require_once __DIR__ . '/auth.php';
requerir_rol([ROL_PACIENTE]);
$idp = (int) $_SESSION['id'];
$idc = (int) ($_GET['id'] ?? $_POST['id_cita'] ?? 0);

$st = $pdo->prepare('SELECT * FROM vista_citas_detalle WHERE id_cita = ? AND id_paciente = ?');
$st->execute([$idc, $idp]);
$cita = $st->fetch(PDO::FETCH_ASSOC);

if (!$cita) {
    flash('danger', 'La cita no existe o no te pertenece.');
    header('Location: mis_citas.php');
    exit;
}
if (!in_array($cita['id_estado'], [1, 2]) || $cita['fecha_hora'] < date('Y-m-d H:i:s')) {
    flash('danger', 'Solo se pueden reprogramar citas Reservadas o Confirmadas cuya fecha no haya pasado.');
    header('Location: mis_citas.php');
    exit;
}

// ---------- POST: reprogramar ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fh = $_POST['fecha_hora'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $fh)) {
        flash('danger', 'Selecciona un bloque horario válido.');
        header('Location: reprogramar.php?id=' . $idc);
        exit;
    }
    try {
        $st = $pdo->prepare('CALL sp_reprogramar_cita(?, ?, ?)');
        $st->execute([$idc, $idp, $fh]);
        $st->closeCursor();
        flash('success', 'Cita reprogramada para el ' . fecha_hora_legible($fh) . '.');
        header('Location: mis_citas.php');
    } catch (PDOException $e) {
        flash('danger', msg_error($e));
        header('Location: reprogramar.php?id=' . $idc . '&fecha=' . substr($fh, 0, 10));
    }
    exit;
}

$fecha = $_GET['fecha'] ?? '';
$hoy = date('Y-m-d');
if (!fecha_valida($fecha) || $fecha < $hoy) {
    $fecha = '';
}
$bloques = $fecha ? generar_bloques($pdo, (int) $cita['id_medico'], $fecha) : [];

$titulo = 'Reprogramar cita';
require __DIR__ . '/cabecera.php';
?>
<h2 class="mb-3">Reprogramar cita</h2>

<div class="card card-body mb-4">
    <p class="mb-1"><strong>Cita actual:</strong> <?= h(fecha_hora_legible($cita['fecha_hora'])) ?> <?= badge_estado($cita['nombre_estado']) ?></p>
    <p class="mb-1"><strong>Médico:</strong> <?= h($cita['nombre_medico']) ?> (<?= h($cita['nombre_especialidad']) ?>)</p>
    <p class="mb-0"><strong>Centro:</strong> <?= h($cita['nombre_centro']) ?></p>
</div>

<form method="get" action="reprogramar.php" class="card card-body mb-4">
    <input type="hidden" name="id" value="<?= $idc ?>">
    <label class="form-label">Nueva fecha</label>
    <input type="date" name="fecha" class="form-control" min="<?= $hoy ?>" value="<?= h($fecha) ?>"
           onchange="this.form.submit()">
</form>

<?php if ($bloques): ?>
    <div class="card card-body mb-3">
        <h5>Bloques disponibles el <?= date('d-m-Y', strtotime($fecha)) ?></h5>
        <form method="post" action="reprogramar.php">
            <input type="hidden" name="id_cita" value="<?= $idc ?>">
            <?php foreach ($bloques as $b): ?>
                <button type="submit" name="fecha_hora" value="<?= h($b['datetime']) ?>"
                        class="btn <?= $b['disponible'] ? 'btn-outline-success' : 'btn-outline-secondary' ?> m-1"
                        <?= $b['disponible'] ? '' : 'disabled' ?>>
                    <?= h($b['hora']) ?>
                </button>
            <?php endforeach; ?>
        </form>
    </div>
<?php endif; ?>

<a href="mis_citas.php" class="btn btn-secondary">Volver a mis citas</a>

<?php require __DIR__ . '/pie.php'; ?>