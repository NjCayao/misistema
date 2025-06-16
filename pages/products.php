<?php
// pages/products.php - Catálogo de productos con filtros
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/settings.php';

// Verificar modo mantenimiento
if (Settings::get('maintenance_mode', '0') == '1' && !isAdmin()) {
    include '../maintenance.php';
    exit;
}

// Parámetros de filtrado
$category = $_GET['categoria'] ?? '';
$search = $_GET['buscar'] ?? '';
$priceMin = floatval($_GET['precio_min'] ?? 0);
$priceMax = floatval($_GET['precio_max'] ?? 1000);
$type = $_GET['tipo'] ?? ''; // 'free', 'paid', 'all'
$sort = $_GET['orden'] ?? 'recientes'; // 'recientes', 'nombre', 'precio_asc', 'precio_desc'
$page = intval($_GET['pagina'] ?? 1);
$perPage = 12;
$offset = ($page - 1) * $perPage;

try {
    $db = Database::getInstance()->getConnection();
    
    // Construir query base
    $whereConditions = ["p.is_active = 1"];
    $params = [];
    
    // Filtro por categoría
    if ($category) {
        $whereConditions[] = "c.slug = ?";
        $params[] = $category;
    }
    
    // Filtro por búsqueda
    if ($search) {
        $whereConditions[] = "(p.name LIKE ? OR p.description LIKE ? OR p.short_description LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Filtro por tipo (gratis/pagado)
    if ($type === 'free') {
        $whereConditions[] = "p.is_free = 1";
    } elseif ($type === 'paid') {
        $whereConditions[] = "p.is_free = 0";
    }
    
    // Filtro por precio (solo para productos pagados)
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
    
    // Obtener productos
    $stmt = $db->prepare("
        SELECT p.*, c.name as category_name, c.slug as category_slug,
               (SELECT COUNT(*) FROM product_versions pv WHERE pv.product_id = p.id) as version_count
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
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
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE $whereClause
    ");
    $countStmt->execute($params);
    $totalProducts = $countStmt->fetch()['total'];
    $totalPages = ceil($totalProducts / $perPage);
    
    // Obtener categorías para filtros
    $stmt = $db->query("
        SELECT c.*, COUNT(p.id) as product_count
        FROM categories c 
        LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
        WHERE c.is_active = 1 
        GROUP BY c.id 
        ORDER BY c.name ASC
    ");
    $categories = $stmt->fetchAll();
    
} catch (Exception $e) {
    $products = [];
    $categories = [];
    $totalProducts = 0;
    $totalPages = 0;
    logError("Error en página productos: " . $e->getMessage());
}

$siteName = Settings::get('site_name', 'MiSistema');
$pageTitle = $search ? "Búsqueda: $search" : ($category ? "Categoría: $category" : "Todos los Productos");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo htmlspecialchars($siteName); ?></title>
    
    <meta name="description" content="Explora nuestro catálogo completo de productos de software">
    <meta name="keywords" content="software, sistemas, productos, <?php echo htmlspecialchars($category); ?>">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    
    <style>
        .filter-sidebar {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .filter-section {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .filter-section:last-child {
            border-bottom: none;
        }
        
        .filter-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
        }
        
        .price-range {
            margin: 10px 0;
        }
        
        .sort-options {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .results-info {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .pagination-container {
            margin-top: 40px;
        }
        
        .category-filter {
            max-height: 200px;
            overflow-y: auto;
        }
        
        .category-filter .form-check {
            margin-bottom: 8px;
        }
        
        .clear-filters {
            color: #dc3545;
            font-size: 0.9rem;
        }
        
        .clear-filters:hover {
            color: #c82333;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <!-- Breadcrumb -->
    <div class="container mt-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>">Inicio</a></li>
                <li class="breadcrumb-item active">Productos</li>
                <?php if ($category): ?>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($category); ?></li>
                <?php endif; ?>
            </ol>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="container my-5">
        <div class="row">
            <!-- Sidebar Filtros -->
            <div class="col-lg-3">
                <div class="filter-sidebar">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Filtros</h5>
                        <a href="<?php echo SITE_URL; ?>/productos" class="clear-filters">
                            <i class="fas fa-times"></i> Limpiar
                        </a>
                    </div>
                    
                    <form method="GET" id="filterForm">
                        <!-- Búsqueda -->
                        <div class="filter-section">
                            <h6 class="filter-title">Buscar</h6>
                            <input type="text" class="form-control" name="buscar" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Buscar productos...">
                        </div>
                        
                        <!-- Categorías -->
                        <div class="filter-section">
                            <h6 class="filter-title">Categorías</h6>
                            <div class="category-filter">
                                <?php foreach ($categories as $cat): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="categoria" 
                                               value="<?php echo $cat['slug']; ?>" id="cat_<?php echo $cat['id']; ?>"
                                               <?php echo $category === $cat['slug'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="cat_<?php echo $cat['id']; ?>">
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                            <span class="text-muted">(<?php echo $cat['product_count']; ?>)</span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Tipo de Producto -->
                        <div class="filter-section">
                            <h6 class="filter-title">Tipo</h6>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tipo" value="" id="tipo_all"
                                       <?php echo $type === '' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="tipo_all">Todos</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tipo" value="free" id="tipo_free"
                                       <?php echo $type === 'free' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="tipo_free">Gratuitos</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="tipo" value="paid" id="tipo_paid"
                                       <?php echo $type === 'paid' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="tipo_paid">De Pago</label>
                            </div>
                        </div>
                        
                        <!-- Rango de Precio -->
                        <div class="filter-section">
                            <h6 class="filter-title">Precio</h6>
                            <div class="row">
                                <div class="col-6">
                                    <input type="number" class="form-control form-control-sm" name="precio_min" 
                                           value="<?php echo $priceMin > 0 ? $priceMin : ''; ?>" 
                                           placeholder="Mín" min="0" step="0.01">
                                </div>
                                <div class="col-6">
                                    <input type="number" class="form-control form-control-sm" name="precio_max" 
                                           value="<?php echo $priceMax < 1000 ? $priceMax : ''; ?>" 
                                           placeholder="Máx" min="0" step="0.01">
                                </div>
                            </div>
                            <div class="price-range">
                                <small class="text-muted">Rangos populares:</small><br>
                                <button type="button" class="btn btn-outline-secondary btn-sm me-1 mb-1" onclick="setPriceRange(0, 10)">$0-$10</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm me-1 mb-1" onclick="setPriceRange(10, 50)">$10-$50</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm me-1 mb-1" onclick="setPriceRange(50, 100)">$50-$100</button>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter"></i> Aplicar Filtros
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Contenido Principal -->
            <div class="col-lg-9">
                <!-- Header de Resultados -->
                <div class="sort-options">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h4 class="mb-0"><?php echo htmlspecialchars($pageTitle); ?></h4>
                            <p class="results-info mb-0">
                                Mostrando <?php echo min($perPage, $totalProducts - $offset); ?> de <?php echo $totalProducts; ?> resultados
                            </p>
                        </div>
                        <div class="col-md-6">
                            <form method="GET" class="d-flex align-items-center justify-content-md-end">
                                <!-- Mantener filtros actuales -->
                                <?php foreach ($_GET as $key => $value): ?>
                                    <?php if ($key !== 'orden'): ?>
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
                        <p class="text-muted">Intenta ajustar los filtros o buscar algo diferente</p>
                        <a href="<?php echo SITE_URL; ?>/productos" class="btn btn-primary">Ver Todos los Productos</a>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($products as $product): ?>
                            <div class="col-md-6 col-xl-4">
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
                                        <?php if ($product['category_name']): ?>
                                            <div class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></div>
                                        <?php endif; ?>
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
                        <div class="pagination-container">
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
        </div>
    </div>
    
    <!-- Footer -->
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
    
    <script>
        function setPriceRange(min, max) {
            document.querySelector('input[name="precio_min"]').value = min;
            document.querySelector('input[name="precio_max"]').value = max;
        }       
        // Auto-submit filtros en algunos casos
        document.querySelectorAll('input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.name === 'categoria' || this.name === 'tipo') {
                    document.getElementById('filterForm').submit();
                }
            });
        });
    </script>
</body>
</html>