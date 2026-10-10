<?php
// 3.5 Búsqueda avanzada de citas (todos los roles)
// Filtros combinables: rango de fechas, centro, región, especialidad, médico,
// estado y previsión del paciente.
// Cada rol solo ve lo suyo: el paciente sus citas, el médico las propias, el admin todas.
require_once __DIR__ . '/auth.php';
requerir_rol([ROL_PACIENTE, ROL_MEDICO, ROL_ADMIN]);
$rol = $_SESSION['rol'];
$id  = (int) $_SESSION['id'];

// Filtros recibidos
$desde  = $_GET['desde'] ?? '';
$hasta  = $_GET['hasta'] ?? '';
$centro = (int) ($_GET['centro'] ?? 0);
$region = trim($_GET['region'] ?? '');
$esp    = (int) ($_GET['especialidad'] ?? 0);
$medico = (int) ($_GET['medico'] ?? 0);
$estado = (int) ($_GET['estado'] ?? 0);
$prev   = (int) ($_GET['prevision'] ?? 0);

// Restricción por rol (siempre se aplica, con o sin filtros)
$where = [];
$par   = [];
if ($rol === ROL_PACIENTE) {
    $where[] = 'id_paciente = ?';
    $par[]   = $id;
} elseif ($rol === ROL_MEDICO) {
    $where[] = 'id_medico = ?';
    $par[]   = $id;
}

if (fecha_valida($desde)) { $where[] = 'DATE(fecha_hora) >= ?'; $par[] = $desde; } else { $desde = ''; }
if (fecha_valida($hasta)) { $where[] = 'DATE(fecha_hora) <= ?'; $par[] = $hasta; } else { $hasta = ''; }
if ($centro) { $where[] = 'id_centro = ?';        $par[] = $centro; }
if ($region !== '') { $where[] = 'region = ?';    $par[] = $region; }
if ($esp)    { $where[] = 'id_especialidad = ?';  $par[] = $esp; }
if ($medico && $rol !== ROL_MEDICO) { $where[] = 'id_medico = ?'; $par[] = $medico; }
if ($estado) { $where[] = 'id_estado = ?';        $par[] = $estado; }
if ($prev)   { $where[] = 'id_prevision = ?';     $par[] = $prev; }

$sql = 'SELECT * FROM vista_citas_detalle'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY fecha_hora DESC LIMIT 500';
$st = $pdo->prepare($sql);
$st->execute($par);
$citas = $st->fetchAll(PDO::FETCH_ASSOC);

// Listas para los filtros
$centros  = $pdo->query('SELECT id_centro, nombre_centro FROM centro_medico ORDER BY nombre_centro')->fetchAll(PDO::FETCH_ASSOC);
$regiones = $pdo->query('SELECT DISTINCT region FROM centro_medico ORDER BY region')->fetchAll(PDO::FETCH_COLUMN);
$especs   = $pdo->query('SELECT id_especialidad, nombre_especialidad FROM especialidad ORDER BY nombre_especialidad')->fetchAll(PDO::FETCH_ASSOC);
$medicos  = $pdo->query('SELECT id_medico, nombre_completo FROM medico ORDER BY nombre_completo')->fetchAll(PDO::FETCH_ASSOC);
$estados  = $pdo->query('SELECT id_estado, nombre_estado FROM estado_cita ORDER BY id_estado')->fetchAll(PDO::FETCH_ASSOC);
$previs   = $pdo->query('SELECT id_prevision, nombre_prevision FROM prevision ORDER BY nombre_prevision')->fetchAll(PDO::FETCH_ASSOC);

$titulo = 'Búsqueda avanzada';
require __DIR__ . '/cabecera.php';
?>
<h2 class="mb-3">Búsqueda avanzada de citas</h2>

<form method="get" action="busqueda_avanzada.php" class="card card-body mb-4">
    <div class="row g-3">
        <div class="col-md-3">
            <label class="form-label">Desde</label>
            <input type="date" name="desde" class="form-control" value="<?= h($desde) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Hasta</label>
            <input type="date" name="hasta" class="form-control" value="<?= h($hasta) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Centro médico</label>
            <select name="centro" class="form-select">
                <option value="0">Todos</option>
                <?php foreach ($centros as $c): ?>
                    <option value="<?= $c['id_centro'] ?>" <?= $centro == $c['id_centro'] ? 'selected' : '' ?>><?= h($c['nombre_centro']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Región</label>
            <select name="region" class="form-select">
                <option value="">Todas</option>
                <?php foreach ($regiones as $r): ?>
                    <option value="<?= h($r) ?>" <?= $region === $r ? 'selected' : '' ?>><?= h($r) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Especialidad</label>
            <select name="especialidad" class="form-select">
                <option value="0">Todas</option>
                <?php foreach ($especs as $e): ?>
                    <option value="<?= $e['id_especialidad'] ?>" <?= $esp == $e['id_especialidad'] ? 'selected' : '' ?>><?= h($e['nombre_especialidad']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($rol !== ROL_MEDICO): ?>
        <div class="col-md-3">
            <label class="form-label">Médico</label>
            <select name="medico" class="form-select">
                <option value="0">Todos</option>
                <?php foreach ($medicos as $m): ?>
                    <option value="<?= $m['id_medico'] ?>" <?= $medico == $m['id_medico'] ? 'selected' : '' ?>><?= h($m['nombre_completo']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="col-md-3">
            <label class="form-label">Estado de la cita</label>
            <select name="estado" class="form-select">
                <option value="0">Todos</option>
                <?php foreach ($estados as $e): ?>
                    <option value="<?= $e['id_estado'] ?>" <?= $estado == $e['id_estado'] ? 'selected' : '' ?>><?= h($e['nombre_estado']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Previsión del paciente</label>
            <select name="prevision" class="form-select">
                <option value="0">Todas</option>
                <?php foreach ($previs as $p): ?>
                    <option value="<?= $p['id_prevision'] ?>" <?= $prev == $p['id_prevision'] ? 'selected' : '' ?>><?= h($p['nombre_prevision']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="mt-3">
        <button class="btn btn-primary" type="submit">Buscar</button>
        <a class="btn btn-outline-secondary" href="busqueda_avanzada.php">Limpiar filtros</a>
    </div>
</form>

<p class="text-muted"><?= count($citas) ?> cita(s) encontrada(s)<?= count($citas) === 500 ? ' (se muestran las 500 más recientes)' : '' ?></p>
<div class="table-responsive">
<table class="table table-striped bg-white align-middle">
    <thead>
        <tr><th>Fecha y hora</th><th>Paciente</th><th>Médico</th><th>Especialidad</th><th>Centro</th><th>Estado</th></tr>
    </thead>
    <tbody>
    <?php foreach ($citas as $c): ?>
        <tr>
            <td><?= h(fecha_hora_legible($c['fecha_hora'])) ?></td>
            <td><?= h($c['nombre_paciente']) ?></td>
            <td><?= h($c['nombre_medico']) ?></td>
            <td><?= h($c['nombre_especialidad']) ?></td>
            <td><?= h($c['nombre_centro']) ?></td>
            <td><?= badge_estado($c['nombre_estado']) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$citas): ?>
        <tr><td colspan="6" class="text-center text-muted">No hay citas que coincidan con los filtros.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/pie.php'; ?>