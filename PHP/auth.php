<?php
// ============================================================
// AUTENTICACIÓN COMPARTIDA
// login.php guarda en la sesión: id_usuario, rol_usuario, nombre_usuario, rut_usuario.
// Aquí se copian a $_SESSION['id'] y $_SESSION['rol'], que son las que usan
// las demás páginas.
// ============================================================
date_default_timezone_set('America/Santiago');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const ROL_PACIENTE = 'paciente';
const ROL_MEDICO   = 'medico';
const ROL_ADMIN    = 'admin';

if (isset($_SESSION['id_usuario'], $_SESSION['rol_usuario'])) {
    $_SESSION['id']  = $_SESSION['id_usuario'];
    $_SESSION['rol'] = $_SESSION['rol_usuario'];
}

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/funciones.php';

// Exige sesión iniciada y que el rol esté permitido.
// Además, aquí se ejecuta la actualización de citas vencidas: ocurre cada vez que
// cualquier usuario autenticado entra a una página protegida (se indica en el README).
function requerir_rol(array $roles): void
{
    global $pdo;

    if (empty($_SESSION['rol']) || empty($_SESSION['id'])) {
        header('Location: login.php');
        exit;
    }
    if (!in_array($_SESSION['rol'], $roles, true)) {
        http_response_code(403);
        die('Acceso no permitido para tu rol. <a href="index.php">Volver al inicio</a>');
    }
    $pdo->exec('CALL actualizar_citas_vencidas()');
}