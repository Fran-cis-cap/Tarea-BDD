<?php
session_start();
// Vaciar todas las variables de sesión
$_SESSION = [];
// Destruir la cookie de sesión si es que existe
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
// Destruimos la sesión por completo
session_destroy();

// Redirigimos a la pagina de login
header("Location: login.php");
exit;
?>