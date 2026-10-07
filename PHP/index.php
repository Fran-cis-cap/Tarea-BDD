<?php
// Se inicializa o se reanuda la sesión 
session_start();
//Verificamos que se haya logueado
if (!isset($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}
//Se obtiene el rol del usuario logueado
$rol = $_SESSION['rol_usuario'] ?? '';
// Redirigimos según el rol del usuario a su pagina correspondiente
if($rol === 'paciente'){
    header('Location: paciente_home.php');
    exit;
} 
elseif ($rol === 'medico'){
    header('Location: medico_home.php');
    exit;
}
elseif ($rol === 'admin') {
    header('Location: admin_home.php');
    exit;
} else{
    //En caso de que el rol sea inválido o no exista
    session_destroy();
    header('Location: login.php');
    exit;
}
?>