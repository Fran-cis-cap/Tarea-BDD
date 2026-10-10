<?php
// 3.4.1 Barra de búsqueda de médicos (todos los roles)
// Busca por un trozo del nombre del médico o de una de sus especialidades.
require_once __DIR__ . '/auth.php';
requerir_rol([ROL_PACIENTE, ROL_MEDICO, ROL_ADMIN]);

$q = trim($_GET['q'] ?? '');
$medicos = [];

if ($q !== '') {
    $like = '%' . $q . '%';
    $st = $pdo->prepare(
        "SELECT m.id_medico, m.nombre_completo,
                (SELECT GROUP_CONCAT(e.nombre_especialidad ORDER BY e.nombre_especialidad SEPARATOR ', ')
                   FROM medico_especialidad me
                   JOIN especialidad e ON e.id_especialidad = me.id_especialidad
                  WHERE me.id_medico = m.id_medico) AS especialidades,
                (SELECT GROUP_CONCAT(cm.nombre_centro ORDER BY cm.nombre_centro SEPARATOR ', ')
                   FROM medico_centro mc
                   JOIN centro_medico cm ON cm.id_centro = mc.id_centro
                  WHERE mc.id_medico = m.id_medico) AS centros
           FROM medico m
          WHERE m.nombre_completo LIKE ?
             OR EXISTS (SELECT 1
                          FROM medico_especialidad me2
                          JOIN especialidad e2 ON e2.id_especialidad = me2.id_especialidad
                         WHERE me2.id_medico = m.id_medico
                           AND e2.nombre_especialidad LIKE ?)
          ORDER BY m.nombre_completo"
    );
    $st->execute([$like, $like]);
    $medicos = $st->fetchAll(PDO::FETCH_ASSOC);
}

$titulo = 'Buscar médicos';
require __DIR__ . '/cabecera.php';
?>
<h2 class="mb-3">Buscar médicos</h2>

<form method="get" action="buscar_medicos.php" class="card card-body mb-4">
    <div class="row g-2">
        <div class="col-md-9">
            <input type="search" name="q" class="form-control" value="<?= h($q) ?>"
                   placeholder="Parte del nombre del médico o de su especialidad">
        </div>
        <div class="col-md-3"><button class="btn btn-primary w-100" type="submit">Buscar</button></div>
    </div>
</form>

<?php if ($q === ''): ?>
    <div class="alert alert-info">Escribe un nombre o una especialidad para buscar.</div>
<?php else: ?>
    <p class="text-muted"><?= count($medicos) ?> resultado(s) para «<?= h($q) ?>»</p>
    <div class="table-responsive">
    <table class="table table-striped bg-white">
        <thead><tr><th>Médico</th><th>Especialidades</th><th>Centros donde atiende</th></tr></thead>
        <tbody>
        <?php foreach ($medicos as $m): ?>
            <tr>
                <td><?= h($m['nombre_completo']) ?></td>
                <td><?= h($m['especialidades']) ?></td>
                <td><?= h($m['centros']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$medicos): ?>
            <tr><td colspan="3" class="text-center text-muted">No se encontraron médicos.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/pie.php'; ?>