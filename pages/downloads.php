<?php
// pages/downloads.php - Página de Mis Descargas
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
$category = $_GET['categoria'] ?? '';
$page = intval($_GET['pagina'] ?? 1);
$perPage = 12;
$offset = ($page - 1) * $perPage;

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener productos con licencias activas del usuario
    $whereConditions = ["ul.user_id = ?", "ul.is_active = 1"];
    $params = [$user['id']];
    
    if ($category) {
        $whereConditions[] = "c.slug = ?";
        $params[] = $category;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $stmt = $db->prepare("
        SELECT ul.*, p.name as product_name, p.slug as product_slug, p.image as product_image,
               p.short_description, p.is_free, p.demo_url,
               c.name as category_name, c.slug as category_slug,
               o.order_number, o.created_at as purchase_date,
               (SELECT COUNT(*) FROM product_versions pv WHERE pv.product_id = p.id) as version_count,
               (SELECT version FROM product_versions pv WHERE pv.product_id = p.id AND pv.is_current = 1) as current_version,
               (SELECT COUNT(*) FROM download_logs dl WHERE dl.user_id = ul.user_id AND dl.product_id = ul.product_id) as total_downloads
        FROM user_licenses ul
        INNER JOIN products p ON ul.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN orders o ON ul.order_id = o.id
        WHERE $whereClause
        ORDER BY ul.created_at DESC
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $userProducts = $stmt->fetchAll();
    
    // Contar total de productos
    $countStmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM user_licenses ul
        INNER JOIN products p ON ul.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE $whereClause
    ");
    $countStmt->execute($params);
    $totalProducts = $countStmt->fetch()['total'];
    $totalPages = ceil($totalProducts / $perPage);
    
    // Obtener categorías con productos del usuario
    $categoriesStmt = $db->prepare("
        SELECT c.name, c.slug, COUNT(ul.id) as product_count
        FROM categories c
        INNER JOIN products p ON c.id = p.category_id
        INNER JOIN user_licenses ul ON p.id = ul.product_id
        WHERE ul.user_id = ? AND ul.is_active = 1
        GROUP BY c.id
        ORDER BY c.name ASC
    ");
    $categoriesStmt->execute([$user['id']]);
    $userCategories = $categoriesStmt->fetchAll();
    
    // Obtener estadísticas del usuario
    $statsStmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT ul.product_id) as total_products,
            COUNT(CASE WHEN ul.downloads_used < ul.downloads_limit THEN 1 END) as available_downloads,
            COUNT(CASE WHEN ul.updates_until IS NULL OR ul.updates_until > NOW() THEN 1 END) as with_updates,
            SUM(ul.downloads_used) as total_downloads_used
        FROM user_licenses ul
        WHERE ul.user_id = ? AND ul.is_active = 1
    ");
    $statsStmt->execute([$user['id']]);
    $stats = $statsStmt->fetch();
    
} catch (Exception $e) {
    logError("Error en página de descargas: " . $e->getMessage());
    $userProducts = [];
    $totalProducts = 0;
    $totalPages = 0;
    $userCategories = [];
    $stats = [
        'total_products' => 0,
        'available_downloads' => 0,
        'with_updates' => 0,
        'total_downloads_used' => 0
    ];
}

$siteName = getSetting('site_name', 'MiSistema');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Descargas - <?php echo htmlspecialchars($siteName); ?></title>
    
    <meta name="description" content="Accede a todos tus productos y descargas">
    <meta name="robots" content="noindex, follow">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    
    <style>
        .page-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
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
        
        .stat-icon.products { background: #e8f5e8; color: #28a745; }
        .stat-icon.downloads { background: #e3f2fd; color: #1976d2; }
        .stat-icon.updates { background: #fff3e0; color: #f57c00; }
        .stat-icon.used { background: #f3e5f5; color: #7b1fa2; }
        
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
        
        .product-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            margin-bottom: 2rem;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .product-header {
            background: linear-gradient(45deg, #f8f9fa, #e9ecef);
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .product-body {
            padding: 1.5rem;
        }
        
        .product-image {
            width: 80px;
            height: 80px;
            border-radius: 10px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 10px;
        }
        
        .license-info {
            background: #e7f3ff;
            border: 1px solid #b8daff;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .download-progress {
            background: #e9ecef;
            border-radius: 10px;
            height: 8px;
            overflow: hidden;
            margin: 0.5rem 0;
        }
        
        .download-progress-bar {
            background: linear-gradient(90deg, #28a745, #20c997);
            height: 100%;
            transition: width 0.3s ease;
        }
        
        .version-badge {
            background: #007bff;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
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
        
        .download-btn {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .download-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
            color: white;
        }
        
        .download-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .updates-badge {
            background: #28a745;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 10px;
            font-size: 0.75rem;
        }
        
        .updates-badge.expired {
            background: #dc3545;
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
                    <li class="breadcrumb-item active">Mis Descargas</li>
                </ol>
            </nav>
            
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-5 mb-2">Mis Descargas</h1>
                    <p class="lead mb-0">
                        Accede a todos tus productos y gestiona tus descargas
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
                    <div class="stat-icon products">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['total_products']; ?></div>
                    <small class="text-muted">Productos Adquiridos</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon downloads">
                        <i class="fas fa-download"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['available_downloads']; ?></div>
                    <small class="text-muted">Descargas Disponibles</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon updates">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['with_updates']; ?></div>
                    <small class="text-muted">Con Actualizaciones</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon used">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['total_downloads_used']; ?></div>
                    <small class="text-muted">Total Descargado</small>
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
        <?php if (!empty($userCategories)): ?>
            <div class="filter-card">
                <h5 class="mb-3">Filtrar por Categoría</h5>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?php echo SITE_URL; ?>/mis-descargas" 
                       class="btn btn-sm <?php echo empty($category) ? 'btn-primary' : 'btn-outline-primary'; ?>">
                        Todas (<?php echo $stats['total_products']; ?>)
                    </a>
                    <?php foreach ($userCategories as $cat): ?>
                        <a href="<?php echo SITE_URL; ?>/mis-descargas?categoria=<?php echo urlencode($cat['slug']); ?>" 
                           class="btn btn-sm <?php echo $category === $cat['slug'] ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <?php echo htmlspecialchars($cat['name']); ?> (<?php echo $cat['product_count']; ?>)
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Lista de Productos -->
        <?php if (empty($userProducts)): ?>
            <div class="empty-state">
                <i class="fas fa-cloud-download-alt"></i>
                <h4>No tienes productos para descargar</h4>
                <p class="mb-4">Cuando compres productos, aparecerán aquí para que puedas descargarlos</p>
                <a href="<?php echo SITE_URL; ?>/productos" class="btn btn-success btn-lg">
                    <i class="fas fa-store me-2"></i>Explorar Productos
                </a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($userProducts as $product): ?>
                    <div class="col-12">
                        <div class="product-card">
                            <div class="product-header">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <div class="product-image">
                                            <?php if ($product['product_image']): ?>
                                                <img src="<?php echo UPLOADS_URL; ?>/products/<?php echo $product['product_image']; ?>" 
                                                     alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                                            <?php else: ?>
                                                <i class="fas fa-cube text-muted fa-2x"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <h5 class="mb-1">
                                                <a href="<?php echo SITE_URL; ?>/producto/<?php echo $product['product_slug']; ?>" 
                                                   class="text-decoration-none text-dark">
                                                    <?php echo htmlspecialchars($product['product_name']); ?>
                                                </a>
                                            </h5>
                                            <p class="text-muted mb-1"><?php echo htmlspecialchars($product['category_name']); ?></p>
                                            <small class="text-muted">
                                                Adquirido: <?php echo formatDate($product['purchase_date']); ?>
                                                <?php if ($product['order_number']): ?>
                                                    | Orden: #<?php echo $product['order_number']; ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="version-badge">
                                            v<?php echo htmlspecialchars($product['current_version'] ?? '1.0'); ?>
                                        </span>
                                        <?php if ($product['updates_until']): ?>
                                            <?php if (strtotime($product['updates_until']) > time()): ?>
                                                <div class="updates-badge mt-2">
                                                    Updates hasta <?php echo formatDate($product['updates_until']); ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="updates-badge expired mt-2">
                                                    Updates expirados
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="updates-badge mt-2">
                                                Updates ilimitados
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="product-body">
                                <?php if ($product['short_description']): ?>
                                    <p class="text-muted mb-3"><?php echo htmlspecialchars($product['short_description']); ?></p>
                                <?php endif; ?>
                                
                                <div class="license-info">
                                    <div class="row align-items-center">
                                        <div class="col-md-4">
                                            <div class="mb-2">
                                                <strong>Descargas utilizadas:</strong>
                                                <span class="float-end"><?php echo $product['downloads_used']; ?>/<?php echo $product['downloads_limit']; ?></span>
                                            </div>
                                            <?php 
                                            $percentage = $product['downloads_limit'] > 0 ? ($product['downloads_used'] / $product['downloads_limit']) * 100 : 0;
                                            ?>
                                            <div class="download-progress">
                                                <div class="download-progress-bar" style="width: <?php echo min(100, $percentage); ?>%"></div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="text-center">
                                                <div><strong><?php echo $product['version_count']; ?></strong></div>
                                                <small class="text-muted">Versiones disponibles</small>
                                            </div>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <div class="d-flex gap-2 justify-content-end">
                                                <?php if ($product['demo_url']): ?>
                                                    <a href="<?php echo $product['demo_url']; ?>" target="_blank" class="btn btn-outline-info">
                                                        <i class="fas fa-eye me-1"></i>Demo
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <a href="<?php echo SITE_URL; ?>/producto/<?php echo $product['product_slug']; ?>" class="btn btn-outline-primary">
                                                    <i class="fas fa-info me-1"></i>Detalles
                                                </a>
                                                
                                                <?php if ($product['downloads_used'] < $product['downloads_limit']): ?>
                                                    <button class="download-btn" onclick="downloadProduct(<?php echo $product['product_id']; ?>)">
                                                        <i class="fas fa-download me-1"></i>Descargar
                                                    </button>
                                                <?php else: ?>
                                                    <button class="download-btn" disabled>
                                                        <i class="fas fa-ban me-1"></i>Límite Alcanzado
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Paginación -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination-container mt-5">
                    <nav aria-label="Paginación de productos">
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
        function downloadProduct(productId) {
            // Mostrar modal de descarga o redirigir
            if (confirm('¿Deseas descargar este producto? Se contabilizará como una descarga utilizada.')) {
                window.location.href = '<?php echo SITE_URL; ?>/download?product=' + productId;
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
        
        // Animar estadísticas al cargar
        document.addEventListener('DOMContentLoaded', function() {
            const statsNumbers = document.querySelectorAll('.stat-number');
            statsNumbers.forEach(stat => {
                const finalValue = parseInt(stat.textContent);
                if (finalValue > 0) {
                    animateCounter(stat, finalValue);
                }
            });
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
                element.textContent = Math.floor(current);
            }, 50);
        }
    </script>
</body>
</html>