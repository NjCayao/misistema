<?php
// pages/dashboard.php - Dashboard del cliente con URLs CORREGIDAS
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

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener estadísticas del usuario
    $stats = [
        'total_orders' => 0,
        'total_spent' => 0,
        'total_downloads' => 0,
        'active_licenses' => 0
    ];
    
    // Total de órdenes completadas
    $stmt = $db->prepare("
        SELECT COUNT(*) as total, COALESCE(SUM(total_amount), 0) as spent
        FROM orders 
        WHERE user_id = ? AND payment_status = 'completed'
    ");
    $stmt->execute([$user['id']]);
    $orderStats = $stmt->fetch();
    $stats['total_orders'] = $orderStats['total'];
    $stats['total_spent'] = $orderStats['spent'];
    
    // Total de descargas
    $stmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM download_logs 
        WHERE user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $downloadStats = $stmt->fetch();
    $stats['total_downloads'] = $downloadStats['total'];
    
    // Licencias activas
    $stmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM user_licenses 
        WHERE user_id = ? AND is_active = 1
    ");
    $stmt->execute([$user['id']]);
    $licenseStats = $stmt->fetch();
    $stats['active_licenses'] = $licenseStats['total'];
    
    // Obtener últimas compras
    $stmt = $db->prepare("
        SELECT o.*, 
               (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count
        FROM orders o 
        WHERE o.user_id = ? 
        ORDER BY o.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$user['id']]);
    $recentOrders = $stmt->fetchAll();
    
    // Obtener productos con licencia activa
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
        LIMIT 10
    ");
    $stmt->execute([$user['id']]);
    $userProducts = $stmt->fetchAll();
    
    // Obtener productos destacados/recomendados
    $stmt = $db->query("
        SELECT p.*, c.name as category_name,
               (SELECT COUNT(*) FROM product_versions pv WHERE pv.product_id = p.id) as version_count
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.is_active = 1 AND (p.is_featured = 1 OR p.is_free = 1)
        ORDER BY p.is_featured DESC, p.download_count DESC
        LIMIT 6
    ");
    $recommendedProducts = $stmt->fetchAll();
    
} catch (Exception $e) {
    logError("Error en dashboard: " . $e->getMessage());
    $recentOrders = [];
    $userProducts = [];
    $recommendedProducts = [];
}

$siteName = getSetting('site_name', 'MiSistema');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Dashboard - <?php echo htmlspecialchars($siteName); ?></title>
    
    <meta name="description" content="Dashboard personal - Gestiona tus compras y descargas">
    <meta name="robots" content="noindex, follow">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    
    <style>
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-right: 1rem;
        }
        
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            height: 100%;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
        }
        
        .stats-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .stats-icon.orders { background: #e3f2fd; color: #1976d2; }
        .stats-icon.spent { background: #f3e5f5; color: #7b1fa2; }
        .stats-icon.downloads { background: #e8f5e8; color: #388e3c; }
        .stats-icon.licenses { background: #fff3e0; color: #f57c00; }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .section-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .section-header {
            padding: 1.5rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .section-body {
            padding: 1.5rem;
        }
        
        .order-item {
            padding: 1rem;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .order-item:hover {
            border-color: #007bff;
            box-shadow: 0 2px 8px rgba(0,123,255,0.1);
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
        
        .product-license {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .product-license:hover {
            border-color: #007bff;
            background: #ffffff;
            box-shadow: 0 4px 12px rgba(0,123,255,0.1);
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .license-info {
            background: #e7f3ff;
            border: 1px solid #b8daff;
            border-radius: 6px;
            padding: 0.75rem;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
        
        .quick-actions {
            background: linear-gradient(45deg, #f8f9fa, #e9ecef);
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        
        .action-btn {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            text-decoration: none;
            color: #495057;
            transition: all 0.3s ease;
            display: block;
        }
        
        .action-btn:hover {
            border-color: #007bff;
            color: #007bff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,123,255,0.15);
        }
        
        .action-btn i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .welcome-section {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <!-- Header principal del sitio -->
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="welcome-section">
                        <div class="d-flex align-items-center">
                            <div class="user-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <h2 class="text-dark mb-1">
                                    ¡Hola, <?php echo htmlspecialchars($user['first_name']); ?>! 👋
                                </h2>
                                <p class="text-muted mb-0">
                                    Bienvenido a tu dashboard personal. Aquí puedes gestionar tus compras y descargas.
                                </p>
                                <small class="text-muted">
                                    Miembro desde <?php echo formatDate($user['created_at'], 'F Y'); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?php echo SITE_URL; ?>/perfil" class="btn btn-light">
                            <i class="fas fa-user-edit me-2"></i>Editar Perfil
                        </a>
                        <a href="<?php echo SITE_URL; ?>/logout" class="btn btn-outline-light">
                            <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="container my-4">
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
        
        <!-- Estadísticas -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon orders">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="stats-number"><?php echo $stats['total_orders']; ?></div>
                    <div class="stats-label">Compras Realizadas</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon spent">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stats-number"><?php echo formatPrice($stats['total_spent']); ?></div>
                    <div class="stats-label">Total Invertido</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon downloads">
                        <i class="fas fa-download"></i>
                    </div>
                    <div class="stats-number"><?php echo $stats['total_downloads']; ?></div>
                    <div class="stats-label">Descargas Realizadas</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-icon licenses">
                        <i class="fas fa-key"></i>
                    </div>
                    <div class="stats-number"><?php echo $stats['active_licenses']; ?></div>
                    <div class="stats-label">Licencias Activas</div>
                </div>
            </div>
        </div>
        
        <!-- Acciones Rápidas -->
        <div class="quick-actions">
            <h5 class="mb-3">Acciones Rápidas</h5>
            <div class="row g-3">
                <div class="col-md-3">
                    <a href="<?php echo SITE_URL; ?>/productos" class="action-btn">
                        <i class="fas fa-store"></i>
                        <div>Explorar Productos</div>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="<?php echo SITE_URL; ?>/mis-compras" class="action-btn">
                        <i class="fas fa-receipt"></i>
                        <div>Mis Compras</div>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="<?php echo SITE_URL; ?>/mis-descargas" class="action-btn">
                        <i class="fas fa-cloud-download-alt"></i>
                        <div>Mis Descargas</div>
                    </a>
                </div>
                <div class="col-md-3">
                    <a href="<?php echo SITE_URL; ?>/contacto" class="action-btn">
                        <i class="fas fa-life-ring"></i>
                        <div>Soporte</div>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Mis Productos -->
            <div class="col-lg-8">
                <div class="section-card">
                    <div class="section-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-box me-2"></i>Mis Productos
                            </h5>
                            <a href="<?php echo SITE_URL; ?>/mis-compras" class="btn btn-sm btn-outline-primary">Ver Todos</a>
                        </div>
                    </div>
                    <div class="section-body">
                        <?php if (empty($userProducts)): ?>
                            <div class="empty-state">
                                <i class="fas fa-box-open"></i>
                                <h6>No tienes productos aún</h6>
                                <p>Explora nuestro catálogo y encuentra el software perfecto para ti</p>
                                <a href="<?php echo SITE_URL; ?>/productos" class="btn btn-primary">
                                    <i class="fas fa-store me-2"></i>Explorar Productos
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($userProducts as $product): ?>
                                <div class="product-license">
                                    <div class="d-flex">
                                        <div class="product-image">
                                            <?php if ($product['product_image']): ?>
                                                <img src="<?php echo UPLOADS_URL; ?>/products/<?php echo $product['product_image']; ?>" 
                                                     alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                                            <?php else: ?>
                                                <i class="fas fa-cube text-muted"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1">
                                                        <a href="<?php echo SITE_URL; ?>/producto/<?php echo $product['product_slug']; ?>" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($product['product_name']); ?>
                                                        </a>
                                                    </h6>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($product['category_name']); ?> • 
                                                        Versión actual: <?php echo htmlspecialchars($product['current_version'] ?? 'N/A'); ?>
                                                    </small>
                                                </div>
                                                <div class="text-end">
                                                    <a href="<?php echo SITE_URL; ?>/download?product=<?php echo $product['product_id']; ?>" 
                                                       class="btn btn-sm btn-primary">
                                                        <i class="fas fa-download me-1"></i>Descargar
                                                    </a>
                                                </div>
                                            </div>
                                            
                                            <div class="license-info">
                                                <div class="row">
                                                    <div class="col-md-4">
                                                        <strong>Descargas:</strong> <?php echo $product['downloads_used']; ?>/<?php echo $product['downloads_limit']; ?>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <strong>Versiones:</strong> <?php echo $product['version_count']; ?> disponibles
                                                    </div>
                                                    <div class="col-md-4">
                                                        <strong>Updates hasta:</strong> 
                                                        <?php if ($product['updates_until']): ?>
                                                            <?php echo formatDate($product['updates_until']); ?>
                                                        <?php else: ?>
                                                            Sin límite
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Últimas Compras -->
                <div class="section-card">
                    <div class="section-header">
                        <h6 class="mb-0">
                            <i class="fas fa-receipt me-2"></i>Últimas Compras
                        </h6>
                    </div>
                    <div class="section-body">
                        <?php if (empty($recentOrders)): ?>
                            <div class="text-center text-muted">
                                <i class="fas fa-receipt fa-2x mb-2 opacity-50"></i>
                                <p class="mb-0">Sin compras aún</p>
                                <a href="<?php echo SITE_URL; ?>/productos" class="btn btn-sm btn-primary mt-2">
                                    <i class="fas fa-store me-1"></i>Explorar Productos
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentOrders as $order): ?>
                                <div class="order-item">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <strong>#<?php echo $order['order_number']; ?></strong>
                                            <div>
                                                <span class="order-status status-<?php echo $order['payment_status']; ?>">
                                                    <?php echo ucfirst($order['payment_status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold"><?php echo formatPrice($order['total_amount']); ?></div>
                                            <small class="text-muted"><?php echo $order['item_count']; ?> productos</small>
                                        </div>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo timeAgo($order['created_at']); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-center mt-3">
                                <a href="<?php echo SITE_URL; ?>/mis-compras" class="btn btn-sm btn-outline-primary">Ver Todas</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Productos Recomendados -->
                <div class="section-card">
                    <div class="section-header">
                        <h6 class="mb-0">
                            <i class="fas fa-star me-2"></i>Recomendados para Ti
                        </h6>
                    </div>
                    <div class="section-body">
                        <?php if (empty($recommendedProducts)): ?>
                            <div class="text-center text-muted">
                                <i class="fas fa-star fa-2x mb-2 opacity-50"></i>
                                <p class="mb-0">Cargando recomendaciones...</p>
                            </div>
                        <?php else: ?>
                            <?php foreach (array_slice($recommendedProducts, 0, 3) as $product): ?>
                                <div class="d-flex mb-3">
                                    <div class="product-image me-3" style="width: 50px; height: 50px;">
                                        <?php if ($product['image']): ?>
                                            <img src="<?php echo UPLOADS_URL; ?>/products/<?php echo $product['image']; ?>" 
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>">
                                        <?php else: ?>
                                            <i class="fas fa-cube text-muted"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1">
                                            <a href="<?php echo SITE_URL; ?>/producto/<?php echo $product['slug']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($product['name']); ?>
                                            </a>
                                        </h6>
                                        <small class="text-muted d-block"><?php echo htmlspecialchars($product['category_name']); ?></small>
                                        <div class="mt-1">
                                            <?php if ($product['is_free']): ?>
                                                <span class="badge bg-success">GRATIS</span>
                                            <?php else: ?>
                                                <strong class="text-primary"><?php echo formatPrice($product['price']); ?></strong>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-center mt-3">
                                <a href="<?php echo SITE_URL; ?>/productos" class="btn btn-sm btn-outline-primary">Ver Más Productos</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animar las estadísticas al cargar
            const statsNumbers = document.querySelectorAll('.stats-number');
            statsNumbers.forEach(stat => {
                const finalValue = parseInt(stat.textContent.replace(/[^\d]/g, ''));
                if (finalValue > 0) {
                    animateCounter(stat, finalValue);
                }
            });
            
            // Auto-dismiss alerts después de 5 segundos
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
        
        function animateCounter(element, target) {
            let current = 0;
            const increment = target / 20;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                
                // Mantener formato si es precio
                if (element.textContent.includes('$')) {
                    element.textContent = '$' + Math.floor(current).toLocaleString();
                } else {
                    element.textContent = Math.floor(current).toLocaleString();
                }
            }, 50);
        }
    </script>
</body>
</html>