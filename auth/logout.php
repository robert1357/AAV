<?php
session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';

// Log de actividad antes de cerrar sesión
if (isset($_SESSION['user_id']) && isset($_SESSION['tipo_usuario'])) {
    try {
        logActivity(
            $pdo,
            $_SESSION['user_id'],
            $_SESSION['tipo_usuario'],
            'LOGOUT',
            'Usuario cerró sesión desde IP: ' . $_SERVER['REMOTE_ADDR']
        );
    } catch (Exception $e) {
        // Log error but continue with logout
        error_log("Error logging logout activity: " . $e->getMessage());
    }
}

// Destruir todas las variables de sesión
$_SESSION = array();

// Destruir la cookie de sesión si existe
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir cookies de "recordar sesión" si existen
if (isset($_COOKIE['remember_user'])) {
    setcookie('remember_user', '', time() - 3600, '/');
}

// Destruir la sesión
session_destroy();

// Limpiar cualquier buffer de salida
if (ob_get_level()) {
    ob_end_clean();
}

// Redirigir a la página de login con mensaje de confirmación
header('Location: login.php?message=logout');
exit();
?>