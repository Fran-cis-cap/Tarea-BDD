<?php
// Se incluyen la conexión a la base de datos y las funciones de validación
require_once 'conexion.php';
require_once 'funciones.php';

$error = '';
$mensaje_exito = '';
$previsiones = [];

// Cargamos las previsiones desde la tabla de dominio
try {
    $previsiones = $pdo->query("SELECT id_prevision, nombre_prevision FROM prevision ORDER BY nombre_prevision ASC")->fetchAll();
} catch (PDOException $e) {
    $error = 'Error al cargar el catálogo de previsiones.';
}

// Valores del formulario (para no perderlos si hay un error)
$rut = $nombre_completo = $email = $fecha_nacimiento = $sexo = '';
$telefono_contacto = $comuna_residencia = $id_prevision = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rut = strtoupper(trim($_POST['rut'] ?? ''));
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? '');
    $sexo = trim($_POST['sexo'] ?? '');
    $telefono_contacto = trim($_POST['telefono_contacto'] ?? '');
    $comuna_residencia = trim($_POST['comuna_residencia'] ?? '');
    $id_prevision = trim($_POST['id_prevision'] ?? '');
    $contrasena = $_POST['contrasena'] ?? '';
    $confirmar_contrasena = $_POST['confirmar_contrasena'] ?? '';

    $ids_prevision = array_column($previsiones, 'id_prevision');

    if ($rut === '' || $nombre_completo === '' || $email === '' || $fecha_nacimiento === '' || $sexo === ''
        || $comuna_residencia === '' || $id_prevision === '' || $contrasena === '') {
        $error = 'Por favor complete todos los campos obligatorios.';
    } elseif (!rut_formato_valido($rut)) {
        $error = 'El RUT debe tener un formato válido (ejemplo: 12345678-9 o 12345678-K).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Por favor ingrese un correo electrónico válido.';
    } elseif (!fecha_valida($fecha_nacimiento) || $fecha_nacimiento > date('Y-m-d') || $fecha_nacimiento < '1900-01-01') {
        $error = 'La fecha de nacimiento no es válida.';
    } elseif (!in_array($sexo, ['M', 'F', 'O'], true)) {
        $error = 'Seleccione un sexo válido.';
    } elseif ($telefono_contacto !== '' && !preg_match('/^\+?[0-9]{8,14}$/', $telefono_contacto)) {
        $error = 'El teléfono debe tener entre 8 y 14 dígitos (ejemplo: +56912345678).';
    } elseif (mb_strlen($nombre_completo) > 255 || mb_strlen($comuna_residencia) > 50 || mb_strlen($email) > 100) {
        $error = 'Alguno de los datos ingresados es demasiado largo.';
    } elseif (!in_array((int) $id_prevision, array_map('intval', $ids_prevision), true)) {
        $error = 'Seleccione una previsión válida.';
    } elseif (($msg = validar_contrasena($contrasena)) !== null) {
        $error = $msg;
    } elseif ($contrasena !== $confirmar_contrasena) {
        $error = 'Las contraseñas ingresadas no coinciden.';
    } else {
        try {
            // El RUT y el e-mail deben ser únicos
            $stmt_rut = $pdo->prepare("SELECT id_paciente FROM paciente WHERE rut = ?");
            $stmt_rut->execute([$rut]);
            $stmt_email = $pdo->prepare("SELECT id_paciente FROM paciente WHERE email = ?");
            $stmt_email->execute([$email]);

            if ($stmt_rut->fetch()) {
                $error = 'El RUT ingresado ya se encuentra registrado en el sistema.';
            } elseif ($stmt_email->fetch()) {
                $error = 'El correo electrónico ya se encuentra registrado en el sistema.';
            } else {
                $sql = "INSERT INTO paciente (rut, nombre_completo, email, fecha_nacimiento, sexo, telefono_contacto, comuna_residencia, contrasena, id_prevision)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $pdo->prepare($sql)->execute([
                    $rut,
                    $nombre_completo,
                    $email,
                    $fecha_nacimiento,
                    $sexo,
                    $telefono_contacto !== '' ? $telefono_contacto : null,
                    $comuna_residencia,
                    password_hash($contrasena, PASSWORD_DEFAULT),
                    (int) $id_prevision,
                ]);
                $mensaje_exito = 'Se ha registrado exitosamente! Ya puedes iniciar sesión con tu RUT y contraseña.';
                // Se vacía el formulario
                $rut = $nombre_completo = $email = $fecha_nacimiento = $sexo = '';
                $telefono_contacto = $comuna_residencia = $id_prevision = '';
            }
        } catch (PDOException $e) {
            $error = msg_error($e);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Paciente - SaludUSM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5 mb-5" style="max-width: 600px;">
    <div class="card shadow">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Registro de Nuevo Paciente</h4>
            <a href="login.php" class="btn btn-sm btn-light">Regresar al Inicio de Sesión</a>
        </div>
        <div class="card-body">
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($mensaje_exito !== ''): ?>
                <div class="alert alert-success" role="alert">
                    <?= htmlspecialchars($mensaje_exito) ?>
                    <div class="mt-2">
                        <a href="login.php" class="btn btn-sm btn-success">Ir al Iniciar Sesión</a>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="registro.php">
                <div class="mb-3">
                    <label class="form-label">RUT (Obligatorio) Ejemplo: 12345678-K</label>
                    <input type="text" name="rut" class="form-control" placeholder="12345678-9" value="<?= htmlspecialchars($rut) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Nombre Completo (Obligatorio)</label>
                    <input type="text" name="nombre_completo" class="form-control" value="<?= htmlspecialchars($nombre_completo) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Correo Electrónico (Obligatorio)</label>
                    <input type="email" name="email" class="form-control" placeholder="ejemplo@correo.cl" value="<?= htmlspecialchars($email) ?>">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Fecha de Nacimiento (Obligatorio)</label>
                        <input type="date" name="fecha_nacimiento" class="form-control" max="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($fecha_nacimiento) ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Sexo (Obligatorio)</label>
                        <select name="sexo" class="form-select">
                            <option value="">Seleccione...</option>
                            <option value="M" <?= $sexo === 'M' ? 'selected' : '' ?>>Masculino</option>
                            <option value="F" <?= $sexo === 'F' ? 'selected' : '' ?>>Femenino</option>
                            <option value="O" <?= $sexo === 'O' ? 'selected' : '' ?>>Otro</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Teléfono de Contacto (Opcional) Ejemplo: +56912345678</label>
                        <input type="text" name="telefono_contacto" class="form-control" value="<?= htmlspecialchars($telefono_contacto) ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Comuna de Residencia (Obligatorio)</label>
                        <input type="text" name="comuna_residencia" class="form-control" value="<?= htmlspecialchars($comuna_residencia) ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Previsión de Salud (Obligatorio)</label>
                    <select name="id_prevision" class="form-select">
                        <option value="">Seleccione...</option>
                        <?php foreach ($previsiones as $prevision): ?>
                            <option value="<?= (int) $prevision['id_prevision'] ?>" <?= (string) $id_prevision === (string) $prevision['id_prevision'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($prevision['nombre_prevision']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Contraseña (Obligatorio / Mínimo 6 caracteres y 2 números)</label>
                        <input type="password" name="contrasena" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Confirmar Contraseña (Obligatorio)</label>
                        <input type="password" name="confirmar_contrasena" class="form-control">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100">Registrarse</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>