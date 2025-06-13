<?php
// pages/logout.php - Sistema de logout de usuarios
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/settings.php';

// Verificar que el usuario está logueado
if (!isLoggedIn()) {
    redirect('/pages/login.php');
}

// Obtener datos del usuario antes de hacer logout
$user = getCurrentUser();
$userName = $user ? $user['first_name'] : 'Usuario';

// Realizar logout
logoutUser();

// Eliminar cookie "recordarme" si existe
if (isset($_COOKIE['remember_user'])) {
    setcookie('remember_user', '', time() - 3600, '/');
}

// Mensaje de despedida
setFlashMessage('success', "¡Hasta luego, $userName! Has cerrado sesión exitosamente.");

// Redirigir al login o a la página solicitada
$redirectTo = $_GET['redirect'] ?? '/pages/login.php';
$redirectTo = cleanUrl($redirectTo);

// Validar que la redirección sea segura
if (strpos($redirectTo, 'http') === 0 && strpos($redirectTo, SITE_URL) !== 0) {
    $redirectTo = '/pages/login.php';
}

redirect($redirectTo);
?>