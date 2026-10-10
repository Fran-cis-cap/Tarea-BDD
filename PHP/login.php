<?php
// Se inicializa o se reanuda la sesión de PHP para poder acceder a las variables de $_SESSION
session_start();

// Si el usuario ya tiene una sesión activa, se le redirige a la página principal
if (isset($_SESSION['id_usuario'])) {
    header('Location: index.php');
    exit;
}
// Se incluye el archivo de conexión a la base de datos
require_once 'conexion.php';

$error = '';
$exito = '';
$rut = '';
$rol = '';

// Mensaje cuando el paciente acaba de eliminar su cuenta
if (isset($_GET['cuenta_eliminada'])) {
    $exito = 'Tu cuenta fue eliminada correctamente.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // El RUT se normaliza (mayúsculas) para que coincida con el guardado al registrarse.
    // La contraseña NO se recorta: se compara tal cual se escribió.
    $rut = strtoupper(trim($_POST['rut'] ?? ''));
    $contrasena = $_POST['contrasena'] ?? '';
    $rol = $_POST['rol'] ?? '';

    if ($rut === '' || $contrasena === '' || $rol === '') {
        $error = 'Por favor complete todos los campos.';
    } else {
        // Cada rol tiene su propia tabla; las consultas devuelven id, nombre y contraseña (hash)
        $consultas = [
            'paciente' => 'SELECT id_paciente AS id, nombre_completo, contrasena FROM paciente WHERE rut = ?',
            'medico'   => 'SELECT id_medico AS id, nombre_completo, contrasena FROM medico WHERE rut = ?',
            'admin'    => 'SELECT id_admin AS id, nombre_completo, contrasena FROM administrador WHERE rut = ?',
        ];

        if (!isset($consultas[$rol])) {
            $error = 'Rol seleccionado no válido.';
        } else {
            $stmt = $pdo->prepare($consultas[$rol]);
            $stmt->execute([$rut]);
            $usuario = $stmt->fetch();

            // Las contraseñas están guardadas con password_hash (bcrypt)
            if ($usuario && password_verify($contrasena, $usuario['contrasena'])) {
                // Nueva sesión al iniciar (evita fijación de sesión)
                session_regenerate_id(true);
                $_SESSION['id_usuario']     = $usuario['id'];
                $_SESSION['nombre_usuario'] = $usuario['nombre_completo'];
                $_SESSION['rol_usuario']    = $rol;
                $_SESSION['rut_usuario']    = $rut;

                header('Location: index.php');
                exit;
            } else {
                $error = 'RUT, contraseña o tipo de usuario incorrectos.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SaludUSM - Iniciar Sesión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5" style="max-width: 450px;">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h3 class="card-title text-center text-primary mb-4">SaludUSM</h3>
                <h5 class="card-subtitle text-center text-muted mb-3">Iniciar Sesión</h5>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger mb-3"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($exito !== ''): ?>
                    <div class="alert alert-success mb-3"><?= htmlspecialchars($exito) ?></div>
                <?php endif; ?>

                <form method="POST" action="login.php">
                    <div class="mb-3">
                        <label class="form-label">RUT (Formato: 12345678-K):</label>
                        <input type="text" name="rut" class="form-control" placeholder="12345678-9" value="<?= htmlspecialchars($rut) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña:</label>
                        <input type="password" name="contrasena" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tipo de Usuario:</label>
                        <select name="rol" class="form-select">
                            <option value="paciente" <?= $rol === 'paciente' ? 'selected' : '' ?>>Paciente</option>
                            <option value="medico" <?= $rol === 'medico' ? 'selected' : '' ?>>Médico</option>
                            <option value="admin" <?= $rol === 'admin' ? 'selected' : '' ?>>Administrador</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Ingresar</button>
                </form>
                <hr class="my-4">
                <div class="text-center">
                    <p class="mb-0">¿Eres paciente nuevo?</p>
                    <a href="registro.php" class="btn btn-outline-success btn-sm mt-2">Registrarse como Paciente</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>