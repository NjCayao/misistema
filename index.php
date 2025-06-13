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

<?php
// Obtener TODOS los banners activos para el carousel
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("
        SELECT * FROM banners 
        WHERE position = 'home_slider' AND is_active = 1 
        ORDER BY sort_order ASC, created_at DESC
    ");
    $sliderBanners = $stmt->fetchAll();
} catch (Exception $e) {
    $sliderBanners = [];
}

try {
    $db = Database::getInstance()->getConnection();

    // Banners promocionales 
    $stmt = $db->query("
        SELECT * FROM banners 
        WHERE position = 'promotion' AND is_active = 1 
        ORDER BY sort_order ASC
    ");
    $promotionBanners = $stmt->fetchAll();

    // Hero section banners
    $stmt = $db->query("
        SELECT * FROM banners 
        WHERE position = 'home_hero' AND is_active = 1 
        ORDER BY sort_order ASC
    ");
    $heroBanners = $stmt->fetchAll();

    // Sidebar banners
    $stmt = $db->query("
        SELECT * FROM banners 
        WHERE position = 'sidebar' AND is_active = 1 
        ORDER BY sort_order ASC
    ");
    $sidebarBanners = $stmt->fetchAll();
} catch (Exception $e) {
    $promotionBanners = [];
    $heroBanners = [];
    $sidebarBanners = [];
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
        <!-- Hero Section con Carousel Completo -->
        <section class="hero-carousel-section">
            <?php if (!empty($sliderBanners)): ?>
                <div id="heroCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="3000">
                    <!-- Indicadores -->
                    <div class="carousel-indicators">
                        <?php foreach ($sliderBanners as $index => $banner): ?>
                            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?php echo $index; ?>"
                                <?php echo $index === 0 ? 'class="active" aria-current="true"' : ''; ?>
                                aria-label="Slide <?php echo $index + 1; ?>"></button>
                        <?php endforeach; ?>
                    </div>

                    <!-- Slides del Carousel -->
                    <div class="carousel-inner">
                        <?php foreach ($sliderBanners as $index => $banner): ?>
                            <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                <div class="hero-slide">
                                    <!-- Imagen de fondo -->
                                    <?php if ($banner['image']): ?>
                                        <div class="hero-background" style="background-image: url('<?php echo UPLOADS_URL; ?>/banners/<?php echo htmlspecialchars($banner['image']); ?>');"></div>
                                    <?php endif; ?>

                                    <!-- Overlay -->
                                    <div class="hero-overlay"></div>

                                    <!-- Contenido -->
                                    <div class="container">
                                        <div class="row align-items-center" style="min-height: 450px;">
                                            <div class="col-lg-6">
                                                <div class="hero-content">
                                                    <h1 class="hero-title animate-slide-up">
                                                        <?php echo htmlspecialchars($banner['title']); ?>
                                                    </h1>
                                                    <?php if ($banner['subtitle']): ?>
                                                        <h2 class="hero-subtitle animate-slide-up delay-1">
                                                            <?php echo htmlspecialchars($banner['subtitle']); ?>
                                                        </h2>
                                                    <?php endif; ?>
                                                    <?php if ($banner['description']): ?>
                                                        <p class="hero-description animate-slide-up delay-2">
                                                            <?php echo htmlspecialchars($banner['description']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    <div class="hero-actions animate-slide-up delay-3">
                                                        <?php if ($banner['button_text'] && $banner['button_url']): ?>
                                                            <a href="<?php echo htmlspecialchars($banner['button_url']); ?>" class="btn btn-primary btn-lg me-3">
                                                                <i class="fas fa-rocket me-2"></i><?php echo htmlspecialchars($banner['button_text']); ?>
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="#categorias" class="btn btn-outline-light btn-lg">
                                                            <i class="fas fa-th-large me-2"></i>Ver Categorías
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-6">
                                                <div class="hero-image">
                                                    <!-- Cuadros flotantes (mantienen su animación) -->
                                                    <div class="floating-card animate-float">
                                                        <i class="fas fa-code fa-3x text-primary mb-3"></i>
                                                        <h5>Desarrollo Profesional</h5>
                                                        <p>Código limpio y optimizado</p>
                                                    </div>
                                                    <div class="floating-card animate-float delay-1">
                                                        <i class="fas fa-shield-alt fa-3x text-success mb-3"></i>
                                                        <h5>100% Seguro</h5>
                                                        <p>Sistemas probados y confiables</p>
                                                    </div>
                                                    <div class="floating-card animate-float delay-2">
                                                        <i class="fas fa-support fa-3x text-info mb-3"></i>
                                                        <h5>Soporte 24/7</h5>
                                                        <p>Ayuda cuando la necesites</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Controles de navegación -->
                    <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
                        <div class="carousel-control-icon">
                            <i class="fas fa-chevron-left"></i>
                        </div>
                        <span class="visually-hidden">Anterior</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
                        <div class="carousel-control-icon">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                        <span class="visually-hidden">Siguiente</span>
                    </button>
                </div>
            <?php else: ?>
                <!-- Fallback si no hay banners -->
                <div class="hero-slide">
                    <div class="hero-overlay"></div>
                    <div class="container">
                        <div class="row align-items-center" style="min-height: 450px;">
                            <div class="col-lg-6">
                                <div class="hero-content">
                                    <h1 class="hero-title">
                                        Bienvenido a <span class="text-primary"><?php echo htmlspecialchars($siteName); ?></span>
                                    </h1>
                                    <p class="hero-description">
                                        <?php echo htmlspecialchars($siteDescription); ?>.
                                        Encuentra los mejores sistemas y componentes para tu negocio.
                                    </p>
                                    <div class="hero-actions">
                                        <a href="#productos-destacados" class="btn btn-primary btn-lg me-3">
                                            <i class="fas fa-rocket me-2"></i>Explorar Productos
                                        </a>
                                        <a href="#categorias" class="btn btn-outline-light btn-lg">
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
                </div>
            <?php endif; ?>
        </section>


        <!-- 1. PROMOCIONES - Después del slider principal -->
        <?php if (!empty($promotionBanners)): ?>
            <section class="promotion-section py-5">
                <div class="container">
                    <div class="row g-4">
                        <?php
                        $colClass = count($promotionBanners) == 1 ? 'col-12' : (count($promotionBanners) == 2 ? 'col-lg-6' : (count($promotionBanners) == 3 ? 'col-lg-4' : 'col-lg-3'));
                        ?>
                        <?php foreach ($promotionBanners as $index => $banner): ?>
                            <div class="<?php echo $colClass; ?>">
                                <div class="promo-card" data-aos="fade-up" data-aos-delay="<?php echo $index * 200; ?>">
                                    <div class="promo-glow"></div>
                                    <div class="promo-image-container">
                                        <?php if ($banner['image']): ?>
                                            <img src="<?php echo UPLOADS_URL; ?>/banners/<?php echo htmlspecialchars($banner['image']); ?>"
                                                alt="<?php echo htmlspecialchars($banner['title']); ?>" class="promo-image">
                                        <?php endif; ?>
                                        <div class="promo-overlay"></div>
                                    </div>
                                    <div class="promo-content">
                                        <div class="promo-icon">
                                            <i class="fas fa-gem"></i>
                                        </div>
                                        <h3 class="promo-title"><?php echo htmlspecialchars($banner['title']); ?></h3>
                                        <?php if ($banner['subtitle']): ?>
                                            <p class="promo-subtitle"><?php echo htmlspecialchars($banner['subtitle']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($banner['description']): ?>
                                            <p class="promo-description"><?php echo htmlspecialchars($banner['description']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($banner['button_text'] && $banner['button_url']): ?>
                                            <a href="<?php echo htmlspecialchars($banner['button_url']); ?>" class="btn-luxury">
                                                <span class="btn-text"><?php echo htmlspecialchars($banner['button_text']); ?></span>
                                                <span class="btn-icon"><i class="fas fa-magic"></i></span>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- 2. HERO SECTION - Tarjetas elegantes -->
        <?php if (!empty($heroBanners)): ?>
            <section class="hero-cards-section py-5 bg-gradient-luxury">
                <div class="container">
                    <div class="row">
                        <div class="col-12 text-center mb-5">
                            <h2 class="luxury-title" data-aos="zoom-in">Experiencia Premium</h2>
                            <div class="luxury-divider"></div>
                        </div>
                    </div>
                    <div class="row g-4">
                        <?php foreach ($heroBanners as $index => $banner): ?>
                            <div class="col-lg-<?php echo count($heroBanners) <= 2 ? '4' : '3'; ?>">
                                <div class="hero-luxury-card" data-aos="flip-right" data-aos-delay="<?php echo $index * 300; ?>">
                                    <div class="hero-card-inner">
                                        <div class="hero-card-glow"></div>
                                        <div class="hero-card-content">
                                            <div class="hero-card-icon">
                                                <i class="fas fa-crown"></i>
                                            </div>
                                            <h4 class="hero-card-title"><?php echo htmlspecialchars($banner['title']); ?></h4>
                                            <?php if ($banner['subtitle']): ?>
                                                <p class="hero-card-subtitle"><?php echo htmlspecialchars($banner['subtitle']); ?></p>
                                            <?php endif; ?>
                                            <?php if ($banner['description']): ?>
                                                <p class="hero-card-description"><?php echo htmlspecialchars($banner['description']); ?></p>
                                            <?php endif; ?>
                                            <?php if ($banner['button_text'] && $banner['button_url']): ?>
                                                <a href="<?php echo htmlspecialchars($banner['button_url']); ?>" class="hero-card-btn">
                                                    <?php echo htmlspecialchars($banner['button_text']); ?>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($banner['image']): ?>
                                            <div class="hero-card-bg" style="background-image: url('<?php echo UPLOADS_URL; ?>/banners/<?php echo htmlspecialchars($banner['image']); ?>');"></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>





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
                        <div class="col-md-6 col-lg-3">
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
                                <a href="<?php echo SITE_URL; ?>/categoria/<?php echo $category['slug']; ?>" class="btn btn-outline-primary">
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
                        <div class="col-md-6 col-lg-3">
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
                                        <a href="<?php echo SITE_URL; ?>/producto/<?php echo $product['slug']; ?>" class="btn btn-primary">Ver Detalles</a>
                                    </div>
                                    <?php if ($product['is_free']): ?>
                                        <span class="product-badge free">GRATIS</span>
                                    <?php endif; ?>
                                </div>
                                <div class="product-info">
                                    <div class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></div>
                                    <h5 class="product-title">
                                        <a href="<?php echo SITE_URL; ?>/producto/<?php echo $product['slug']; ?>" class="text-decoration-none text-dark">
                                            <?php echo htmlspecialchars($product['name']); ?>
                                        </a>
                                    </h5>
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
        <section class="stats-section">
            <div class="container">
                <div class="stats-container">
                    <div class="row g-3">
                        <div class="col-md-3 col-6">
                            <div class="stat-card" data-aos="fade-up" data-aos-delay="0">
                                <div class="stat-icon">
                                    <i class="fas fa-download"></i>
                                </div>
                                <div class="stat-content">
                                    <!-- <h3 class="stat-number" data-target="<?php echo number_format($totalDownloads); ?>">0</h3> -->
                                    <h3 class="stat-number">Total</h3>
                                    <p class="stat-label">Descargas</p>
                                    <h3 class="stat-number">10K+</h3>
                                    <!-- <div class="stat-code">[DL_COUNT]</div> -->
                                </div>
                                <div class="stat-glow"></div>
                            </div>
                        </div>

                        <div class="col-md-3 col-6">
                            <div class="stat-card" data-aos="fade-up" data-aos-delay="100">
                                <div class="stat-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="stat-content">
                                    <!-- <h3 class="stat-number" data-target="<?php echo number_format($totalUsers); ?>">0</h3> -->
                                    <h3 class="stat-number">Usuarios</h3>
                                    <p class="stat-label">Registrados</p>
                                    <h3 class="stat-number">3K+</h3>
                                    <!-- <div class="stat-code">{users}</div> -->
                                </div>
                                <div class="stat-glow"></div>
                            </div>
                        </div>

                        <div class="col-md-3 col-6">
                            <div class="stat-card" data-aos="fade-up" data-aos-delay="200">
                                <div class="stat-icon">
                                    <i class="fas fa-code"></i>
                                </div>
                                <div class="stat-content">
                                    <h3 class="stat-number" data-target="<?php echo $totalProducts; ?>">0</h3>
                                    <p class="stat-label">Projects</p>
                                    <div class="stat-code">&lt;/projects&gt;</div>
                                </div>
                                <div class="stat-glow"></div>
                            </div>
                        </div>

                        <div class="col-md-3 col-6">
                            <div class="stat-card" data-aos="fade-up" data-aos-delay="300">
                                <div class="stat-icon">
                                    <i class="fas fa-star"></i>
                                </div>
                                <div class="stat-content">
                                    <h3 class="stat-number" data-target="4.9">0</h3>
                                    <p class="stat-label">Rating</p>
                                    <div class="stat-code">★★★★★</div>
                                </div>
                                <div class="stat-glow"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Partículas de código de fondo -->
            <div class="code-particles-bg">
                <span class="particle">DevCayao</span>
                <span class="particle">DevCayao</span>
                <span class="particle">DevCayao</span>
                <span class="particle">DevCayao</span>
                <span class="particle">DevCayao</span>
                <span class="particle">DevCayao</span>
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
                                        <a href="<?php echo SITE_URL; ?>/producto/<?php echo $product['slug']; ?>">
                                            <img src="<?php echo UPLOADS_URL; ?>/products/<?php echo $product['image']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                        </a>
                                    <?php else: ?>
                                        <div class="no-image">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="product-info">
                                    <h6 class="product-title">
                                        <a href="<?php echo SITE_URL; ?>/producto/<?php echo $product['slug']; ?>" class="text-decoration-none text-dark">
                                            <?php echo htmlspecialchars($product['name']); ?>
                                        </a>
                                    </h6>
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


        <!-- 4. SIDEBAR BANNERS - Grid de cristal -->
        <?php if (!empty($sidebarBanners)): ?>
            <section class="crystal-banners-section py-5">
                <div class="container">
                    <div class="row">
                        <div class="col-12 text-center mb-5">
                            <h2 class="crystal-title" data-aos="fade-down">Colección Exclusiva</h2>
                            <div class="crystal-divider"></div>
                        </div>
                    </div>
                    <div class="row g-4">
                        <?php foreach ($sidebarBanners as $index => $banner): ?>
                            <div class="col-md-6 col-lg-<?php echo count($sidebarBanners) <= 2 ? '6' : (count($sidebarBanners) == 3 ? '4' : '3'); ?>">
                                <div class="crystal-card" data-aos="fade-up" data-aos-delay="<?php echo $index * 150; ?>">
                                    <div class="crystal-inner">
                                        <div class="crystal-glow"></div>
                                        <?php if ($banner['image']): ?>
                                            <div class="crystal-image">
                                                <img src="<?php echo UPLOADS_URL; ?>/banners/<?php echo htmlspecialchars($banner['image']); ?>"
                                                    alt="<?php echo htmlspecialchars($banner['title']); ?>">
                                                <div class="crystal-overlay"></div>
                                            </div>
                                        <?php endif; ?>
                                        <div class="crystal-content">
                                            <h5 class="crystal-title-small"><?php echo htmlspecialchars($banner['title']); ?></h5>
                                            <?php if ($banner['subtitle']): ?>
                                                <p class="crystal-subtitle"><?php echo htmlspecialchars($banner['subtitle']); ?></p>
                                            <?php endif; ?>
                                            <?php if ($banner['description']): ?>
                                                <p class="crystal-description"><?php echo htmlspecialchars($banner['description']); ?></p>
                                            <?php endif; ?>
                                            <?php if ($banner['button_text'] && $banner['button_url']): ?>
                                                <a href="<?php echo htmlspecialchars($banner['button_url']); ?>" class="crystal-btn">
                                                    <?php echo htmlspecialchars($banner['button_text']); ?>
                                                    <i class="fas fa-arrow-right"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

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


    <!-- JavaScript adicional para mejorar el carousel -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const carousel = document.querySelector('#heroCarousel');
            if (carousel) {
                // Pausar en hover
                carousel.addEventListener('mouseenter', function() {
                    bootstrap.Carousel.getInstance(carousel).pause();
                });

                // Reanudar al salir
                carousel.addEventListener('mouseleave', function() {
                    bootstrap.Carousel.getInstance(carousel).cycle();
                });

                // Reiniciar animaciones en cada slide
                carousel.addEventListener('slide.bs.carousel', function() {
                    // Resetear animaciones del slide que se va
                    const activeSlide = carousel.querySelector('.carousel-item.active');
                    if (activeSlide) {
                        const elements = activeSlide.querySelectorAll('.animate-slide-up');
                        elements.forEach(el => {
                            el.style.animation = 'none';
                            el.offsetHeight; // Trigger reflow
                            el.style.animation = null;
                        });
                    }
                });
            }
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar AOS
            AOS.init({
                duration: 1000,
                easing: 'ease-out-cubic',
                once: true,
                offset: 100
            });

            // Efecto parallax para mega banner
            window.addEventListener('scroll', function() {
                const scrolled = window.pageYOffset;
                const megaBgs = document.querySelectorAll('.mega-banner-bg');
                megaBgs.forEach(bg => {
                    const speed = scrolled * 0.3;
                    // bg.style.transform = `translateY(${speed}px)`;
                });
            });

            // Efecto de cristal en movimiento
            const crystalCards = document.querySelectorAll('.crystal-inner');
            crystalCards.forEach(card => {
                card.addEventListener('mousemove', function(e) {
                    const rect = card.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;

                    const centerX = rect.width / 2;
                    const centerY = rect.height / 2;

                    const rotateX = (y - centerY) / 10;
                    const rotateY = (centerX - x) / 10;

                    card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateZ(20px)`;
                });

                card.addEventListener('mouseleave', function() {
                    card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) translateZ(0)';
                });
            });
        });
    </script>

    <!-- JavaScript para animación de contadores -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Función para animar números
            function animateCounter(element, target, duration = 2000) {
                const start = 0;
                const increment = target / (duration / 16);
                let current = start;

                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        element.textContent = target;
                        clearInterval(timer);
                    } else {
                        element.textContent = Math.floor(current);
                    }
                }, 16);
            }

            // Función para formatear números grandes
            function formatNumber(num) {
                if (num >= 1000000) {
                    return (num / 1000000).toFixed(1) + 'M';
                } else if (num >= 1000) {
                    return (num / 1000).toFixed(1) + 'K';
                }
                return num.toString();
            }

            // Observador de intersección para activar animaciones
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const statNumbers = entry.target.querySelectorAll('.stat-number');

                        statNumbers.forEach(stat => {
                            const target = parseInt(stat.getAttribute('data-target'));
                            const isDecimal = stat.getAttribute('data-target').includes('.');

                            if (isDecimal) {
                                // Para números decimales como 4.9
                                let current = 0;
                                const increment = target / 100;
                                const timer = setInterval(() => {
                                    current += increment;
                                    if (current >= target) {
                                        stat.textContent = target;
                                        clearInterval(timer);
                                    } else {
                                        stat.textContent = current.toFixed(1);
                                    }
                                }, 20);
                            } else {
                                // Para números enteros
                                animateCounter(stat, target);
                            }
                        });

                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.5
            });

            // Observar la sección de estadísticas
            const statsSection = document.querySelector('.stats-section');
            if (statsSection) {
                observer.observe(statsSection);
            }

            // Efecto de typed en códigos al hover
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => {
                const codeElement = card.querySelector('.stat-code');
                const originalText = codeElement.textContent;

                card.addEventListener('mouseenter', () => {
                    let i = 0;
                    codeElement.textContent = '';
                    const typeInterval = setInterval(() => {
                        codeElement.textContent += originalText[i];
                        i++;
                        if (i >= originalText.length) {
                            clearInterval(typeInterval);
                        }
                    }, 50);
                });

                card.addEventListener('mouseleave', () => {
                    codeElement.textContent = originalText;
                });
            });
        });
    </script>
</body>

</html>