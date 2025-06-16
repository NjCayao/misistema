<?php
// pages/search.php - Sistema de búsqueda de productos CORREGIDO
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/settings.php';

// Verificar modo mantenimiento
if (getSetting('maintenance_mode', '0') == '1' && !isAdmin()) {
    include '../maintenance.php';
    exit;
}

// Parámetros de búsqueda
$query = $_GET['q'] ?? '';
$category = $_GET['categoria'] ?? '';
$type = $_GET['tipo'] ?? '';
$priceMin = floatval($_GET['precio_min'] ?? 0);
$priceMax = floatval($_GET['precio_max'] ?? 1000);
$sort = $_GET['orden'] ?? 'relevancia';
$page = intval($_GET['pagina'] ?? 1);
$perPage = 12;
$offset = ($page - 1) * $perPage;

$products = [];
$totalProducts = 0;
$totalPages = 0;
$categories = [];
$suggestions = [];

// Solo buscar si hay una consulta
if (!empty($query) && strlen(trim($query)) >= 2) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Limpiar y preparar términos de búsqueda
        $searchTerms = explode(' ', trim($query));
        $searchTerms = array_filter($searchTerms, function($term) {
            return strlen($term) >= 2;
        });
        
        if (!empty($searchTerms)) {
            // Construir condiciones de búsqueda
            $whereConditions = ["p.is_active = 1"];
            $params = [];
            
            // Búsqueda por términos (en nombre, descripción corta y completa)
            $searchConditions = [];
            foreach ($searchTerms as $term) {
                $searchConditions[] = "(p.name LIKE ? OR p.short_description LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
                $likeTerm = "%$term%";
                $params[] = $likeTerm;
                $params[] = $likeTerm;
                $params[] = $likeTerm;
                $params[] = $likeTerm;
            }
            $whereConditions[] = "(" . implode(" OR ", $searchConditions) . ")";
            
            // Filtro por categoría
            if ($category) {
                $whereConditions[] = "c.slug = ?";
                $params[] = $category;
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
            
            // Ordenamiento con relevancia
            $orderBy = match($sort) {
                'nombre' => 'p.name ASC',
                'precio_asc' => 'p.price ASC',
                'precio_desc' => 'p.price DESC',
                'recientes' => 'p.created_at DESC',
                'populares' => 'COALESCE(p.download_count, 0) DESC, p.created_at DESC',
                default => 'relevancia_score DESC, p.created_at DESC'
            };
            
            // Query principal con scoring de relevancia
            $relevanceQuery = "
                SELECT p.*, c.name as category_name, c.slug as category_slug,
                       (SELECT COUNT(*) FROM product_versions pv WHERE pv.product_id = p.id) as version_count,
                       (
                           CASE WHEN p.name LIKE ? THEN 10 ELSE 0 END +
                           CASE WHEN p.short_description LIKE ? THEN 5 ELSE 0 END +
                           CASE WHEN p.description LIKE ? THEN 3 ELSE 0 END +
                           CASE WHEN c.name LIKE ? THEN 2 ELSE 0 END +
                           (COALESCE(p.download_count, 0) / 100) +
                           CASE WHEN p.is_featured = 1 THEN 5 ELSE 0 END
                       ) as relevancia_score
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE $whereClause
                ORDER BY $orderBy
                LIMIT $perPage OFFSET $offset
            ";
            
            // Agregar parámetros para el scoring
            $firstTerm = "%{$searchTerms[0]}%";
            $scoringParams = [$firstTerm, $firstTerm, $firstTerm, $firstTerm];
            $allParams = array_merge($scoringParams, $params);
            
            $stmt = $db->prepare($relevanceQuery);
            $stmt->execute($allParams);
            $products = $stmt->fetchAll();
            
            // Contar total de resultados
            $countQuery = "
                SELECT COUNT(*) as total
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE $whereClause
            ";
            $countStmt = $db->prepare($countQuery);
            $countStmt->execute($params);
            $totalProducts = $countStmt->fetch()['total'];
            $totalPages = ceil($totalProducts / $perPage);
            
            // Obtener categorías con resultados
            $catQuery = "
                SELECT c.name, c.slug, COUNT(p.id) as product_count
                FROM categories c 
                INNER JOIN products p ON c.id = p.category_id 
                WHERE p.is_active = 1 AND (" . implode(" OR ", $searchConditions) . ")
                GROUP BY c.id 
                ORDER BY product_count DESC, c.name ASC
                LIMIT 10
            ";
            $catParams = [];
            foreach ($searchTerms as $term) {
                $likeTerm = "%$term%";
                $catParams[] = $likeTerm;
                $catParams[] = $likeTerm;
                $catParams[] = $likeTerm;
                $catParams[] = $likeTerm;
            }
            $catStmt = $db->prepare($catQuery);
            $catStmt->execute($catParams);
            $categories = $catStmt->fetchAll();
            
            // Generar sugerencias si no hay resultados
            if (empty($products)) {
                $suggestions = generateSearchSuggestions($db, $query);
            }
        }
        
    } catch (Exception $e) {
        logError("Error en búsqueda: " . $e->getMessage());
        $products = [];
        $totalProducts = 0;
    }
}

// Función para generar sugerencias
function generateSearchSuggestions($db, $query) {
    $suggestions = [];
    
    try {
        // Buscar productos similares
        $stmt = $db->prepare("
            SELECT DISTINCT p.name 
            FROM products p 
            WHERE p.is_active = 1 AND p.name LIKE ? 
            ORDER BY COALESCE(p.download_count, 0) DESC 
            LIMIT 5
        ");
        $stmt->execute(["%$query%"]);
        $similar = $stmt->fetchAll();
        
        foreach ($similar as $item) {
            $suggestions[] = $item['name'];
        }
        
        // Agregar categorías populares si no hay sugerencias
        if (empty($suggestions)) {
            $stmt = $db->query("
                SELECT c.name 
                FROM categories c 
                INNER JOIN products p ON c.id = p.category_id 
                WHERE c.is_active = 1 AND p.is_active = 1 
                GROUP BY c.id 
                ORDER BY COUNT(p.id) DESC 
                LIMIT 3
            ");
            $popularCategories = $stmt->fetchAll();
            
            foreach ($popularCategories as $cat) {
                $suggestions[] = $cat['name'];
            }
        }
    } catch (Exception $e) {
        // Sugerencias por defecto
        $suggestions = ['Sistema de Ventas', 'Inventario', 'CRM', 'E-commerce'];
    }
    
    return $suggestions;
}

$siteName = getSetting('site_name', 'MiSistema');
$pageTitle = $query ? "Búsqueda: $query" : "Buscar Productos";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo htmlspecialchars($siteName); ?></title>
    
    <meta name="description" content="Busca productos de software en nuestra plataforma">
    <meta name="robots" content="noindex, follow">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    
    <style>
        .search-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 0;
        }
        
        .search-box-large {
            position: relative;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .search-box-large .form-control {
            height: 60px;
            font-size: 1.2rem;
            border-radius: 30px;
            padding: 0 60px 0 25px;
            border: none;
            box-shadow: 0 4px 15px rgb(0 0 0 / 57%);
        }
        
        .search-box-large .btn {
            position: relative;
            right: 5px;
            top: 0px;
            bottom: 5px;
            width: 50px;
            border-radius: 25px;
        }
        
        .search-stats {
            background: rgba(255,255,255,0.1);
            padding: 15px 20px;
            border-radius: 10px;
            margin-top: 20px;
            backdrop-filter: blur(10px);
        }
        
        .filter-sidebar {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            position: sticky;
            top: 20px;
        }
        
        .filter-section {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .filter-section:last-child {
            border-bottom: none;
        }
        
        .category-filter {
            max-height: 200px;
            overflow-y: auto;
        }
        
        .no-results {
            text-align: center;
            padding: 60px 20px;
        }
        
        .suggestion-tags {
            margin-top: 20px;
        }
        
        .suggestion-tag {
            display: inline-block;
            padding: 8px 16px;
            margin: 5px;
            background: #e9ecef;
            color: #495057;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .suggestion-tag:hover {
            background: #007bff;
            color: white;
        }
        
        .search-tips {
            background: #e7f3ff;
            border: 1px solid #b8daff;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
        }
        
        .highlight {
            background-color: #fff3cd;
            padding: 2px 4px;
            border-radius: 3px;
        }
        
        .product-meta {
            font-size: 0.875rem;
            color: #6c757d;
            margin-bottom: 1rem;
        }
        
        .product-meta i {
            margin-right: 0.25rem;
        }
        
        .product-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.3s ease;
            background: white;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .product-image {
            position: relative;
            height: 200px;
            overflow: hidden;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .no-image {
            width: 100%;
            height: 100%;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 3rem;
        }
        
        .product-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .product-card:hover .product-overlay {
            opacity: 1;
        }
        
        .product-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        .product-badge.free {
            background: #28a745;
            color: white;
        }
        
        .product-info {
            padding: 1.5rem;
        }
        
        .product-category {
            font-size: 0.8rem;
            color: #6c757d;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }
        
        .product-title {
            font-size: 1.1rem;
            margin-bottom: 1rem;
            line-height: 1.3;
        }
        
        .product-description {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 1rem;
            line-height: 1.4;
        }
        
        .product-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
        }
        
        .price-free {
            color: #28a745;
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .price {
            color: #007bff;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .product-actions {
            display: flex;
            gap: 0.5rem;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <!-- Search Header -->
    <div class="search-header">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <h1 class="display-5 mb-4">
                        <?php if ($query): ?>
                            Resultados para "<?php echo htmlspecialchars($query); ?>"
                        <?php else: ?>
                            Buscar Productos
                        <?php endif; ?>
                    </h1>
                    
                    <!-- Caja de Búsqueda Grande -->
                    <form method="GET" class="search-box-large">
                        <div class="input-group">
                            <input type="text" class="form-control" name="q" 
                                   value="<?php echo htmlspecialchars($query); ?>" 
                                   placeholder="¿Qué estás buscando?" 
                                   autocomplete="off" required>
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                    
                    <!-- Estadísticas de Búsqueda -->
                    <?php if ($query && $totalProducts > 0): ?>
                        <div class="search-stats">
                            <i class="fas fa-info-circle me-2"></i>
                            Se encontraron <strong><?php echo number_format($totalProducts); ?></strong> resultados
                            <?php if ($totalProducts != count($products)): ?>
                                (mostrando <?php echo count($products); ?>)
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="container my-5">
        <?php if ($query): ?>
            <div class="row">
                <!-- Sidebar Filtros -->
                <div class="col-lg-3">
                    <div class="filter-sidebar">
                        <h5 class="mb-3">Refinar Búsqueda</h5>
                        
                        <form method="GET" id="filterForm">
                            <input type="hidden" name="q" value="<?php echo htmlspecialchars($query); ?>">
                            
                            <!-- Categorías -->
                            <?php if (!empty($categories)): ?>
                                <div class="filter-section">
                                    <h6 class="mb-3">Categorías</h6>
                                    <div class="category-filter">
                                        <?php foreach ($categories as $cat): ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="categoria" 
                                                       value="<?php echo $cat['slug']; ?>" id="cat_<?php echo $cat['slug']; ?>"
                                                       <?php echo $category === $cat['slug'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="cat_<?php echo $cat['slug']; ?>">
                                                    <?php echo htmlspecialchars($cat['name']); ?>
                                                    <span class="text-muted">(<?php echo $cat['product_count']; ?>)</span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Tipo -->
                            <div class="filter-section">
                                <h6 class="mb-3">Tipo</h6>
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
                            
                            <!-- Precio -->
                            <div class="filter-section">
                                <h6 class="mb-3">Precio</h6>
                                <div class="row">
                                    <div class="col-6">
                                        <input type="number" class="form-control form-control-sm mb-2" name="precio_min" 
                                               value="<?php echo $priceMin > 0 ? $priceMin : ''; ?>" 
                                               placeholder="Mín" min="0" step="0.01">
                                    </div>
                                    <div class="col-6">
                                        <input type="number" class="form-control form-control-sm mb-2" name="precio_max" 
                                               value="<?php echo $priceMax < 1000 ? $priceMax : ''; ?>" 
                                               placeholder="Máx" min="0" step="0.01">
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 mb-2">
                                <i class="fas fa-filter"></i> Aplicar Filtros
                            </button>
                            <a href="<?php echo SITE_URL; ?>/buscar?q=<?php echo urlencode($query); ?>" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-times"></i> Limpiar Filtros
                            </a>
                        </form>
                    </div>
                </div>
                
                <!-- Resultados -->
                <div class="col-lg-9">
                    <!-- Ordenamiento -->
                    <?php if (!empty($products)): ?>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h5 class="mb-0">Resultados de Búsqueda</h5>
                                <small class="text-muted">
                                    Página <?php echo $page; ?> de <?php echo $totalPages; ?>
                                </small>
                            </div>
                            <form method="GET" class="d-flex align-items-center">
                                <input type="hidden" name="q" value="<?php echo htmlspecialchars($query); ?>">
                                <?php foreach ($_GET as $key => $value): ?>
                                    <?php if ($key !== 'orden' && $key !== 'q'): ?>
                                        <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                
                                <label class="me-2">Ordenar:</label>
                                <select name="orden" class="form-select" style="width: auto;" onchange="this.form.submit()">
                                    <option value="relevancia" <?php echo $sort === 'relevancia' ? 'selected' : ''; ?>>Relevancia</option>
                                    <option value="nombre" <?php echo $sort === 'nombre' ? 'selected' : ''; ?>>Nombre A-Z</option>
                                    <option value="precio_asc" <?php echo $sort === 'precio_asc' ? 'selected' : ''; ?>>Precio: Menor a Mayor</option>
                                    <option value="precio_desc" <?php echo $sort === 'precio_desc' ? 'selected' : ''; ?>>Precio: Mayor a Menor</option>
                                    <option value="recientes" <?php echo $sort === 'recientes' ? 'selected' : ''; ?>>Más Recientes</option>
                                    <option value="populares" <?php echo $sort === 'populares' ? 'selected' : ''; ?>>Más Populares</option>
                                </select>
                            </form>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Grid de Productos -->
                    <?php if (empty($products)): ?>
                        <div class="no-results">
                            <i class="fas fa-search fa-4x text-muted mb-4"></i>
                            <h3 class="text-muted">
                                <?php if ($query): ?>
                                    No se encontraron resultados
                                <?php else: ?>
                                    Ingresa un término de búsqueda
                                <?php endif; ?>
                            </h3>
                            
                            <?php if ($query): ?>
                                <p class="text-muted mb-4">
                                    No encontramos productos que coincidan con "<?php echo htmlspecialchars($query); ?>"
                                </p>
                                
                                <?php if (!empty($suggestions)): ?>
                                    <div class="suggestion-tags">
                                        <p class="mb-3">Quizás te interese buscar:</p>
                                        <?php foreach ($suggestions as $suggestion): ?>
                                            <a href="<?php echo SITE_URL; ?>/buscar?q=<?php echo urlencode($suggestion); ?>" class="suggestion-tag">
                                                <?php echo htmlspecialchars($suggestion); ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="text-muted">Usa la caja de búsqueda para encontrar productos</p>
                            <?php endif; ?>
                            
                            <a href="<?php echo SITE_URL; ?>/productos" class="btn btn-primary mt-3">
                                <i class="fas fa-arrow-left me-2"></i>Ver Todos los Productos
                            </a>
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
                                        </div>
                                        <div class="product-info">
                                            <?php if ($product['category_name']): ?>
                                                <div class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></div>
                                            <?php endif; ?>
                                            <h5 class="product-title">
                                                <a href="<?php echo SITE_URL; ?>/producto/<?php echo $product['slug']; ?>" class="text-decoration-none text-dark">
                                                    <?php
                                                    // Resaltar términos de búsqueda en el título
                                                    $highlightedName = htmlspecialchars($product['name']);
                                                    foreach (explode(' ', $query) as $term) {
                                                        if (strlen($term) >= 2) {
                                                            $highlightedName = preg_replace('/(' . preg_quote($term, '/') . ')/i', '<span class="highlight">$1</span>', $highlightedName);
                                                        }
                                                    }
                                                    echo $highlightedName;
                                                    ?>
                                                </a>
                                            </h5>
                                            <p class="product-description">
                                                <?php
                                                // Resaltar términos en la descripción
                                                $highlightedDesc = htmlspecialchars($product['short_description']);
                                                foreach (explode(' ', $query) as $term) {
                                                    if (strlen($term) >= 2) {
                                                        $highlightedDesc = preg_replace('/(' . preg_quote($term, '/') . ')/i', '<span class="highlight">$1</span>', $highlightedDesc);
                                                    }
                                                }
                                                echo $highlightedDesc;
                                                ?>
                                            </p>
                                            
                                            <div class="product-meta">
                                                <small class="text-muted">
                                                    <i class="fas fa-code-branch"></i> <?php echo $product['version_count']; ?> versiones
                                                    <span class="ms-2">
                                                        <i class="fas fa-download"></i> <?php echo number_format($product['download_count'] ?? 0); ?>
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
                                                    <button class="btn btn-sm btn-outline-primary" onclick="addToCart(<?php echo $product['id']; ?>)">
                                                        <i class="fas fa-cart-plus"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-secondary" onclick="addToWishlist(<?php echo $product['id']; ?>)">
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
                                <nav aria-label="Paginación de resultados">
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
        <?php else: ?>
            <!-- Página inicial de búsqueda -->
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="search-tips">
                        <h5><i class="fas fa-lightbulb me-2"></i>Consejos de Búsqueda</h5>
                        <ul class="mb-0">
                            <li>Usa palabras clave específicas como "sistema de ventas" o "inventario"</li>
                            <li>Combina términos para búsquedas más precisas: "CRM PHP MySQL"</li>
                            <li>Busca por categoría para explorar productos relacionados</li>
                            <li>Usa filtros para refinar tus resultados por precio o tipo</li>
                        </ul>
                    </div>
                    
                    <!-- Búsquedas Populares -->
                    <div class="mt-5">
                        <h5 class="mb-3">Búsquedas Populares</h5>
                        <div class="suggestion-tags">
                            <a href="<?php echo SITE_URL; ?>/buscar?q=sistema+de+ventas" class="suggestion-tag">Sistema de Ventas</a>
                            <a href="<?php echo SITE_URL; ?>/buscar?q=inventario" class="suggestion-tag">Inventario</a>
                            <a href="<?php echo SITE_URL; ?>/buscar?q=crm" class="suggestion-tag">CRM</a>
                            <a href="<?php echo SITE_URL; ?>/buscar?q=ecommerce" class="suggestion-tag">E-commerce</a>
                            <a href="<?php echo SITE_URL; ?>/buscar?q=pos" class="suggestion-tag">POS</a>
                            <a href="<?php echo SITE_URL; ?>/buscar?q=contabilidad" class="suggestion-tag">Contabilidad</a>
                            <a href="<?php echo SITE_URL; ?>/buscar?q=php" class="suggestion-tag">PHP</a>
                            <a href="<?php echo SITE_URL; ?>/buscar?q=mysql" class="suggestion-tag">MySQL</a>
                        </div>
                    </div>
                    
                    <!-- Categorías Populares -->
                    <div class="mt-5">
                        <h5 class="mb-3">Explorar por Categorías</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <a href="<?php echo SITE_URL; ?>/categoria/sistemas-php" class="d-block p-3 border rounded text-decoration-none">
                                    <h6 class="text-primary">Sistemas PHP</h6>
                                    <small class="text-muted">Sistemas completos en PHP</small>
                                </a>
                            </div>
                            <div class="col-md-6 mb-3">
                                <a href="<?php echo SITE_URL; ?>/categoria/zona-codigo" class="d-block p-3 border rounded text-decoration-none">
                                    <h6 class="text-primary">Zona de Código</h6>
                                    <small class="text-muted">Componentes y códigos</small>
                                </a>
                            </div>
                        </div>
                    </div>
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
            // TODO: Implementar más adelante
            alert('Función de carrito próximamente disponible');
        }
        
        function addToWishlist(productId) {
            console.log('Agregar a favoritos:', productId);
            // TODO: Implementar más adelante
            alert('Función de favoritos próximamente disponible');
        }
        
        // Auto-submit filtros
        document.querySelectorAll('input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.name === 'categoria' || this.name === 'tipo') {
                    document.getElementById('filterForm').submit();
                }
            });
        });
        
        // Prevenir envío de formulario vacío
        document.querySelector('form.search-box-large').addEventListener('submit', function(e) {
            const input = this.querySelector('input[name="q"]');
            if (input.value.trim().length < 2) {
                e.preventDefault();
                alert('Ingresa al menos 2 caracteres para buscar');
                input.focus();
            }
        });
        
        // Limpiar destacados al cambiar búsqueda
        const searchInput = document.querySelector('input[name="q"]');
        if (searchInput) {
            searchInput.addEventListener('focus', function() {
                this.select();
            });
        }
    </script>
</body>
</html>