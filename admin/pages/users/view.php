<?php
// admin/pages/users/view.php - Ver detalles del usuario
require_once '../../../config/database.php';
require_once '../../../config/constants.php';
require_once '../../../config/functions.php';
require_once '../../../config/settings.php';

// Verificar autenticación
if (!isAdmin()) {
    redirect(ADMIN_URL . '/login.php');
}

$userId = intval($_GET['id'] ?? 0);

if ($userId <= 0) {
    setFlashMessage('error', 'Usuario no válido');
    redirect('index.php');
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener datos del usuario
    $stmt = $db->prepare("
        SELECT u.*,
               (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as total_orders,
               (SELECT COUNT(*) FROM orders WHERE user_id = u.id AND payment_status = 'completed') as completed_orders,
               (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE user_id = u.id AND payment_status = 'completed') as total_spent,
               (SELECT COUNT(*) FROM user_licenses WHERE user_id = u.id AND is_active = 1) as active_licenses,
               (SELECT COUNT(*) FROM download_logs WHERE user_id = u.id) as total_downloads
        FROM users u 
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        setFlashMessage('error', 'Usuario no encontrado');
        redirect('index.php');
    }
    
    // Obtener órdenes del usuario
    $stmt = $db->prepare("
        SELECT o.*, 
               (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count,
               (SELECT GROUP_CONCAT(product_name SEPARATOR ', ') FROM order_items oi WHERE oi.order_id = o.id) as products
        FROM orders o 
        WHERE o.user_id = ? 
        ORDER BY o.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$userId]);
    $orders = $stmt->fetchAll();
    
    // Obtener productos con licencia
    $stmt = $db->prepare("
        SELECT ul.*, p.name as product_name, p.slug as product_slug, p.image as product_image,
               c.name as category_name,
               (SELECT COUNT(*) FROM product_versions pv WHERE pv.product_id = p.id) as version_count,
               (SELECT version FROM product_versions pv WHERE pv.product_id = p.id AND pv.is_current = 1) as current_version
        FROM user_licenses ul
        INNER JOIN products p ON ul.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE ul.user_id = ? AND ul.is_active = 1
        ORDER BY ul.created_at DESC
    ");
    $stmt->execute([$userId]);
    $licenses = $stmt->fetchAll();
    
    // Obtener historial de descargas reciente
    $stmt = $db->prepare("
        SELECT dl.*, p.name as product_name, pv.version
        FROM download_logs dl
        INNER JOIN products p ON dl.product_id = p.id
        INNER JOIN product_versions pv ON dl.version_id = pv.id
        WHERE dl.user_id = ?
        ORDER BY dl.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$userId]);
    $downloads = $stmt->fetchAll();
    
} catch (Exception $e) {
    logError("Error obteniendo detalles del usuario: " . $e->getMessage());
    setFlashMessage('error', 'Error al obtener los datos del usuario');
    redirect('index.php');
}

$success = getFlashMessage('success');
$error = getFlashMessage('error');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Usuario: <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?> | <?php echo getSetting('site_name', 'MiSistema'); ?></title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/plugins/fontawesome-free/css/all.min.css">
    <!-- AdminLTE -->
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/dist/css/adminlte.min.css">
    
    <style>
        .user-avatar-large {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #007bff;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }
        
        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .status-verified { background: #cce7ff; color: #004085; }
        .status-unverified { background: #fff3cd; color: #856404; }
        
        .info-item {
            padding: 0.5rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #6c757d;
            width: 120px;
            display: inline-block;
        }
        
        .order-status {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .order-completed { background: #d4edda; color: #155724; }
        .order-pending { background: #fff3cd; color: #856404; }
        .order-failed { background: #f8d7da; color: #721c24; }
        
        .activity-item {
            padding: 0.75rem;
            border-left: 3px solid #007bff;
            margin-bottom: 0.5rem;
            background: #f8f9fa;
            border-radius: 0 0.375rem 0.375rem 0;
        }
        
        .license-card {
            border: 1px solid #e9ecef;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .license-card:hover {
            border-color: #007bff;
            box-shadow: 0 2px 8px rgba(0,123,255,0.1);
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
                            <h1 class="m-0">Perfil de Usuario</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="index.php">Usuarios</a></li>
                                <li class="breadcrumb-item active">Ver Usuario</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <section class="content">
                <div class="container-fluid">
                    
                    <!-- Mensajes -->
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            <i class="icon fas fa-check"></i> <?php echo $success; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            <i class="icon fas fa-ban"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Información del Usuario -->
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-body text-center">
                                    <div class="user-avatar-large mx-auto">
                                        <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                                    </div>
                                    <h4 class="mb-1"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                                    <p class="text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
                                    
                                    <div class="mb-3">
                                        <span class="status-badge <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $user['is_active'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                        <span class="status-badge <?php echo $user['is_verified'] ? 'status-verified' : 'status-unverified'; ?> ml-2">
                                            <?php echo $user['is_verified'] ? 'Verificado' : 'Sin verificar'; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="btn-group w-100" role="group">
                                        <a href="edit.php?id=<?php echo $user['id']; ?>" class="btn btn-warning">
                                            <i class="fas fa-edit"></i> Editar
                                        </a>
                                        <a href="mailto:<?php echo $user['email']; ?>" class="btn btn-info">
                                            <i class="fas fa-envelope"></i> Email
                                        </a>
                                        <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                                            <i class="fas fa-trash"></i> Eliminar
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Información Personal -->
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title"><i class="fas fa-user me-2"></i>Información Personal</h3>
                                </div>
                                <div class="card-body">
                                    <div class="info-item">
                                        <span class="info-label">ID:</span>
                                        #<?php echo $user['id']; ?>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Teléfono:</span>
                                        <?php echo $user['phone'] ? htmlspecialchars($user['phone']) : '<span class="text-muted">No especificado</span>'; ?>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">País:</span>
                                        <?php echo $user['country'] ? htmlspecialchars($user['country']) : '<span class="text-muted">No especificado</span>'; ?>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Registro:</span>
                                        <?php echo formatDateTime($user['created_at']); ?>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Último Login:</span>
                                        <?php if ($user['last_login']): ?>
                                            <?php echo timeAgo($user['last_login']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Nunca</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Estadísticas -->
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title"><i class="fas fa-chart-bar me-2"></i>Estadísticas</h3>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center">
                                        <div class="col-6">
                                            <div class="description-block">
                                                <h5 class="description-header text-info"><?php echo $user['completed_orders']; ?></h5>
                                                <span class="description-text">Compras</span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="description-block">
                                                <h5 class="description-header text-success"><?php echo formatPrice($user['total_spent']); ?></h5>
                                                <span class="description-text">Gastado</span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="description-block">
                                                <h5 class="description-header text-warning"><?php echo $user['active_licenses']; ?></h5>
                                                <span class="description-text">Licencias</span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="description-block">
                                                <h5 class="description-header text-secondary"><?php echo $user['total_downloads']; ?></h5>
                                                <span class="description-text">Descargas</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Contenido Principal -->
                        <div class="col-lg-8">
                            <!-- Tabs -->
                            <div class="card">
                                <div class="card-header p-2">
                                    <ul class="nav nav-pills" id="userTabs">
                                        <li class="nav-item">
                                            <a class="nav-link active" href="#orders" data-toggle="tab">
                                                <i class="fas fa-shopping-bag"></i> Órdenes
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" href="#licenses" data-toggle="tab">
                                                <i class="fas fa-key"></i> Licencias
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" href="#downloads" data-toggle="tab">
                                                <i class="fas fa-download"></i> Descargas
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                                <div class="card-body">
                                    <div class="tab-content">
                                        <!-- Órdenes -->
                                        <div class="tab-pane active" id="orders">
                                            <?php if (empty($orders)): ?>
                                                <div class="text-center py-4">
                                                    <i class="fas fa-shopping-bag fa-3x text-muted mb-3"></i>
                                                    <h5 class="text-muted">Sin órdenes</h5>
                                                    <p class="text-muted">Este usuario no ha realizado ninguna compra</p>
                                                </div>
                                            <?php else: ?>
                                                <div class="table-responsive">
                                                    <table class="table table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th>Orden</th>
                                                                <th>Productos</th>
                                                                <th>Total</th>
                                                                <th>Estado</th>
                                                                <th>Fecha</th>
                                                                <th>Acciones</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($orders as $order): ?>
                                                                <tr>
                                                                    <td>
                                                                        <strong>#<?php echo $order['order_number']; ?></strong><br>
                                                                        <small class="text-muted"><?php echo ucfirst($order['payment_method']); ?></small>
                                                                    </td>
                                                                    <td>
                                                                        <small title="<?php echo htmlspecialchars($order['products']); ?>">
                                                                            <?php echo $order['item_count']; ?> producto(s)
                                                                        </small>
                                                                    </td>
                                                                    <td><strong><?php echo formatPrice($order['total_amount']); ?></strong></td>
                                                                    <td>
                                                                        <span class="order-status order-<?php echo $order['payment_status']; ?>">
                                                                            <?php echo ucfirst($order['payment_status']); ?>
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        <small><?php echo formatDate($order['created_at'], 'd/m/Y'); ?></small>
                                                                    </td>
                                                                    <td>
                                                                        <a href="../orders/view.php?id=<?php echo $order['id']; ?>" 
                                                                           class="btn btn-sm btn-info" title="Ver orden">
                                                                            <i class="fas fa-eye"></i>
                                                                        </a>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Licencias -->
                                        <div class="tab-pane" id="licenses">
                                            <?php if (empty($licenses)): ?>
                                                <div class="text-center py-4">
                                                    <i class="fas fa-key fa-3x text-muted mb-3"></i>
                                                    <h5 class="text-muted">Sin licencias</h5>
                                                    <p class="text-muted">Este usuario no tiene licencias activas</p>
                                                </div>
                                            <?php else: ?>
                                                <?php foreach ($licenses as $license): ?>
                                                    <div class="license-card">
                                                        <div class="row align-items-center">
                                                            <div class="col-md-8">
                                                                <h6 class="mb-1">
                                                                    <a href="../products/view.php?id=<?php echo $license['product_id']; ?>" class="text-decoration-none">
                                                                        <?php echo htmlspecialchars($license['product_name']); ?>
                                                                    </a>
                                                                </h6>
                                                                <small class="text-muted">
                                                                    <?php echo htmlspecialchars($license['category_name']); ?> • 
                                                                    Versión: <?php echo htmlspecialchars($license['current_version'] ?? 'N/A'); ?>
                                                                </small>
                                                                <div class="mt-2">
                                                                    <span class="badge badge-info">
                                                                        Descargas: <?php echo $license['downloads_used']; ?>/<?php echo $license['downloads_limit']; ?>
                                                                    </span>
                                                                    <?php if ($license['updates_until']): ?>
                                                                        <span class="badge badge-success">
                                                                            Updates hasta: <?php echo formatDate($license['updates_until'], 'd/m/Y'); ?>
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-4 text-md-right">
                                                                <small class="text-muted">
                                                                    Licencia desde:<br>
                                                                    <?php echo formatDate($license['created_at'], 'd/m/Y'); ?>
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Descargas -->
                                        <div class="tab-pane" id="downloads">
                                            <?php if (empty($downloads)): ?>
                                                <div class="text-center py-4">
                                                    <i class="fas fa-download fa-3x text-muted mb-3"></i>
                                                    <h5 class="text-muted">Sin descargas</h5>
                                                    <p class="text-muted">Este usuario no ha descargado ningún producto</p>
                                                </div>
                                            <?php else: ?>
                                                <?php foreach ($downloads as $download): ?>
                                                    <div class="activity-item">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($download['product_name']); ?></strong>
                                                                <span class="badge badge-secondary ml-2">v<?php echo htmlspecialchars($download['version']); ?></span>
                                                                <br>
                                                                <small class="text-muted">
                                                                    Tipo: <?php echo ucfirst($download['download_type']); ?> • 
                                                                    IP: <?php echo $download['ip_address']; ?>
                                                                </small>
                                                            </div>
                                                            <small class="text-muted">
                                                                <?php echo timeAgo($download['created_at']); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
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
        function confirmDelete() {
            if (confirm('¿Estás seguro de que deseas eliminar este usuario?\n\nEsta acción eliminará:\n- Toda la información del usuario\n- Sus compras y licencias\n- Su historial completo\n\nEsta acción no se puede deshacer.')) {
                window.location.href = 'delete.php?id=<?php echo $user['id']; ?>&confirm=1';
            }
        }
        
        // Auto-dismiss alerts
        setTimeout(function() {
            $('.alert').fadeOut();
        }, 5000);
    </script>
</body>
</html>