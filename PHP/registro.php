<?php
//Se incluye el archivo de conexión a la base de datos
require_once 'conexion.php';
// Se crea variable para almacenar un mensaje de error, en caso de que ocurra uno
$error = '';
//Se crea variable para almacenar un mensaje de exito, en caso de que ocurra uno
$mensaje_exito = '';
//Cargamos las previsiones desde la base de datos 
try {
    $stmt_previon = $pdo->query("SELECT id_prevision, nombre_prevision FROM prevision ORDER BY nombre_prevision ASC");
    $previsiones = $stmt_previon->fetchAll();
} catch (PDOException $e) {
    $error = 'Error al cargar el catálogo de previsiones: ' . $e->getMessage();
}

// Procesamos el formulario cuando el usuario hace clic en "Registrarse"
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Se obtiene los datos ingresados por el usuario y los limpiamos 
    $rut = trim($_POST['rut'] ?? '');
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? '');
    $sexo = trim($_POST['sexo'] ?? '');
    $telefono_contacto = trim($_POST['telefono_contacto'] ?? '');
    $comuna_residencia = trim($_POST['comuna_residencia'] ?? '');
    $id_prevision = trim($_POST['id_prevision'] ?? '');
    $contrasena = $_POST['contrasena'] ?? '';
    $confirmar_contrasena = $_POST['confirmar_contrasena'] ?? '';

    // Validamos que no haya campos vacíos
    if (empty($rut) || empty($nombre_completo) || empty($email) || empty($fecha_nacimiento) || empty($sexo) || empty($comuna_residencia) || empty($id_prevision) || empty($contrasena)){
        $error = 'Por favor complete todos los campos obligatorios.';
    }
    //Validamos que el formato del RUT sea válido (12345678-9 o 12345678-K)
    elseif (!preg_match('/^[0-9]{7,8}-[0-9kK]{1}$/', $rut)){
        $error = 'El RUT debe tener un formato válido (ejemplo: 12345678-9 o 12345678-K).';
    }
    //Validamos que el formato del correo electrónico sea correcto
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Por favor ingrese un correo electrónico válido.';
    }
    //Validamos que el largo mínimo de la contraseña sea al menos de 6 caracteres
    elseif (strlen($contrasena)< 6){
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    }
    // Validamos que contenga al menos 2 números
    elseif(preg_match_all('/[0-9]/', $contrasena) < 2) {
        $error = 'La contraseña debe contener al menos 2 números.';
    }
    //Validamos que las contraseñas ingresadas sean iguales
    elseif($contrasena !== $confirmar_contrasena){
        $error = 'Las contraseñas ingresadas no coinciden.';
    } else {
        try{
            //Verificamos si el RUT ya existe en la base de datos
            $stmt_verificar = $pdo->prepare("SELECT id_paciente FROM paciente WHERE rut = ?");
            $stmt_verificar->execute([$rut]);
            if ($stmt_verificar->rowCount() > 0) {
                $error = 'El RUT ingresado ya se encuentra registrado en el sistema.';
            }
            //Verificamos si el email ya existe en la base de datos
            elseif ($stmt_email->rowCount() > 0) {
                $error = 'El correo electrónico ya se encuentra registrado en el sistema.';
            } else{
                // Insertamos al nuevo paciente en la base de datos
                $sql = "INSERT INTO paciente (rut,nombre_completo,email,fecha_nacimiento,sexo,telefono_contacto, comuna_residencia,contrasena, id_prevision) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_insert = $pdo->prepare($sql);
                $stmt_insert->execute([
                    strtoupper($rut),
                    $nombre_completo,
                    strtolower($email),
                    $fecha_nacimiento,
                    $sexo,
                    !empty($telefono_contacto) ? $telefono_contacto : NULL,
                    $comuna_residencia,
                    $contrasena,
                    $id_prevision
                ]);
                $mensaje_exito = 'Se ha registrado exitosamente! Ya puedes iniciar sesión con tu RUT y contraseña.';
            }
        } catch(PDOException $e){
            $error = "Error al registrar el paciente: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Paciente - SaludUSM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5" style="max-width: 600px;">
    <div class="card shadow">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Registro de Nuevo Paciente</h4>
            <a href="login.php" class="btn btn-sm btn-light">Regresar al Inicio de Sesión</a>
        </div>
        <div class="card-body">
            <!-- Se muestra un mensaje de error en caso de que exista uno -->
             <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            <!-- Se muestra un mensaje de exito en caso de que exista uno -->
            <?php if (!empty($mensaje_exito)): ?>
                <div class="alert alert-success" role="alert">
                    <?= htmlspecialchars($mensaje_exito) ?>
                    <div class="mt-2">
                        <a href="login.php" class="btn btn-sm btn-success">Ir al Iniciar Sesión</a>
                    </div>
                </div>
            <?php endif; ?>
            <!-- Formulario de Registro -->
            <form method="POST" action="registro.php">
                <div class="mb-3">
                    <label class="form-label">RUT (Obligatorio) Ejemplo: 12345678-K</label>
                    <input type="text" name="rut" class="form-control" placeholder="12345678-9" value="<?= htmlspecialchars($rut ?? '') ?>" >
                </div>
                <div class="mb-3">
                    <label class="form-label">Nombre Completo (Obligatorio)</label>
                    <input type="text" name="nombre_completo" class="form-control" value="<?= htmlspecialchars($nombre_completo ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Correo Electrónico (Obligatorio)</label>
                    <input type="email" name="email" class="form-control" placeholder="ejemplo@correo.cl" value="<?= htmlspecialchars($email ?? '') ?>">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Fecha de Nacimiento (Obligatorio)</label>
                        <input type="date" name="fecha_nacimiento" class="form-control" value="<?= htmlspecialchars($fecha_nacimiento ?? '') ?>" >
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Sexo (Obligatorio)</label>
                        <select name="sexo" class="form-select">
                            <option value="">Seleccione...</option>
                            <option value="M" <?= (isset($sexo) && $sexo === 'M') ? 'selected' : '' ?>>Masculino</option>
                            <option value="F" <?= (isset($sexo) && $sexo === 'F') ? 'selected' : '' ?>>Femenino</option>
                            <option value="O" <?= (isset($sexo) && $sexo === 'O') ? 'selected' : '' ?>>Otro</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Teléfono de Contacto (Opcional) Ejemplo:+56912345678</label>
                        <input type="text" name="telefono_contacto" class="form-control" value="<?= $telefono_contacto ?? '' ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Comuna de Residencia (Obligatorio)</label>
                        <input type="text" name="comuna_residencia" class="form-control" value="<?= $comuna_residencia ?? '' ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Previsión de Salud (Obligatorio)</label>
                    <select name="id_prevision" class="form-select" >
                        <option value="">Seleccione...</option>
                        <?php foreach ($previsiones as $prevision): ?>
                            <option value="<?= $prevision['id_prevision'] ?>" <?= (isset($id_prevision) && $id_prevision == $prevision['id_prevision']) ? 'selected' : '' ?>><?= $prevision['nombre_prevision'] ?></option>
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

                        


