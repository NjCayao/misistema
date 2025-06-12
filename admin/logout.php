<?php
// admin/logout.php
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../config/functions.php';

// Destruir sesión de admin
if (isset($_SESSION[ADMIN_SESSION_NAME])) {
    unset($_SESSION[ADMIN_SESSION_NAME]);
}

session_destroy();
redirect(ADMIN_URL . '/login.php');
?>