<?php
// pages/purchases.php - Página de Mis Compras
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/settings.php';

// Verificar modo mantenimiento
if (getSetting('maintenance_mode', '0') == '1' && !isAdmin()) {
    include '../maintenance.php';
    exit;
}

// Verificar que el usuario está logueado
if (!isLoggedIn()) {
    redirect('/login?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

// Obtener datos del usuario
$user = getCurrentUser();
if (!$user) {
    logoutUser();
    redirect('/login');
}

$success = getFlashMessage('success');
$error = getFlashMessage('error');

// Parámetros de filtrado y paginación
$status = $_GET['estado'] ?? '';
$page = intval($_GET['pagina'] ?? 1);
$perPage = 10;
$offset = ($page - 1) * $perPage;

try {
    $db = Database::getInstance()->getConnection();
    
    // Construir condiciones WHERE
    $whereConditions = ["o.user_id = ?"];
    $params = [$user['id']];
    
    if ($status && in_array($status, ['pending', 'completed', 'failed', 'refunded'])) {
        $whereConditions[] = "o.payment_status = ?";
        $params[] = $status;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Obtener órdenes del usuario
    $stmt = $db->prepare("
        SELECT o.*, 
               (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count,
               (SELECT GROUP_CONCAT(oi.product_name SEPARATOR ', ') FROM order_items oi WHERE oi.order_id = o.id LIMIT 3) as product_names
        FROM orders o 
        WHERE $whereClause
        ORDER BY o.created_at DESC 
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    
    // Contar total de órdenes
    $countStmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM orders o 
        WHERE $whereClause
    ");
    $countStmt->execute($params);
    $totalOrders = $countStmt->fetch()['total'];
    $totalPages = ceil($totalOrders / $perPage);
    
    // Obtener estadísticas del usuario
    $statsStmt = $db->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN payment_status = 'completed' THEN total_amount ELSE 0 END) as total_spent,
            COUNT(CASE WHEN payment_status = 'completed' THEN 1 END) as completed_orders,
            COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_orders
        FROM orders 
        WHERE user_id = ?
    ");
    $statsStmt->execute([$user['id']]);
    $stats = $statsStmt->fetch();
    
} catch (Exception $e) {
    logError("Error en página de compras: " . $e->getMessage());
    $orders = [];
    $totalOrders = 0;
    $totalPages = 0;
    $stats = [
        'total_orders' => 0,
        'total_spent' => 0,
        'completed_orders' => 0,
        'pending_orders' => 0
    ];
}

// Función para obtener detalles de una orden
function getOrderDetails($orderId, $db) {
    try {
        $stmt = $db->prepare("
            SELECT oi.*, p.slug as product_slug, p.image as product_image
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
            ORDER BY oi.id ASC
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

$siteName = getSetting('site_name', 'MiSistema');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Compras - <?php echo htmlspecialchars($siteName); ?></title>
    
    <meta name="description" content="Historial de compras y descargas">
    <meta name="robots" content="noindex, follow">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    
    <style>
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0 2rem;
        }
        
        .stats-row {
            margin-top: -2rem;
            position: relative;
            z-index: 2;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: none;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
        }
        
        .stat-icon.total { background: #e3f2fd; color: #1976d2; }
        .stat-icon.completed { background: #e8f5e8; color: #388e3c; }
        .stat-icon.spent { background: #f3e5f5; color: #7b1fa2; }
        .stat-icon.pending { background: #fff3e0; color: #f57c00; }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .filter-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .order-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .order-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .order-header {
            background: #f8f9fa;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .order-body {
            padding: 1.5rem;
        }
        
        .order-status {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-completed { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-failed { background: #f8d7da; color: #721c24; }
        .status-refunded { background: #d1ecf1; color: #0c5460; }
        
        .product-item {
            padding: 1rem;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .breadcrumb-custom {
            background: transparent;
            padding: 1rem 0;
        }
        
        .breadcrumb-custom .breadcrumb-item + .breadcrumb-item::before {
            color: rgba(255,255,255,0.7);
        }
        
        .breadcrumb-custom .breadcrumb-item a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
        }
        
        .breadcrumb-custom .breadcrumb-item.active {
            color: white;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="breadcrumb-custom">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>">Inicio</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item active">Mis Compras</li>
                </ol>
            </nav>
            
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-5 mb-2">Mis Compras</h1>
                    <p class="lead mb-0">
                        Gestiona tu historial de compras y accede a tus descargas
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="<?php echo SITE_URL; ?>/productos" class="btn btn-light btn-lg">
                        <i class="fas fa-store me-2"></i>Explorar Productos
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="container stats-row">
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon total">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['total_orders']; ?></div>
                    <small class="text-muted">Total Órdenes</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon completed">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['completed_orders']; ?></div>
                    <small class="text-muted">Completadas</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon spent">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-number"><?php echo formatPrice($stats['total_spent']); ?></div>
                    <small class="text-muted">Total Gastado</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon pending">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['pending_orders']; ?></div>
                    <small class="text-muted">Pendientes</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="container my-5">
        <!-- Mostrar mensajes -->
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- Filtros -->
        <div class="filter-card">
            <h5 class="mb-3">Filtrar Compras</h5>
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <option value="">Todos los estados</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completado</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Fallido</option>
                        <option value="refunded" <?php echo $status === 'refunded' ? 'selected' : ''; ?>>Reembolsado</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-2"></i>Aplicar Filtros
                    </button>
                    <a href="<?php echo SITE_URL; ?>/mis-compras" class="btn btn-outline-secondary ms-2">
                        <i class="fas fa-times me-2"></i>Limpiar
                    </a>
                </div>
                <div class="col-md-4 text-md-end">
                    <small class="text-muted">
                        Mostrando <?php echo count($orders); ?> de <?php echo $totalOrders; ?> órdenes
                    </small>
                </div>
            </form>
        </div>
        
        <!-- Lista de Órdenes -->
        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <i class="fas fa-shopping-bag"></i>
                <h4>No tienes compras aún</h4>
                <p class="mb-4">Cuando realices una compra, aparecerá aquí tu historial completo</p>
                <a href="<?php echo SITE_URL; ?>/productos" class="btn btn-primary btn-lg">
                    <i class="fas fa-store me-2"></i>Explorar Productos
                </a>
            </div>
        <?php else: ?>
            <div class="orders-list">
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <h6 class="mb-1">Orden #<?php echo htmlspecialchars($order['order_number']); ?></h6>
                                    <small class="text-muted"><?php echo formatDateTime($order['created_at']); ?></small>
                                </div>
                                <div class="col-md-3">
                                    <span class="order-status status-<?php echo $order['payment_status']; ?>">
                                        <?php
                                        $statusLabels = [
                                            'completed' => 'Completado',
                                            'pending' => 'Pendiente',
                                            'failed' => 'Fallido',
                                            'refunded' => 'Reembolsado'
                                        ];
                                        echo $statusLabels[$order['payment_status']] ?? ucfirst($order['payment_status']);
                                        ?>
                                    </span>
                                </div>
                                <div class="col-md-3">
                                    <strong class="text-primary"><?php echo formatPrice($order['total_amount']); ?></strong>
                                    <br><small class="text-muted"><?php echo ucfirst($order['payment_method']); ?></small>
                                </div>
                                <div class="col-md-3 text-md-end">
                                    <button class="btn btn-sm btn-outline-primary" onclick="toggleOrderDetails(<?php echo $order['id']; ?>)">
                                        <i class="fas fa-eye me-1"></i>Ver Detalles
                                    </button>
                                    <?php if ($order['payment_status'] === 'completed'): ?>
                                        <a href="<?php echo SITE_URL; ?>/download?order=<?php echo $order['id']; ?>" class="btn btn-sm btn-success ms-1">
                                            <i class="fas fa-download me-1"></i>Descargar
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="order-body" id="order-details-<?php echo $order['id']; ?>" style="display: none;">
                            <h6 class="mb-3">Productos Comprados:</h6>
                            <div class="products-list">
                                <?php
                                $orderItems = getOrderDetails($order['id'], $db);
                                foreach ($orderItems as $item):
                                ?>
                                    <div class="product-item">
                                        <div class="d-flex align-items-center">
                                            <div class="product-image me-3">
                                                <?php if ($item['product_image']): ?>
                                                    <img src="<?php echo UPLOADS_URL; ?>/products/<?php echo $item['product_image']; ?>" 
                                                         alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                                <?php else: ?>
                                                    <i class="fas fa-cube text-muted"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">
                                                    <?php if ($item['product_slug']): ?>
                                                        <a href="<?php echo SITE_URL; ?>/producto/<?php echo $item['product_slug']; ?>" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($item['product_name']); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <?php echo htmlspecialchars($item['product_name']); ?>
                                                    <?php endif; ?>
                                                </h6>
                                                <small class="text-muted">Cantidad: <?php echo $item['quantity']; ?></small>
                                            </div>
                                            <div class="text-end">
                                                <strong><?php echo formatPrice($item['price']); ?></strong>
                                                <?php if ($order['payment_status'] === 'completed' && $item['product_id']): ?>
                                                    <br><a href="<?php echo SITE_URL; ?>/download?product=<?php echo $item['product_id']; ?>" class="btn btn-xs btn-outline-success mt-1">
                                                        <i class="fas fa-download me-1"></i>Descargar
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php if (!empty($order['payment_id'])): ?>
                                <div class="mt-3 pt-3 border-top">
                                    <small class="text-muted">
                                        <strong>ID de Pago:</strong> <?php echo htmlspecialchars($order['payment_id']); ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Paginación -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination-container mt-4">
                    <nav aria-label="Paginación de órdenes">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $page - 1])); ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $page + 1])); ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- Footer -->
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
    
    <script>
        function toggleOrderDetails(orderId) {
            const details = document.getElementById('order-details-' + orderId);
            const button = event.target.closest('button');
            const icon = button.querySelector('i');
            
            if (details.style.display === 'none') {
                details.style.display = 'block';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
                button.innerHTML = '<i class="fas fa-eye-slash me-1"></i>Ocultar';
            } else {
                details.style.display = 'none';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
                button.innerHTML = '<i class="fas fa-eye me-1"></i>Ver Detalles';
            }
        }
        
        // Auto-dismiss alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>