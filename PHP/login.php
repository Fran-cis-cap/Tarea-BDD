<?php
// Se inicializa o se reanuda la sesión de PHP para poder acceder a las variables de $_SESSION 
session_start();

// Si el usuario ya tiene una sesión activa, entonces se le redirige automaticamente a la página principal para que no vuelva a ver el login
if (isset($_SESSION['id_usuario'])) {
    header('Location: index.php');
    exit;
}
// Se incluye el archivo de conexión a la base de datos
require_once 'conexion.php';

// Se crea variable para almacenar cualquier mensaje de error, en caso de que ocurra uno
$error = '';
// Se comprueba si el usuario hizo clic en el botón de enviar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Se obtiene la información que ingreso el usuario en sus campos correspondientes
    $rut = trim($_POST['rut'] ?? '');
    $contrasena = trim($_POST['contrasena'] ?? '');
    $rol = $_POST['rol'] ?? '';
    // Se valida que no haya campos vacíos
    if (empty($rut) || empty($contrasena) || empty($rol)) {
        $error = 'Por favor complete todos los campos.';
    } else {
        $usuario = null;
        if($rol === 'paciente'){
            $stmt = $pdo->prepare("SELECT id_paciente AS id, nombre_completo, contrasena FROM paciente WHERE rut = ?");
            $stmt->execute([$rut]);
            $usuario= $stmt->fetch();
        } 
        elseif($rol === 'medico'){
            $stmt = $pdo->prepare("SELECT id_medico AS id, nombre_completo, contrasena FROM medico WHERE rut = ?");
            $stmt->execute([$rut]);
            $usuario = $stmt->fetch();
        }
        elseif($rol === 'admin'){
            $stmt = $pdo->prepare("SELECT id_admin AS id, nombre_completo, contrasena FROM administrador WHERE rut = ?");
            $stmt->execute([$rut]);
            $usuario = $stmt->fetch();
        } else{
            $error = 'Rol seleccionado no válido.';
        }
        //Verificamos que exista el usuario y que la contraseña sea correcta
        if ($usuario && $contrasena === $usuario['contrasena']) {
            //Guardamos la información del usuario en la sesión del servidor  
            $_SESSION['id_usuario']     = $usuario['id'];
            $_SESSION['nombre_usuario'] = $usuario['nombre_completo'];
            $_SESSION['rol_usuario']    = $rol;
            $_SESSION['rut_usuario'] = $rut;
            // Redirigimos al usuario a la pagina principal
            header('Location: index.php');
            exit;
        } else {
            //Si el RUT no existe o la contraseña no coincide
            $error = 'RUT o contraseña incorrectos.';
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
                <!-- Se muestra una alerta si hubo un error en el login -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger mb-3"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <!-- Formulario de ingreso -->
                <form method="POST" action="login.php">
                    <div class="mb-3">
                        <label class="form-label">RUT (Formato: 12345678-K):</label>
                        <input type="text" name="rut" class="form-control" placeholder="12345678-9" value="<?= htmlspecialchars($rut ?? '') ?>" >
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña:</label>
                        <input type="password" name="contrasena" class="form-control" >
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tipo de Usuario:</label>
                        <select name="rol" class="form-select">
                            <option value="paciente" <?= (isset($rol) && $rol === 'paciente') ? 'selected' : '' ?>>Paciente</option>
                            <option value="medico" <?= (isset($rol) && $rol === 'medico') ? 'selected' : '' ?>>Médico</option>
                            <option value="admin" <?= (isset($rol) && $rol === 'admin') ? 'selected' : '' ?>>Administrador</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Ingresar</button>
                </form>
                <hr class="my-4">
                <!-- Link para registro de pacientes nuevos -->
                 <div class="text-center">
                    <p class="mb-0">¿Eres paciente nuevo?</p>
                    <a href="registro.php" class="btn btn-outline-success btn-sm mt-2">Registrarse como Paciente</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
