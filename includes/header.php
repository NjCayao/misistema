<?php
// includes/header.php - CORREGIDO
$siteName = getSetting('site_name', 'MiSistema');
$siteLogo = getSetting('site_logo', '');

// Obtener menú principal
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("
        SELECT * FROM menu_items 
        WHERE menu_location = 'main' AND is_active = 1 AND parent_id IS NULL 
        ORDER BY sort_order ASC
    ");
    $mainMenuItems = $stmt->fetchAll();
    
    // Obtener submenús
    $subMenus = [];
    foreach ($mainMenuItems as $item) {
        $stmt = $db->prepare("
            SELECT * FROM menu_items 
            WHERE parent_id = ? AND is_active = 1 
            ORDER BY sort_order ASC
        ");
        $stmt->execute([$item['id']]);
        $subMenus[$item['id']] = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $mainMenuItems = [];
    $subMenus = [];
}

// Función helper para procesar URLs
function processMenuUrl($url) {
    // Si la URL ya tiene http:// o https://, devolverla tal como está
    if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
        return $url;
    }
    
    // Si empieza con /, agregar SITE_URL
    if (strpos($url, '/') === 0) {
        return SITE_URL . $url;
    }
    
    // Si no empieza con /, agregar SITE_URL/
    return SITE_URL . '/' . $url;
}

// Obtener datos del usuario actual
$currentUser = getCurrentUser();
?>

<header class="main-header">
    <!-- Top Bar -->
    <div class="top-bar d-none d-lg-block">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="top-bar-left">
                        <span class="top-bar-text">
                            <i class="fas fa-envelope me-2"></i>
                            <?php echo getSetting('site_email', 'info@misistema.com'); ?>
                        </span>
                        <?php if (getSetting('contact_phone')): ?>
                            <span class="top-bar-text ms-3">
                                <i class="fas fa-phone me-2"></i>
                                <?php echo getSetting('contact_phone'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="top-bar-right text-end">
                        <!-- Redes Sociales -->
                        <?php if (getSetting('facebook_url')): ?>
                            <a href="<?php echo getSetting('facebook_url'); ?>" target="_blank" class="social-link">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                        <?php endif; ?>
                        <?php if (getSetting('twitter_url')): ?>
                            <a href="<?php echo getSetting('twitter_url'); ?>" target="_blank" class="social-link">
                                <i class="fab fa-twitter"></i>
                            </a>
                        <?php endif; ?>
                        <?php if (getSetting('instagram_url')): ?>
                            <a href="<?php echo getSetting('instagram_url'); ?>" target="_blank" class="social-link">
                                <i class="fab fa-instagram"></i>
                            </a>
                        <?php endif; ?>
                        <?php if (getSetting('linkedin_url')): ?>
                            <a href="<?php echo getSetting('linkedin_url'); ?>" target="_blank" class="social-link">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                        <?php endif; ?>
                        
                        <!-- User Menu -->
                        <div class="user-menu ms-3">
                            <?php if (isLoggedIn() && $currentUser): ?>
                                <div class="dropdown">
                                    <a href="#" class="dropdown-toggle user-link" data-bs-toggle="dropdown">
                                        <i class="fas fa-user me-1"></i>
                                        <?php echo htmlspecialchars($currentUser['first_name']); ?>
                                    </a>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/dashboard"><i class="fas fa-tachometer-alt me-2"></i>Mi Dashboard</a></li>
                                        <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/mis-compras"><i class="fas fa-shopping-bag me-2"></i>Mis Compras</a></li>
                                        <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/perfil"><i class="fas fa-user me-2"></i>Mi Perfil</a></li>
                                        <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/configuracion"><i class="fas fa-cog me-2"></i>Configuración</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/logout"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a></li>
                                    </ul>
                                </div>
                            <?php else: ?>
                                <a href="<?php echo SITE_URL; ?>/login" class="user-link">
                                    <i class="fas fa-sign-in-alt me-1"></i>Iniciar Sesión
                                </a>
                                <a href="<?php echo SITE_URL; ?>/register" class="user-link ms-2">
                                    <i class="fas fa-user-plus me-1"></i>Registrarse
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <!-- Logo -->
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <?php if ($siteLogo): ?>
                    <img src="<?php echo UPLOADS_URL; ?>/logos/<?php echo $siteLogo; ?>" alt="<?php echo htmlspecialchars($siteName); ?>" class="logo">
                <?php else: ?>
                    <span class="logo-text"><?php echo htmlspecialchars($siteName); ?></span>
                <?php endif; ?>
            </a>
            
            <!-- Mobile Toggle -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Navigation Menu -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php foreach ($mainMenuItems as $item): ?>
                        <li class="nav-item <?php echo !empty($subMenus[$item['id']]) ? 'dropdown' : ''; ?>">
                            <?php if (!empty($subMenus[$item['id']])): ?>
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                    <?php if ($item['icon']): ?>
                                        <i class="<?php echo $item['icon']; ?> me-1"></i>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($item['title']); ?>
                                </a>
                                <ul class="dropdown-menu">
                                    <?php foreach ($subMenus[$item['id']] as $subItem): ?>
                                        <li>
                                            <a class="dropdown-item" href="<?php echo processMenuUrl($subItem['url']); ?>" <?php echo $subItem['target'] == '_blank' ? 'target="_blank"' : ''; ?>>
                                                <?php if ($subItem['icon']): ?>
                                                    <i class="<?php echo $subItem['icon']; ?> me-2"></i>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($subItem['title']); ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <a class="nav-link" href="<?php echo processMenuUrl($item['url']); ?>" <?php echo $item['target'] == '_blank' ? 'target="_blank"' : ''; ?>>
                                    <?php if ($item['icon']): ?>
                                        <i class="<?php echo $item['icon']; ?> me-1"></i>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($item['title']); ?>
                                </a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                
                <!-- Search & Cart -->
                <div class="navbar-actions d-flex align-items-center">
                    <!-- Search -->
                    <div class="search-box me-3">
                        <form class="d-flex" action="<?php echo SITE_URL; ?>/buscar" method="GET">
                            <div class="input-group">
                                <input class="form-control" type="search" placeholder="Buscar productos..." name="q" value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
                                <button class="btn btn-outline-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Cart -->
                    <div class="cart-icon">
                        <a href="<?php echo SITE_URL; ?>/carrito" class="btn btn-outline-primary position-relative">
                            <i class="fas fa-shopping-cart"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="cart-count" style="display: none;">
                                0
                            </span>
                        </a>
                    </div>
                    
                    <!-- Mobile User Menu -->
                    <div class="mobile-user d-lg-none ms-3">
                        <?php if (isLoggedIn()): ?>
                            <a href="<?php echo SITE_URL; ?>/dashboard" class="btn btn-sm btn-primary">
                                <i class="fas fa-user"></i>
                            </a>
                        <?php else: ?>
                            <a href="<?php echo SITE_URL; ?>/login" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-sign-in-alt"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </nav>
</header>

<!-- Modal del Carrito -->
<?php 
// Incluir el carrito solo si existe
if (file_exists(__DIR__ . '/cart_modal.php')) {
    include __DIR__ . '/cart_modal.php';
}
?>