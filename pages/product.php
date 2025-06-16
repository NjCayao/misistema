<?php
// pages/product.php - Página individual de producto
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/settings.php';

// Verificar modo mantenimiento
if (Settings::get('maintenance_mode', '0') == '1' && !isAdmin()) {
    include '../maintenance.php';
    exit;
}

// Obtener slug del producto
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    header("HTTP/1.0 404 Not Found");
    include '../404.php';
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Obtener producto
    $stmt = $db->prepare("
        SELECT p.*, c.name as category_name, c.slug as category_slug
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.slug = ? AND p.is_active = 1
    ");
    $stmt->execute([$slug]);
    $product = $stmt->fetch();

    if (!$product) {
        header("HTTP/1.0 404 Not Found");
        include '../404.php';
        exit;
    }

    // Obtener versiones del producto
    $stmt = $db->prepare("
        SELECT version, changelog, created_at, is_current
        FROM product_versions 
        WHERE product_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$product['id']]);
    $versions = $stmt->fetchAll();

    // Obtener productos relacionados (misma categoría)
    $stmt = $db->prepare("
        SELECT p.*, c.name as category_name
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.category_id = ? AND p.id != ? AND p.is_active = 1 
        ORDER BY RAND() 
        LIMIT 4
    ");
    $stmt->execute([$product['category_id'], $product['id']]);
    $relatedProducts = $stmt->fetchAll();

    // Incrementar contador de vistas
    $stmt = $db->prepare("UPDATE products SET view_count = view_count + 1 WHERE id = ?");
    $stmt->execute([$product['id']]);

    // Obtener reseñas (implementar más adelante)
    $reviews = [];
    $averageRating = 0;
    $totalReviews = 0;
} catch (Exception $e) {
    logError("Error en página de producto: " . $e->getMessage());
    header("HTTP/1.0 500 Internal Server Error");
    include '../500.php';
    exit;
}

$siteName = Settings::get('site_name', 'MiSistema');
$pageTitle = $product['meta_title'] ?: $product['name'];
$pageDescription = $product['meta_description'] ?: $product['short_description'];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo htmlspecialchars($siteName); ?></title>

    <meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($product['name'] . ', ' . $product['category_name']); ?>">

    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta property="og:type" content="product">
    <meta property="og:url" content="<?php echo SITE_URL; ?>/producto/<?php echo $product['slug']; ?>">
    <?php if ($product['image']): ?>
        <meta property="og:image" content="<?php echo UPLOADS_URL; ?>/products/<?php echo $product['image']; ?>">
    <?php endif; ?>

    <!-- Schema.org -->
    <script type="application/ld+json">
        {
            "@context": "https://schema.org/",
            "@type": "SoftwareApplication",
            "name": "<?php echo htmlspecialchars($product['name']); ?>",
            "description": "<?php echo htmlspecialchars($pageDescription); ?>",
            "applicationCategory": "<?php echo htmlspecialchars($product['category_name']); ?>",
            "operatingSystem": "Web, Windows, Linux, Mac",
            "offers": {
                "@type": "Offer",
                "price": "<?php echo $product['is_free'] ? '0' : $product['price']; ?>",
                "priceCurrency": "USD",
                "availability": "https://schema.org/InStock"
            }
            <?php if ($product['image']): ?>,
                "image": "<?php echo UPLOADS_URL; ?>/products/<?php echo $product['image']; ?>"
            <?php endif; ?>
        }
    </script>

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">

    <style>
        .product-gallery img {
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .product-badges {
            position: absolute;
            top: 15px;
            right: 15px;
            z-index: 10;
        }

        .product-badges .badge {
            display: block;
            margin-bottom: 5px;
        }

        .price-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
        }

        .price-original {
            font-size: 2rem;
            font-weight: 700;
            color: #28a745;
        }

        .price-free {
            font-size: 2rem;
            font-weight: 700;
            color: #28a745;
        }

        .product-actions {
            margin-top: 20px;
        }

        .product-actions .btn {
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 25px;
        }

        .product-meta {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .meta-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .meta-item:last-child {
            border-bottom: none;
        }

        .version-timeline {
            max-height: 400px;
            overflow-y: auto;
        }

        .version-item {
            border-left: 3px solid #007bff;
            padding-left: 15px;
            margin-bottom: 20px;
            position: relative;
        }

        .version-item::before {
            content: '';
            position: absolute;
            left: -7px;
            top: 5px;
            width: 10px;
            height: 10px;
            background: #007bff;
            border-radius: 50%;
        }

        .version-item.current {
            border-left-color: #28a745;
        }

        .version-item.current::before {
            background: #28a745;
        }

        .related-products {
            margin-top: 50px;
        }

        .sticky-purchase {
            position: sticky;
            top: 20px;
        }

        .demo-button {
            margin-top: 15px;
        }

        .feature-list {
            list-style: none;
            padding: 0;
        }

        .feature-list li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .feature-list li:last-child {
            border-bottom: none;
        }

        .feature-list i {
            color: #28a745;
            margin-right: 10px;
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
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/productos">Productos</a></li>
                <?php if ($product['category_name']): ?>
                    <li class="breadcrumb-item">
                        <a href="<?php echo SITE_URL; ?>/productos?categoria=<?php echo $product['category_slug']; ?>">
                            <?php echo htmlspecialchars($product['category_name']); ?>
                        </a>
                    </li>
                <?php endif; ?>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($product['name']); ?></li>
            </ol>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="container my-5">
        <div class="row">
            <!-- Contenido Principal -->
            <div class="col-lg-8">
                <!-- Galería del Producto -->
                <div class="product-gallery mb-4 position-relative">
                    <?php if ($product['image']): ?>
                        <img src="<?php echo UPLOADS_URL; ?>/products/<?php echo $product['image']; ?>"
                            alt="<?php echo htmlspecialchars($product['name']); ?>"
                            class="img-fluid w-100" style="max-height: 400px; object-fit: cover;">
                    <?php else: ?>
                        <div class="no-image d-flex align-items-center justify-content-center" style="height: 400px; background: #f8f9fa; border-radius: 10px;">
                            <i class="fas fa-image fa-5x text-muted"></i>
                        </div>
                    <?php endif; ?>

                    <!-- Badges -->
                    <div class="product-badges">
                        <?php if ($product['is_free']): ?>
                            <span class="badge bg-success fs-6">GRATIS</span>
                        <?php endif; ?>
                        <?php if ($product['is_featured']): ?>
                            <span class="badge bg-warning fs-6">DESTACADO</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Información del Producto -->
                <div class="product-info">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <?php if ($product['category_name']): ?>
                                <span class="badge bg-primary mb-2"><?php echo htmlspecialchars($product['category_name']); ?></span>
                            <?php endif; ?>
                            <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                            <p class="lead text-muted"><?php echo htmlspecialchars($product['short_description']); ?></p>
                        </div>
                    </div>

                    <!-- Tabs de Información -->
                    <ul class="nav nav-tabs mb-4" id="productTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="description-tab" data-bs-toggle="tab" data-bs-target="#description" type="button">
                                <i class="fas fa-info-circle me-2"></i>Descripción
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="versions-tab" data-bs-toggle="tab" data-bs-target="#versions" type="button">
                                <i class="fas fa-code-branch me-2"></i>Versiones (<?php echo count($versions); ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button">
                                <i class="fas fa-star me-2"></i>Reseñas (<?php echo $totalReviews; ?>)
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="productTabsContent">
                        <!-- Descripción -->
                        <div class="tab-pane fade show active" id="description" role="tabpanel">
                            <div class="content-section">
                                <?php if ($product['description']): ?>
                                    <div class="product-description">
                                        <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No hay descripción detallada disponible.</p>
                                <?php endif; ?>

                                <!-- Características Principales -->
                                <div class="mt-4">
                                    <h5>Características Principales</h5>
                                    <ul class="feature-list">
                                        <li><i class="fas fa-check"></i> Código fuente completo incluido</li>
                                        <li><i class="fas fa-check"></i> Documentación detallada</li>
                                        <li><i class="fas fa-check"></i> Soporte técnico incluido</li>
                                        <li><i class="fas fa-check"></i> Actualizaciones por <?php echo $product['update_months']; ?> meses</li>
                                        <li><i class="fas fa-check"></i> Hasta <?php echo $product['download_limit']; ?> descargas</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Versiones -->
                        <div class="tab-pane fade" id="versions" role="tabpanel">
                            <div class="version-timeline">
                                <?php if (empty($versions)): ?>
                                    <p class="text-muted">No hay versiones disponibles aún.</p>
                                <?php else: ?>
                                    <?php foreach ($versions as $version): ?>
                                        <div class="version-item <?php echo $version['is_current'] ? 'current' : ''; ?>">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h6 class="mb-0">
                                                    Versión <?php echo htmlspecialchars($version['version']); ?>
                                                    <?php if ($version['is_current']): ?>
                                                        <span class="badge bg-success ms-2">Actual</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <small class="text-muted">
                                                    <?php echo date('d/m/Y', strtotime($version['created_at'])); ?>
                                                </small>
                                            </div>
                                            <?php if ($version['changelog']): ?>
                                                <p class="mb-0 text-muted"><?php echo nl2br(htmlspecialchars($version['changelog'])); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Reseñas -->
                        <div class="tab-pane fade" id="reviews" role="tabpanel">
                            <div class="reviews-section">
                                <p class="text-muted">Sistema de reseñas próximamente disponible.</p>
                                <!-- Aquí implementaremos las reseñas en la Fase 5 -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar de Compra -->
            <div class="col-lg-4">
                <div class="sticky-purchase">
                    <!-- Precio y Compra -->
                    <div class="price-section text-center">
                        <?php if ($product['is_free']): ?>
                            <div class="price-free">GRATIS</div>
                            <p class="text-muted mb-0">Descarga gratuita</p>
                        <?php else: ?>
                            <div class="price-original"><?php echo formatPrice($product['price']); ?></div>
                            <p class="text-muted mb-0">Pago único - Sin suscripciones</p>
                        <?php endif; ?>

                        <div class="product-actions">
                            <?php if ($product['is_free']): ?>
                                <button class="btn btn-success btn-lg w-100 mb-2" onclick="downloadFree(<?php echo $product['id']; ?>)">
                                    <i class="fas fa-download me-2"></i>Descargar Gratis
                                </button>
                            <?php else: ?>
                                <button class="btn btn-primary btn-lg w-100 mb-2" onclick="addToCart(<?php echo $product['id']; ?>)">
                                    <i class="fas fa-cart-plus me-2"></i>Agregar al Carrito
                                </button>
                                <button class="btn btn-success btn-lg w-100 mb-2" onclick="buyNow(<?php echo $product['id']; ?>)">
                                    <i class="fas fa-credit-card me-2"></i>Comprar Ahora
                                </button>
                            <?php endif; ?>

                            <button class="btn btn-outline-secondary w-100" onclick="addToWishlist(<?php echo $product['id']; ?>)">
                                <i class="fas fa-heart me-2"></i>Agregar a Favoritos
                            </button>

                            <?php if ($product['demo_url']): ?>
                                <div class="demo-button">
                                    <a href="<?php echo htmlspecialchars($product['demo_url']); ?>" target="_blank" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-external-link-alt me-2"></i>Ver Demo
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Información del Producto -->
                    <div class="product-meta">
                        <h6 class="mb-3">Información del Producto</h6>

                        <div class="meta-item">
                            <span class="text-muted">Categoría:</span>
                            <span><?php echo htmlspecialchars($product['category_name'] ?: 'Sin categoría'); ?></span>
                        </div>

                        <div class="meta-item">
                            <span class="text-muted">Versiones:</span>
                            <span><?php echo count($versions); ?></span>
                        </div>

                        <div class="meta-item">
                            <span class="text-muted">Descargas:</span>
                            <span><?php echo number_format($product['download_count']); ?></span>
                        </div>

                        <div class="meta-item">
                            <span class="text-muted">Vistas:</span>
                            <span><?php echo number_format($product['view_count']); ?></span>
                        </div>

                        <div class="meta-item">
                            <span class="text-muted">Publicado:</span>
                            <span><?php echo date('d/m/Y', strtotime($product['created_at'])); ?></span>
                        </div>

                        <div class="meta-item">
                            <span class="text-muted">Actualizado:</span>
                            <span><?php echo date('d/m/Y', strtotime($product['updated_at'])); ?></span>
                        </div>
                    </div>

                    <!-- Compartir -->
                    <div class="share-section text-center">
                        <h6>Compartir</h6>
                        <div class="share-buttons">
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(SITE_URL . '/producto/' . $product['slug']); ?>"
                                target="_blank" class="btn btn-outline-primary btn-sm me-2">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(SITE_URL . '/producto/' . $product['slug']); ?>&text=<?php echo urlencode($product['name']); ?>"
                                target="_blank" class="btn btn-outline-info btn-sm me-2">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="https://wa.me/?text=<?php echo urlencode($product['name'] . ' - ' . SITE_URL . '/producto/' . $product['slug']); ?>"
                                target="_blank" class="btn btn-outline-success btn-sm">
                                <i class="fab fa-whatsapp"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Productos Relacionados -->
        <?php if (!empty($relatedProducts)): ?>
            <div class="related-products">
                <h3 class="mb-4">Productos Relacionados</h3>
                <div class="row g-4">
                    <?php foreach ($relatedProducts as $relatedProduct): ?>
                        <div class="col-md-6 col-lg-3">
                            <div class="product-card compact h-100">
                                <div class="product-image">
                                    <?php if ($relatedProduct['image']): ?>
                                        <img src="<?php echo UPLOADS_URL; ?>/products/<?php echo $relatedProduct['image']; ?>"
                                            alt="<?php echo htmlspecialchars($relatedProduct['name']); ?>">
                                    <?php else: ?>
                                        <div class="no-image">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="product-overlay">
                                        <a href="<?php echo SITE_URL; ?>/producto/<?php echo $relatedProduct['slug']; ?>" class="btn btn-primary btn-sm">Ver</a>
                                    </div>
                                </div>
                                <div class="product-info">
                                    <h6 class="product-title">
                                        <a href="<?php echo SITE_URL; ?>/producto/<?php echo $relatedProduct['slug']; ?>" class="text-decoration-none text-dark">
                                            <?php echo htmlspecialchars($relatedProduct['name']); ?>
                                        </a>
                                    </h6>
                                    <div class="product-price">
                                        <?php if ($relatedProduct['is_free']): ?>
                                            <span class="price-free">GRATIS</span>
                                        <?php else: ?>
                                            <span class="price"><?php echo formatPrice($relatedProduct['price']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
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

        // Guardar texto original de botones
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('button[onclick*="addToCart"]').forEach(button => {
                button.dataset.originalText = button.innerHTML;
            });
        });
    </script>
</body>

</html>