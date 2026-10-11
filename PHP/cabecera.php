<?php
// Cabecera compartida (Bootstrap 5 por CDN) con navegación según el rol.
// Antes de incluirla, cada página define $titulo.
$rol = $_SESSION['rol'] ?? '';
$nombre_usuario = $_SESSION['nombre_usuario'] ?? '';
$nombres_rol = [ROL_PACIENTE => 'Paciente', ROL_MEDICO => 'Médico', ROL_ADMIN => 'Administrador'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($titulo ?? 'SaludUSM') ?> - SaludUSM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container">
        <a class="navbar-brand" href="index.php">SaludUSM</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menu">
            <ul class="navbar-nav me-auto">
                <?php if ($rol === ROL_PACIENTE): ?>
                    <li class="nav-item"><a class="nav-link" href="paciente_home.php">Inicio</a></li>
                    <li class="nav-item"><a class="nav-link" href="agendar.php">Agendar hora</a></li>
                    <li class="nav-item"><a class="nav-link" href="mis_citas.php">Mis citas</a></li>
                    <li class="nav-item"><a class="nav-link" href="historial.php">Mi historial</a></li>
                    <li class="nav-item"><a class="nav-link" href="busqueda_avanzada.php">Búsqueda avanzada</a></li>
                    <li class="nav-item"><a class="nav-link" href="perfil.php">Mi perfil</a></li>
                <?php elseif ($rol === ROL_MEDICO): ?>
                    <li class="nav-item"><a class="nav-link" href="medico_home.php">Inicio</a></li>
                    <li class="nav-item"><a class="nav-link" href="agenda.php">Mi agenda</a></li>
                    <li class="nav-item"><a class="nav-link" href="historial.php">Historial de pacientes</a></li>
                    <li class="nav-item"><a class="nav-link" href="busqueda_avanzada.php">Búsqueda avanzada</a></li>
                    <li class="nav-item"><a class="nav-link" href="perfil.php">Mi perfil</a></li>
                <?php elseif ($rol === ROL_ADMIN): ?>
                    <li class="nav-item"><a class="nav-link" href="admin_home.php">Panel de gestión</a></li>
                    <li class="nav-item"><a class="nav-link" href="admin_centros.php">Centros</a></li>
                    <li class="nav-item"><a class="nav-link" href="admin_medicos.php">Médicos</a></li>
                    <li class="nav-item"><a class="nav-link" href="busqueda_avanzada.php">Búsqueda avanzada</a></li>
                    <li class="nav-item"><a class="nav-link" href="perfil.php">Mi perfil</a></li>
                <?php endif; ?>
            </ul>
            <?php if ($rol !== ''): ?>
            <form class="d-flex me-3" method="get" action="buscar_medicos.php">
                <input class="form-control me-2" type="search" name="q" placeholder="Buscar médico o especialidad"
                       value="<?= h($_GET['q'] ?? '') ?>">
                <button class="btn btn-light" type="submit">Buscar</button>
            </form>
            <span class="navbar-text text-white me-3 small">
                <?= h($nombre_usuario) ?> · <?= h($nombres_rol[$rol] ?? $rol) ?>
            </span>
            <a class="btn btn-outline-light" href="logout.php">Cerrar sesión</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<div class="container pb-5">
<?php mostrar_flash(); ?>