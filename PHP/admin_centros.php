<?php
// 3.8 CRUD de centros médicos (rol Administrador): código, nombre, comuna y región.
// No se puede eliminar un centro que tenga citas (clave foránea con RESTRICT).
require_once __DIR__ . '/auth.php';
requerir_rol([ROL_ADMIN]);

$regiones = ['Arica y Parinacota', 'Tarapacá', 'Antofagasta', 'Atacama', 'Coquimbo', 'Valparaíso',
             'Metropolitana', "O'Higgins", 'Maule', 'Ñuble', 'Biobío', 'La Araucanía', 'Los Ríos',
             'Los Lagos', 'Aysén', 'Magallanes'];

// ---------- POST: crear, editar o eliminar ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id = (int) ($_POST['id_centro'] ?? 0);
    $volver = 'admin_centros.php';

    try {
        if ($accion === 'eliminar') {
            $pdo->prepare('DELETE FROM centro_medico WHERE id_centro = ?')->execute([$id]);
            flash('success', 'Centro eliminado.');
        } elseif (in_array($accion, ['crear', 'editar'])) {
            $codigo = trim($_POST['codigo_interno'] ?? '');
            $nombre = trim($_POST['nombre_centro'] ?? '');
            $comuna = trim($_POST['comuna'] ?? '');
            $region = trim($_POST['region'] ?? '');

            if ($accion === 'editar') {
                $volver = 'admin_centros.php?editar=' . $id;
            }
            if ($codigo === '' || $nombre === '' || $comuna === '' || $region === '') {
                throw new RuntimeException('Todos los campos son obligatorios.');
            }
            if (mb_strlen($codigo) > 20 || mb_strlen($nombre) > 100 || mb_strlen($comuna) > 50 || mb_strlen($region) > 50) {
                throw new RuntimeException('Alguno de los datos es demasiado largo.');
            }

            if ($accion === 'crear') {
                $pdo->prepare('INSERT INTO centro_medico (codigo_interno, nombre_centro, comuna, region) VALUES (?, ?, ?, ?)')
                    ->execute([$codigo, $nombre, $comuna, $region]);
                flash('success', 'Centro creado.');
            } else {
                $pdo->prepare('UPDATE centro_medico SET codigo_interno = ?, nombre_centro = ?, comuna = ?, region = ? WHERE id_centro = ?')
                    ->execute([$codigo, $nombre, $comuna, $region, $id]);
                flash('success', 'Centro actualizado.');
                $volver = 'admin_centros.php';
            }
        }
    } catch (RuntimeException $e) {
        flash('danger', $e->getMessage());
    } catch (PDOException $e) {
        $codigo_err = (int) ($e->errorInfo[1] ?? 0);
        if ($codigo_err === 1062) {
            flash('danger', 'Ya existe un centro con ese código.');
        } elseif ($codigo_err === 1451) {
            flash('danger', 'No se puede eliminar el centro porque tiene citas registradas.');
        } else {
            flash('danger', msg_error($e));
        }
    }
    header('Location: ' . $volver);
    exit;
}

// ---------- GET ----------
$editar = null;
$editar_id = (int) ($_GET['editar'] ?? 0);
if ($editar_id) {
    $st = $pdo->prepare('SELECT * FROM centro_medico WHERE id_centro = ?');
    $st->execute([$editar_id]);
    $editar = $st->fetch() ?: null;
}

$centros = $pdo->query(
    'SELECT cm.*,
            (SELECT COUNT(*) FROM medico_centro mc WHERE mc.id_centro = cm.id_centro) AS medicos,
            (SELECT COUNT(*) FROM cita c WHERE c.id_centro = cm.id_centro) AS citas
       FROM centro_medico cm ORDER BY cm.nombre_centro'
)->fetchAll();

$titulo = 'Centros médicos';
require __DIR__ . '/cabecera.php';
?>
<h2 class="mb-3">Centros médicos</h2>

<form method="post" class="card card-body mb-4">
    <h5><?= $editar ? 'Editar centro' : 'Nuevo centro' ?></h5>
    <input type="hidden" name="accion" value="<?= $editar ? 'editar' : 'crear' ?>">
    <?php if ($editar): ?><input type="hidden" name="id_centro" value="<?= $editar['id_centro'] ?>"><?php endif; ?>
    <div class="row g-3">
        <div class="col-md-2">
            <label class="form-label">Código</label>
            <input type="text" name="codigo_interno" class="form-control" maxlength="20" required value="<?= h($editar['codigo_interno'] ?? '') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Nombre</label>
            <input type="text" name="nombre_centro" class="form-control" maxlength="100" required value="<?= h($editar['nombre_centro'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Comuna</label>
            <input type="text" name="comuna" class="form-control" maxlength="50" required value="<?= h($editar['comuna'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Región</label>
            <input type="text" name="region" class="form-control" maxlength="50" list="lista_regiones" required value="<?= h($editar['region'] ?? '') ?>">
            <datalist id="lista_regiones">
                <?php foreach ($regiones as $r): ?><option value="<?= h($r) ?>"><?php endforeach; ?>
            </datalist>
        </div>
    </div>
    <div class="mt-3">
        <button class="btn btn-primary" type="submit"><?= $editar ? 'Guardar cambios' : 'Crear centro' ?></button>
        <?php if ($editar): ?><a href="admin_centros.php" class="btn btn-outline-secondary">Cancelar</a><?php endif; ?>
    </div>
</form>

<div class="table-responsive">
<table class="table table-striped bg-white align-middle">
    <thead><tr><th>Código</th><th>Nombre</th><th>Comuna</th><th>Región</th><th class="text-end">Médicos</th><th class="text-end">Citas</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($centros as $c): ?>
        <tr>
            <td><?= h($c['codigo_interno']) ?></td>
            <td><?= h($c['nombre_centro']) ?></td>
            <td><?= h($c['comuna']) ?></td>
            <td><?= h($c['region']) ?></td>
            <td class="text-end"><?= (int) $c['medicos'] ?></td>
            <td class="text-end"><?= (int) $c['citas'] ?></td>
            <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="admin_centros.php?editar=<?= $c['id_centro'] ?>">Editar</a>
                <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este centro?');">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id_centro" value="<?= $c['id_centro'] ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$centros): ?>
        <tr><td colspan="7" class="text-center text-muted">No hay centros registrados.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>

<?php require __DIR__ . '/pie.php'; ?>