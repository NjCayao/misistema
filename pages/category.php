<?php
// pages/category.php - Página de categoría específica
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/settings.php';

// Verificar modo mantenimiento
if (Settings::get('maintenance_mode', '0') == '1' && !isAdmin()) {
    include '../maintenance.php';
    exit;
}

// Obtener slug de la categoría
$categorySlug = $_GET['slug'] ?? '';

if (empty($categorySlug)) {
    header("HTTP/1.0 404 Not Found");
    include '../404.php';
    exit;
}

// Parámetros de filtrado
$search = $_GET['buscar'] ?? '';
$priceMin = floatval($_GET['precio_min'] ?? 0);
$priceMax = floatval($_GET['precio_max'] ?? 1000);
$type = $_GET['tipo'] ?? ''; // 'free', 'paid', 'all'
$sort = $_GET['orden'] ?? 'recientes';
$page = intval($_GET['pagina'] ?? 1);
$perPage = 12;
$offset = ($page - 1) * $perPage;

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener información de la categoría
    $stmt = $db->prepare("SELECT * FROM categories WHERE slug = ? AND is_active = 1");
    $stmt->execute([$categorySlug]);
    $category = $stmt->fetch();
    
    if (!$category) {
        header("HTTP/1.0 404 Not Found");
        include '../404.php';
        exit;
    }
    
    // Construir query de productos
    $whereConditions = ["p.is_active = 1", "p.category_id = ?"];
    $params = [$category['id']];
    
    // Filtro por búsqueda
    if ($search) {
        $whereConditions[] = "(p.name LIKE ? OR p.description LIKE ? OR p.short_description LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Filtro por tipo
    if ($type === 'free') {
        $whereConditions[] = "p.is_free = 1";
    } elseif ($type === 'paid') {
        $whereConditions[] = "p.is_free = 0";
    }
    
    // Filtro por precio
    if ($priceMin > 0) {
        $whereConditions[] = "p.price >= ?";
        $params[] = $priceMin;
    }
    if ($priceMax > 0 && $priceMax < 1000) {
        $whereConditions[] = "p.price <= ?";
        $params[] = $priceMax;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Ordenamiento
    $orderBy = match($sort) {
        'nombre' => 'p.name ASC',
        'precio_asc' => 'p.price ASC',
        'precio_desc' => 'p.price DESC',
        'populares' => 'p.download_count DESC, p.created_at DESC',
        default => 'p.created_at DESC'
    };
    
    // Obtener productos de la categoría
    $stmt = $db->prepare("
        SELECT p.*, 
               (SELECT COUNT(*) FROM product_versions pv WHERE pv.product_id = p.id) as version_count
        FROM products p 
        WHERE $whereClause
        ORDER BY $orderBy
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    // Contar total para paginación
    $countStmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM products p 
        WHERE $whereClause
    ");
    $countStmt->execute($params);
    $totalProducts = $countStmt->fetch()['total'];
    $totalPages = ceil($totalProducts / $perPage);
    
    // Obtener estadísticas de la categoría
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_products,
            SUM(CASE WHEN is_free = 1 THEN 1 ELSE 0 END) as free_products,
            SUM(CASE WHEN is_free = 0 THEN 1 ELSE 0 END) as paid_products,
            AVG(CASE WHEN is_free = 0 THEN price ELSE NULL END) as avg_price,
            MIN(CASE WHEN is_free = 0 THEN price ELSE NULL END) as min_price,
            MAX(CASE WHEN is_free = 0 THEN price ELSE NULL END) as max_price
        FROM products 
        WHERE category_id = ? AND is_active = 1
    ");
    $stmt->execute([$category['id']]);
    $stats = $stmt->fetch();
    
    // Obtener otras categorías
    $stmt = $db->query("
        SELECT c.*, COUNT(p.id) as product_count
        FROM categories c 
        LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
        WHERE c.is_active = 1 AND c.id != {$category['id']}
        GROUP BY c.id 
        ORDER BY c.name ASC
        LIMIT 8
    ");
    $otherCategories = $stmt->fetchAll();
    
} catch (Exception $e) {
    logError("Error en página de categoría: " . $e->getMessage());
    header("HTTP/1.0 500 Internal Server Error");
    include '../500.php';
    exit;
}

$siteName = Settings::get('site_name', 'MiSistema');
$pageTitle = "Categoría: " . $category['name'];
$pageDescription = $category['description'] ?: "Explora todos los productos de la categoría " . $category['name'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo htmlspecialchars($siteName); ?></title>
    
    <meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($category['name'] . ', software, sistemas'); ?>">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo SITE_URL; ?>/categoria/<?php echo $category['slug']; ?>">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    
    <style>
        .category-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 40px;
        }
        
        .category-icon {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 20px;
        }
        
        .category-icon img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
        }
        
        .stats-card {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            display: block;
        }
        
        .stats-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .filter-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .sort-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .other-categories {
            margin-top: 50px;
        }
        
        .category-card-small {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            height: 100%;
        }
        
        .category-card-small:hover {
            transform: translateY(-5px);
        }
        
        .category-card-small .icon {
            width: 50px;
            height: 50px;
            background: #f8f9fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: #007bff;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <!-- Category Header -->
    <div class="category-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="category-icon">
                        <?php if ($category['image']): ?>
                            <img src="<?php echo UPLOADS_URL; ?>/categories/<?php echo $category['image']; ?>" 
                                 alt="<?php echo htmlspecialchars($category['name']); ?>">
                        <?php else: ?>
                            <i class="fas fa-folder"></i>
                        <?php endif; ?>
                    </div>
                    <h1 class="display-4 mb-3"><?php echo htmlspecialchars($category['name']); ?></h1>
                    <?php if ($category['description']): ?>
                        <p class="lead mb-4"><?php echo htmlspecialchars($category['description']); ?></p>
                    <?php endif; ?>
                    
                    <!-- Breadcrumb -->
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>" class="text-white-50">Inicio</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/productos" class="text-white-50">Productos</a></li>
                            <li class="breadcrumb-item active text-white"><?php echo htmlspecialchars($category['name']); ?></li>
                        </ol>
                    </nav>
                </div>
                <div class="col-lg-4">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="stats-card">
                                <span class="stats-number"><?php echo $stats['total_products']; ?></span>
                                <span class="stats-label">Productos</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stats-card">
                                <span class="stats-number"><?php echo $stats['free_products']; ?></span>
                                <span class="stats-label">Gratuitos</span>
                            </div>
                        </div>
                        <?php if ($stats['paid_products'] > 0): ?>
                            <div class="col-6">
                                <div class="stats-card">
                                    <span class="stats-number"><?php echo formatPrice($stats['min_price']); ?></span>
                                    <span class="stats-label">Desde</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stats-card">
                                    <span class="stats-number"><?php echo formatPrice($stats['avg_price']); ?></span>
                                    <span class="stats-label">Promedio</span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="container mb-5">
        <!-- Filtros -->
        <div class="filter-section">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <form method="GET" class="row g-3" id="filterForm">
                        <input type="hidden" name="slug" value="<?php echo $category['slug']; ?>">
                        
                        <div class="col-md-4">
                            <input type="text" class="form-control" name="buscar" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Buscar en <?php echo htmlspecialchars($category['name']); ?>...">
                        </div>
                        
                        <div class="col-md-3">
                            <select name="tipo" class="form-select">
                                <option value="" <?php echo $type === '' ? 'selected' : ''; ?>>Todos los tipos</option>
                                <option value="free" <?php echo $type === 'free' ? 'selected' : ''; ?>>Solo gratuitos</option>
                                <option value="paid" <?php echo $type === 'paid' ? 'selected' : ''; ?>>Solo de pago</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <input type="number" class="form-control" name="precio_min" 
                                   value="<?php echo $priceMin > 0 ? $priceMin : ''; ?>" 
                                   placeholder="Precio mín" min="0" step="0.01">
                        </div>
                        
                        <div class="col-md-2">
                            <input type="number" class="form-control" name="precio_max" 
                                   value="<?php echo $priceMax < 1000 ? $priceMax : ''; ?>" 
                                   placeholder="Precio máx" min="0" step="0.01">
                        </div>
                        
                        <div class="col-md-1">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="<?php echo SITE_URL; ?>/categoria/<?php echo $category['slug']; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> Limpiar Filtros
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Ordenamiento y Resultados -->
        <div class="sort-section">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h4 class="mb-0">
                        <?php if ($search): ?>
                            Resultados para "<?php echo htmlspecialchars($search); ?>"
                        <?php else: ?>
                            Productos en <?php echo htmlspecialchars($category['name']); ?>
                        <?php endif; ?>
                    </h4>
                    <p class="text-muted mb-0">
                        Mostrando <?php echo min($perPage, $totalProducts - $offset); ?> de <?php echo $totalProducts; ?> productos
                    </p>
                </div>
                <div class="col-md-6">
                    <form method="GET" class="d-flex align-items-center justify-content-md-end">
                        <!-- Mantener parámetros actuales -->
                        <input type="hidden" name="slug" value="<?php echo $category['slug']; ?>">
                        <?php foreach ($_GET as $key => $value): ?>
                            <?php if ($key !== 'orden' && $key !== 'slug'): ?>
                                <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                        <label class="me-2">Ordenar por:</label>
                        <select name="orden" class="form-select" style="width: auto;" onchange="this.form.submit()">
                            <option value="recientes" <?php echo $sort === 'recientes' ? 'selected' : ''; ?>>Más Recientes</option>
                            <option value="nombre" <?php echo $sort === 'nombre' ? 'selected' : ''; ?>>Nombre A-Z</option>
                            <option value="precio_asc" <?php echo $sort === 'precio_asc' ? 'selected' : ''; ?>>Precio: Menor a Mayor</option>
                            <option value="precio_desc" <?php echo $sort === 'precio_desc' ? 'selected' : ''; ?>>Precio: Mayor a Menor</option>
                            <option value="populares" <?php echo $sort === 'populares' ? 'selected' : ''; ?>>Más Populares</option>
                        </select>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Grid de Productos -->
        <?php if (empty($products)): ?>
            <div class="text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No se encontraron productos</h4>
                <p class="text-muted">No hay productos que coincidan con los filtros aplicados</p>
                <a href="<?php echo SITE_URL; ?>/categoria/<?php echo $category['slug']; ?>" class="btn btn-primary">Ver Todos los Productos</a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($products as $product): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="product-card h-100">
                            <div class="product-image">
                                <?php if ($product['image']): ?>
                                    <img src="<?php echo UPLOADS_URL; ?>/products/<?php echo $product['image']; ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                         loading="lazy">
                                <?php else: ?>
                                    <div class="no-image">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="product-overlay">
                                    <a href="<?php echo SITE_URL; ?>/producto/<?php echo $product['slug']; ?>" class="btn btn-primary">Ver Detalles</a>
                                </div>
                                <?php if ($product['is_free']): ?>
                                    <span class="product-badge free">GRATIS</span>
                                <?php endif; ?>
                                <?php if ($product['is_featured']): ?>
                                    <span class="product-badge featured">DESTACADO</span>
                                <?php endif; ?>
                            </div>
                            <div class="product-info">
                                <h5 class="product-title">
                                    <a href="<?php echo SITE_URL; ?>/producto/<?php echo $product['slug']; ?>" class="text-decoration-none text-dark">
                                        <?php echo htmlspecialchars($product['name']); ?>
                                    </a>
                                </h5>
                                <p class="product-description"><?php echo htmlspecialchars($product['short_description']); ?></p>
                                
                                <div class="product-meta">
                                    <small class="text-muted">
                                        <i class="fas fa-code-branch"></i> <?php echo $product['version_count']; ?> versiones
                                        <span class="ms-2">
                                            <i class="fas fa-download"></i> <?php echo number_format($product['download_count']); ?>
                                        </span>
                                    </small>
                                </div>
                                
                                <div class="product-footer">
                                    <div class="product-price">
                                        <?php if ($product['is_free']): ?>
                                            <span class="price-free">GRATIS</span>
                                        <?php else: ?>
                                            <span class="price"><?php echo formatPrice($product['price']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="product-actions">
                                        <button class="btn btn-sm btn-outline-primary" onclick="addToCart(<?php echo $product['id']; ?>)" title="Agregar al carrito">
                                            <i class="fas fa-cart-plus"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="addToWishlist(<?php echo $product['id']; ?>)" title="Favoritos">
                                            <i class="fas fa-heart"></i>
                                        </button>
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
        
        <!-- Otras Categorías -->
        <?php if (!empty($otherCategories)): ?>
            <div class="other-categories">
                <h3 class="mb-4">Explorar Otras Categorías</h3>
                <div class="row g-4">
                    <?php foreach ($otherCategories as $otherCategory): ?>
                        <div class="col-md-6 col-lg-3">
                            <a href="<?php echo SITE_URL; ?>/categoria/<?php echo $otherCategory['slug']; ?>" class="text-decoration-none">
                                <div class="category-card-small">
                                    <div class="icon">
                                        <?php if ($otherCategory['image']): ?>
                                            <img src="<?php echo UPLOADS_URL; ?>/categories/<?php echo $otherCategory['image']; ?>" 
                                                 alt="<?php echo htmlspecialchars($otherCategory['name']); ?>" 
                                                 style="width: 30px; height: 30px; border-radius: 50%;">
                                        <?php else: ?>
                                            <i class="fas fa-folder"></i>
                                        <?php endif; ?>
                                    </div>
                                    <h6 class="mb-2"><?php echo htmlspecialchars($otherCategory['name']); ?></h6>
                                    <p class="text-muted mb-0"><?php echo $otherCategory['product_count']; ?> productos</p>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Footer -->
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
    
    <script>
        function addToCart(productId) {
            console.log('Agregar al carrito:', productId);
            // Implementar más adelante
        }
        
        function addToWishlist(productId) {
            console.log('Agregar a favoritos:', productId);
            // Implementar más adelante
        }
        
        // Auto-submit filtros cuando cambien ciertos campos
        document.querySelector('select[name="tipo"]').addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    </script>
</body>
</html>