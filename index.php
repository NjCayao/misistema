<?php
// index.php - Página principal del frontend
require_once 'config/database.php';
require_once 'config/constants.php';
require_once 'config/functions.php';
require_once 'config/settings.php';

// Verificar modo mantenimiento
if (Settings::get('maintenance_mode', '0') == '1' && !isAdmin()) {
    include 'maintenance.php';
    exit;
}

// Obtener configuraciones del sitio
$siteName = Settings::get('site_name', 'MiSistema');
$siteDescription = Settings::get('site_description', 'Plataforma de venta de software');
$siteLogo = Settings::get('site_logo', '');
$siteFavicon = Settings::get('site_favicon', '');

// Obtener productos destacados
try {
    $db = Database::getInstance()->getConnection();
    
    // Productos destacados
    $stmt = $db->query("
        SELECT p.*, c.name as category_name
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.is_active = 1 AND p.is_featured = 1 
        ORDER BY p.created_at DESC 
        LIMIT 6
    ");
    $featuredProducts = $stmt->fetchAll();
    
    // Categorías activas
    $stmt = $db->query("
        SELECT c.*, COUNT(p.id) as product_count
        FROM categories c 
        LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
        WHERE c.is_active = 1 
        GROUP BY c.id 
        ORDER BY c.sort_order ASC, c.name ASC
    ");
    $categories = $stmt->fetchAll();
    
    // Productos recientes
    $stmt = $db->query("
        SELECT p.*, c.name as category_name
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.is_active = 1 
        ORDER BY p.created_at DESC 
        LIMIT 8
    ");
    $recentProducts = $stmt->fetchAll();
    
} catch (Exception $e) {
    $featuredProducts = [];
    $categories = [];
    $recentProducts = [];
    logError("Error en homepage: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteName); ?> - <?php echo htmlspecialchars($siteDescription); ?></title>
    
    <!-- Meta Tags -->
    <meta name="description" content="<?php echo htmlspecialchars($siteDescription); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars(Settings::get('site_keywords', 'software, sistemas, php')); ?>">
    <meta name="author" content="<?php echo htmlspecialchars($siteName); ?>">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo htmlspecialchars($siteName); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($siteDescription); ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo SITE_URL; ?>">
    
    <!-- Favicon -->
    <?php if ($siteFavicon): ?>
        <link rel="icon" type="image/png" href="<?php echo UPLOADS_URL; ?>/logos/<?php echo $siteFavicon; ?>">
    <?php endif; ?>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <?php include 'includes/header.php'; ?>
    
    <!-- Main Content -->
    <main>
        <!-- Hero Section -->
        <section class="hero-section">
            <div class="container">
                <div class="row align-items-center min-vh-75">
                    <div class="col-lg-6">
                        <div class="hero-content">
                            <h1 class="hero-title">
                                Bienvenido a <span class="text-primary"><?php echo htmlspecialchars($siteName); ?></span>
                            </h1>
                            <p class="hero-subtitle">
                                <?php echo htmlspecialchars($siteDescription); ?>. 
                                Encuentra los mejores sistemas y componentes para tu negocio.
                            </p>
                            <div class="hero-actions">
                                <a href="#productos-destacados" class="btn btn-primary btn-lg me-3">
                                    <i class="fas fa-rocket me-2"></i>Explorar Productos
                                </a>
                                <a href="#categorias" class="btn btn-outline-primary btn-lg">
                                    <i class="fas fa-th-large me-2"></i>Ver Categorías
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="hero-image">
                            <div class="floating-card">
                                <i class="fas fa-code fa-3x text-primary mb-3"></i>
                                <h5>Desarrollo Profesional</h5>
                                <p>Código limpio y optimizado</p>
                            </div>
                            <div class="floating-card delay-1">
                                <i class="fas fa-shield-alt fa-3x text-success mb-3"></i>
                                <h5>100% Seguro</h5>
                                <p>Sistemas probados y confiables</p>
                            </div>
                            <div class="floating-card delay-2">
                                <i class="fas fa-support fa-3x text-info mb-3"></i>
                                <h5>Soporte 24/7</h5>
                                <p>Ayuda cuando la necesites</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Categorías -->
        <section id="categorias" class="py-5 bg-light">
            <div class="container">
                <div class="row">
                    <div class="col-12 text-center mb-5">
                        <h2 class="section-title">Explora por Categorías</h2>
                        <p class="section-subtitle">Encuentra exactamente lo que necesitas</p>
                    </div>
                </div>
                <div class="row g-4">
                    <?php foreach ($categories as $category): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="category-card">
                                <div class="category-icon">
                                    <?php if ($category['image']): ?>
                                        <img src="<?php echo UPLOADS_URL; ?>/categories/<?php echo $category['image']; ?>" alt="<?php echo htmlspecialchars($category['name']); ?>">
                                    <?php else: ?>
                                        <i class="fas fa-folder fa-3x"></i>
                                    <?php endif; ?>
                                </div>
                                <h5 class="category-title"><?php echo htmlspecialchars($category['name']); ?></h5>
                                <p class="category-description"><?php echo htmlspecialchars($category['description']); ?></p>
                                <div class="category-stats">
                                    <span class="product-count"><?php echo $category['product_count']; ?> productos</span>
                                </div>
                                <a href="/categoria/<?php echo $category['slug']; ?>" class="btn btn-outline-primary">
                                    Ver Productos <i class="fas fa-arrow-right ms-2"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        
        <!-- Productos Destacados -->
        <section id="productos-destacados" class="py-5">
            <div class="container">
                <div class="row">
                    <div class="col-12 text-center mb-5">
                        <h2 class="section-title">Productos Destacados</h2>
                        <p class="section-subtitle">Los más populares de nuestra plataforma</p>
                    </div>
                </div>
                <div class="row g-4">
                    <?php foreach ($featuredProducts as $product): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="product-card">
                                <div class="product-image">
                                    <?php if ($product['image']): ?>
                                        <img src="<?php echo UPLOADS_URL; ?>/products/<?php echo $product['image']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                    <?php else: ?>
                                        <div class="no-image">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="product-overlay">
                                        <a href="/producto/<?php echo $product['slug']; ?>" class="btn btn-primary">Ver Detalles</a>
                                    </div>
                                    <?php if ($product['is_free']): ?>
                                        <span class="product-badge free">GRATIS</span>
                                    <?php endif; ?>
                                </div>
                                <div class="product-info">
                                    <div class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></div>
                                    <h5 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                    <p class="product-description"><?php echo htmlspecialchars($product['short_description']); ?></p>
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
                <div class="text-center mt-5">
                    <a href="/productos" class="btn btn-primary btn-lg">
                        Ver Todos los Productos <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                </div>
            </div>
        </section>
        
        <!-- Stats Section -->
        <section class="py-5 bg-primary text-white">
            <div class="container">
                <div class="row g-4 text-center">
                    <div class="col-md-3 col-6">
                        <div class="stat-item">
                            <i class="fas fa-download fa-3x mb-3"></i>
                            <h3 class="stat-number">10K+</h3>
                            <p class="stat-label">Descargas</p>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="stat-item">
                            <i class="fas fa-users fa-3x mb-3"></i>
                            <h3 class="stat-number">5K+</h3>
                            <p class="stat-label">Usuarios</p>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="stat-item">
                            <i class="fas fa-box fa-3x mb-3"></i>
                            <h3 class="stat-number"><?php echo count($recentProducts); ?>+</h3>
                            <p class="stat-label">Productos</p>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="stat-item">
                            <i class="fas fa-star fa-3x mb-3"></i>
                            <h3 class="stat-number">4.9</h3>
                            <p class="stat-label">Rating</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Productos Recientes -->
        <section class="py-5">
            <div class="container">
                <div class="row">
                    <div class="col-12 text-center mb-5">
                        <h2 class="section-title">Productos Recientes</h2>
                        <p class="section-subtitle">Los últimos agregados a nuestra plataforma</p>
                    </div>
                </div>
                <div class="row g-4">
                    <?php foreach (array_slice($recentProducts, 0, 4) as $product): ?>
                        <div class="col-md-6 col-lg-3">
                            <div class="product-card compact">
                                <div class="product-image">
                                    <?php if ($product['image']): ?>
                                        <img src="<?php echo UPLOADS_URL; ?>/products/<?php echo $product['image']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                    <?php else: ?>
                                        <div class="no-image">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="product-info">
                                    <h6 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h6>
                                    <div class="product-price">
                                        <?php if ($product['is_free']): ?>
                                            <span class="price-free">GRATIS</span>
                                        <?php else: ?>
                                            <span class="price"><?php echo formatPrice($product['price']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </main>
    
    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
    
    <script>
        // Funciones básicas para interactividad
        function addToCart(productId) {
            // Implementar más adelante
            console.log('Agregar al carrito:', productId);
        }
        
        function addToWishlist(productId) {
            // Implementar más adelante
            console.log('Agregar a favoritos:', productId);
        }
        
        // Animaciones de scroll
        window.addEventListener('scroll', function() {
            const header = document.querySelector('header');
            if (window.scrollY > 100) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });
    </script>
</body>
</html>