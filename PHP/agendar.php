<?php
// 3.4.4 Agendar hora (rol Paciente)
// Flujo: especialidad -> centro -> médico -> fecha -> bloques libres.
// Usa la function bloque_disponible (para mostrar bloques) y el
// procedimiento sp_agendar_cita (para crear la cita). Los triggers validan
// especialidad, centro y sobre-agendamiento.
require_once __DIR__ . '/auth.php';
requerir_rol([ROL_PACIENTE]);
$idp = (int) $_SESSION['id'];

function url_agendar($esp, $cen, $med, $fecha): string
{
    return 'agendar.php?' . http_build_query([
        'especialidad' => $esp, 'centro' => $cen, 'medico' => $med, 'fecha' => $fecha,
    ]);
}

// ---------- POST: crear la cita ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $esp = (int) ($_POST['especialidad'] ?? 0);
    $cen = (int) ($_POST['centro'] ?? 0);
    $med = (int) ($_POST['medico'] ?? 0);
    $fh  = $_POST['fecha_hora'] ?? '';
    $fecha = substr($fh, 0, 10);

    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $fh)) {
        flash('danger', 'Selecciona un bloque horario válido.');
        header('Location: ' . url_agendar($esp, $cen, $med, $fecha));
        exit;
    }
    try {
        $st = $pdo->prepare('CALL sp_agendar_cita(?, ?, ?, ?, ?)');
        $st->execute([$idp, $med, $cen, $esp, $fh]);
        $st->closeCursor();
        flash('success', 'Cita agendada correctamente para el ' . fecha_hora_legible($fh) . '.');
        header('Location: mis_citas.php');
    } catch (PDOException $e) {
        flash('danger', msg_error($e));
        header('Location: ' . url_agendar($esp, $cen, $med, $fecha));
    }
    exit;
}

// ---------- GET: filtros en cascada ----------
$esp   = (int) ($_GET['especialidad'] ?? 0);
$cen   = (int) ($_GET['centro'] ?? 0);
$med   = (int) ($_GET['medico'] ?? 0);
$fecha = $_GET['fecha'] ?? '';
$hoy   = date('Y-m-d');
if (!fecha_valida($fecha) || $fecha < $hoy) {
    $fecha = '';
}

$especialidades = $pdo->query('SELECT id_especialidad, nombre_especialidad FROM especialidad ORDER BY nombre_especialidad')
                      ->fetchAll(PDO::FETCH_ASSOC);

// Centros donde atiende al menos un médico con esa especialidad
$centros = [];
if ($esp) {
    $st = $pdo->prepare(
        'SELECT DISTINCT cm.id_centro, cm.nombre_centro, cm.comuna
           FROM centro_medico cm
           JOIN medico_centro mc ON mc.id_centro = cm.id_centro
           JOIN medico_especialidad me ON me.id_medico = mc.id_medico
          WHERE me.id_especialidad = ?
          ORDER BY cm.nombre_centro'
    );
    $st->execute([$esp]);
    $centros = $st->fetchAll(PDO::FETCH_ASSOC);
}
if (!in_array($cen, array_column($centros, 'id_centro'))) {
    $cen = 0;
}

// Médicos con esa especialidad que atienden en ese centro
$medicos = [];
if ($esp && $cen) {
    $st = $pdo->prepare(
        'SELECT m.id_medico, m.nombre_completo
           FROM medico m
           JOIN medico_especialidad me ON me.id_medico = m.id_medico
           JOIN medico_centro mc ON mc.id_medico = m.id_medico
          WHERE me.id_especialidad = ? AND mc.id_centro = ?
          ORDER BY m.nombre_completo'
    );
    $st->execute([$esp, $cen]);
    $medicos = $st->fetchAll(PDO::FETCH_ASSOC);
}
if (!in_array($med, array_column($medicos, 'id_medico'))) {
    $med = 0;
}

$bloques = [];
if ($esp && $cen && $med && $fecha) {
    $bloques = generar_bloques($pdo, $med, $fecha);
}

$titulo = 'Agendar hora';
require __DIR__ . '/cabecera.php';
?>
<h2 class="mb-3">Agendar hora</h2>

<form method="get" action="agendar.php" class="card card-body mb-4">
    <div class="row g-3">
        <div class="col-md-3">
            <label class="form-label">1. Especialidad</label>
            <select name="especialidad" class="form-select" onchange="this.form.submit()">
                <option value="0">Selecciona...</option>
                <?php foreach ($especialidades as $e): ?>
                    <option value="<?= $e['id_especialidad'] ?>" <?= $esp == $e['id_especialidad'] ? 'selected' : '' ?>>
                        <?= h($e['nombre_especialidad']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">2. Centro médico</label>
            <select name="centro" class="form-select" onchange="this.form.submit()" <?= $esp ? '' : 'disabled' ?>>
                <option value="0">Selecciona...</option>
                <?php foreach ($centros as $c): ?>
                    <option value="<?= $c['id_centro'] ?>" <?= $cen == $c['id_centro'] ? 'selected' : '' ?>>
                        <?= h($c['nombre_centro']) ?> (<?= h($c['comuna']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">3. Médico</label>
            <select name="medico" class="form-select" onchange="this.form.submit()" <?= $cen ? '' : 'disabled' ?>>
                <option value="0">Selecciona...</option>
                <?php foreach ($medicos as $m): ?>
                    <option value="<?= $m['id_medico'] ?>" <?= $med == $m['id_medico'] ? 'selected' : '' ?>>
                        <?= h($m['nombre_completo']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">4. Fecha</label>
            <input type="date" name="fecha" class="form-control" min="<?= $hoy ?>" value="<?= h($fecha) ?>"
                   onchange="this.form.submit()" <?= $med ? '' : 'disabled' ?>>
        </div>
    </div>
</form>

<?php if ($bloques): ?>
    <div class="card card-body">
        <h5>Bloques disponibles el <?= date('d-m-Y', strtotime($fecha)) ?></h5>
        <p class="text-muted small">Cada bloque dura <?= BLOQUE_MINUTOS ?> minutos. Los bloques ocupados o pasados aparecen deshabilitados.</p>
        <form method="post" action="agendar.php">
            <input type="hidden" name="especialidad" value="<?= $esp ?>">
            <input type="hidden" name="centro" value="<?= $cen ?>">
            <input type="hidden" name="medico" value="<?= $med ?>">
            <?php foreach ($bloques as $b): ?>
                <button type="submit" name="fecha_hora" value="<?= h($b['datetime']) ?>"
                        class="btn <?= $b['disponible'] ? 'btn-outline-success' : 'btn-outline-secondary' ?> m-1"
                        <?= $b['disponible'] ? '' : 'disabled' ?>>
                    <?= h($b['hora']) ?>
                </button>
            <?php endforeach; ?>
        </form>
    </div>
<?php elseif ($esp && !$centros): ?>
    <div class="alert alert-warning">No hay médicos con esa especialidad por ahora.</div>
<?php endif; ?>

<?php require __DIR__ . '/pie.php'; ?>