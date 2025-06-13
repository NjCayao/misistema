<?php
// admin/pages/users/delete.php - Eliminar usuario
require_once '../../../config/database.php';
require_once '../../../config/constants.php';
require_once '../../../config/functions.php';
require_once '../../../config/settings.php';

// Verificar autenticación
if (!isAdmin()) {
    redirect(ADMIN_URL . '/login.php');
}

$userId = intval($_GET['id'] ?? $_POST['user_id'] ?? 0);
$confirm = $_GET['confirm'] ?? '';
$action = $_POST['action'] ?? '';

if ($userId <= 0) {
    setFlashMessage('error', 'Usuario no válido');
    redirect('index.php');
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener datos del usuario
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        setFlashMessage('error', 'Usuario no encontrado');
        redirect('index.php');
    }
    
    // Obtener estadísticas antes de eliminar
    $stmt = $db->prepare("
        SELECT 
            (SELECT COUNT(*) FROM orders WHERE user_id = ?) as total_orders,
            (SELECT COUNT(*) FROM user_licenses WHERE user_id = ?) as total_licenses,
            (SELECT COUNT(*) FROM download_logs WHERE user_id = ?) as total_downloads
    ");
    $stmt->execute([$userId, $userId, $userId]);
    $stats = $stmt->fetch();
    
} catch (Exception $e) {
    logError("Error obteniendo usuario para eliminar: " . $e->getMessage());
    setFlashMessage('error', 'Error al obtener los datos del usuario');
    redirect('index.php');
}

// Procesar eliminación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    try {
        $db->beginTransaction();
        
        // Eliminar en orden para respetar foreign keys
        
        // 1. Eliminar logs de descargas
        $stmt = $db->prepare("DELETE FROM download_logs WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // 2. Eliminar licencias de usuario
        $stmt = $db->prepare("DELETE FROM user_licenses WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // 3. Eliminar items de órdenes
        $stmt = $db->prepare("
            DELETE oi FROM order_items oi 
            INNER JOIN orders o ON oi.order_id = o.id 
            WHERE o.user_id = ?
        ");
        $stmt->execute([$userId]);
        
        // 4. Eliminar órdenes
        $stmt = $db->prepare("DELETE FROM orders WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // 5. Eliminar reseñas de productos (si las hay)
        $stmt = $db->prepare("DELETE FROM product_reviews WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // 6. Finalmente eliminar el usuario
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        $db->commit();
        
        // Log de la eliminación
        logError("Usuario eliminado - ID: $userId, Email: {$user['email']}, Admin: " . $_SESSION[ADMIN_SESSION_NAME]['username'], 'user_deletions.log');
        
        setFlashMessage('success', "Usuario '{$user['first_name']} {$user['last_name']}' eliminado exitosamente junto con todos sus datos.");
        redirect('index.php');
        
    } catch (Exception $e) {
        $db->rollBack();
        logError("Error eliminando usuario: " . $e->getMessage());
        setFlashMessage('error', 'Error al eliminar el usuario. Inténtalo más tarde.');
        redirect('view.php?id=' . $userId);
    }
}

// Si no es confirmación, mostrar página de confirmación
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Eliminar Usuario | <?php echo getSetting('site_name', 'MiSistema'); ?></title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/plugins/fontawesome-free/css/all.min.css">
    <!-- AdminLTE -->
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/dist/css/adminlte.min.css">
    
    <style>
        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #dc3545;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            margin: 0 auto 1rem;
        }
        
        .danger-zone {
            border: 2px solid #dc3545;
            border-radius: 0.375rem;
            background: #fff5f5;
        }
        
        .warning-list li {
            margin-bottom: 0.5rem;
            color: #721c24;
        }
        
        .stats-item {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 0.375rem;
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .stats-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #dc3545;
        }
        
        .stats-label {
            font-size: 0.875rem;
            color: #6c757d;
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">
        <!-- Navbar -->
        <?php include '../../includes/navbar.php'; ?>

        <!-- Sidebar -->
        <?php include '../../includes/sidebar.php'; ?>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Content Header -->
            <div class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0 text-danger">
                                <i class="fas fa-exclamation-triangle"></i> Eliminar Usuario
                            </h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="index.php">Usuarios</a></li>
                                <li class="breadcrumb-item active">Eliminar</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <section class="content">
                <div class="container-fluid">
                    
                    <div class="row justify-content-center">
                        <div class="col-lg-8">
                            <div class="danger-zone">
                                <div class="card-body text-center">
                                    <div class="user-avatar">
                                        <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                                    </div>
                                    
                                    <h3 class="text-danger mb-3">
                                        ¿Eliminar a <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>?
                                    </h3>
                                    
                                    <div class="alert alert-danger">
                                        <h5><i class="fas fa-exclamation-triangle"></i> ¡ADVERTENCIA!</h5>
                                        <p class="mb-0">Esta acción es <strong>IRREVERSIBLE</strong> y eliminará permanentemente:</p>
                                    </div>
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-3">
                                            <div class="stats-item">
                                                <div class="stats-number"><?php echo $stats['total_orders']; ?></div>
                                                <div class="stats-label">Órdenes</div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="stats-item">
                                                <div class="stats-number"><?php echo $stats['total_licenses']; ?></div>
                                                <div class="stats-label">Licencias</div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="stats-item">
                                                <div class="stats-number"><?php echo $stats['total_downloads']; ?></div>
                                                <div class="stats-label">Descargas</div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="stats-item">
                                                <div class="stats-number">1</div>
                                                <div class="stats-label">Usuario</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="text-left mb-4">
                                        <h6 class="text-danger">Se eliminarán permanentemente:</h6>
                                        <ul class="warning-list">
                                            <li><strong>Información personal:</strong> Nombre, email, teléfono, etc.</li>
                                            <li><strong>Historial de compras:</strong> Todas las órdenes y transacciones</li>
                                            <li><strong>Licencias de productos:</strong> Acceso a todos los productos</li>
                                            <li><strong>Historial de descargas:</strong> Registro completo de actividad</li>
                                            <li><strong>Reseñas y comentarios:</strong> Todas las evaluaciones</li>
                                        </ul>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <strong>Información del usuario:</strong><br>
                                        <strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?><br>
                                        <strong>Registro:</strong> <?php echo formatDateTime($user['created_at']); ?><br>
                                        <strong>Último login:</strong> <?php echo $user['last_login'] ? timeAgo($user['last_login']) : 'Nunca'; ?>
                                    </div>
                                    
                                    <!-- Formulario de confirmación -->
                                    <div class="mt-4">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            
                                            <button type="submit" class="btn btn-danger btn-lg mr-3" 
                                                    onclick="return confirmDeletion()">
                                                <i class="fas fa-trash"></i> SÍ, ELIMINAR PERMANENTEMENTE
                                            </button>
                                        </form>
                                        
                                        <a href="view.php?id=<?php echo $userId; ?>" class="btn btn-secondary btn-lg mr-2">
                                            <i class="fas fa-times"></i> Cancelar
                                        </a>
                                        
                                        <a href="edit.php?id=<?php echo $userId; ?>" class="btn btn-warning btn-lg">
                                            <i class="fas fa-edit"></i> Editar en su lugar
                                        </a>
                                    </div>
                                    
                                    <div class="mt-4">
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle"></i>
                                            Considera desactivar al usuario en lugar de eliminarlo si solo quieres suspender el acceso.
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </section>
        </div>

        <!-- Footer -->
        <?php include '../../includes/footer.php'; ?>
    </div>

    <!-- Scripts -->
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/jquery/jquery.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/dist/js/adminlte.min.js"></script>
    
    <script>
        function confirmDeletion() {
            const userName = '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>';
            const userEmail = '<?php echo htmlspecialchars($user['email']); ?>';
            
            const confirmation1 = confirm(`¿Estás ABSOLUTAMENTE SEGURO de que deseas eliminar al usuario "${userName}" (${userEmail})?\n\nEsta acción NO SE PUEDE DESHACER.`);
            
            if (confirmation1) {
                const confirmation2 = confirm(`ÚLTIMA CONFIRMACIÓN:\n\nEsto eliminará permanentemente:\n- Toda la información del usuario\n- ${<?php echo $stats['total_orders']; ?>} órdenes\n- ${<?php echo $stats['total_licenses']; ?>} licencias\n- ${<?php echo $stats['total_downloads']; ?>} registros de descarga\n\n¿Continuar con la eliminación?`);
                
                if (confirmation2) {
                    // Deshabilitar el botón para evitar doble envío
                    $('button[type="submit"]').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Eliminando...');
                    return true;
                }
            }
            
            return false;
        }
        
        // Confirmar antes de salir de la página si hay cambios pendientes
        window.addEventListener('beforeunload', function(e) {
            // Este evento se activa si el usuario intenta salir
        });
    </script>
</body>
</html>