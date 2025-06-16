<?php
// pages/cart.php - Página completa del carrito
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/cart.php';

// Verificar modo mantenimiento
if (Settings::get('maintenance_mode', '0') == '1' && !isAdmin()) {
    include '../maintenance.php';
    exit;
}

// Obtener datos del carrito
$cartItems = Cart::getItems();
$cartTotals = Cart::getTotals();
$cartEmpty = Cart::isEmpty();

$siteName = Settings::get('site_name', 'MiSistema');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrito de Compras - <?php echo htmlspecialchars($siteName); ?></title>
    
    <meta name="description" content="Revisa los productos en tu carrito de compras">
    <meta name="robots" content="noindex, follow">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    
    <style>
        .cart-page {
            min-height: 70vh;
        }
        
        .cart-item {
            padding: 2rem;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            margin-bottom: 1rem;
            background: white;
            transition: all 0.3s ease;
        }
        
        .cart-item:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .product-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .quantity-controls {
            width: 140px;
        }
        
        .cart-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 2rem;
            position: sticky;
            top: 20px;
        }
        
        .empty-cart {
            text-align: center;
            padding: 4rem 2rem;
        }
        
        .proceed-buttons {
            margin-top: 2rem;
        }
        
        .continue-shopping {
            border-color: #6c757d;
            color: #6c757d;
        }
        
        .continue-shopping:hover {
            background: #6c757d;
            color: white;
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
                <li class="breadcrumb-item active">Carrito de Compras</li>
            </ol>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="container cart-page my-5">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">
                    <i class="fas fa-shopping-cart me-3"></i>
                    Carrito de Compras
                    <?php if (!$cartEmpty): ?>
                        <span class="badge bg-primary ms-2"><?php echo $cartTotals['items_count']; ?> productos</span>
                    <?php endif; ?>
                </h1>
            </div>
        </div>
        
        <?php if ($cartEmpty): ?>
            <!-- Carrito Vacío -->
            <div class="empty-cart">
                <i class="fas fa-shopping-cart fa-5x text-muted mb-4"></i>
                <h3 class="text-muted mb-3">Tu carrito está vacío</h3>
                <p class="text-muted mb-4">¡Explora nuestros productos y encuentra algo increíble!</p>
                <a href="<?php echo SITE_URL; ?>/productos" class="btn btn-primary btn-lg">
                    <i class="fas fa-search me-2"></i>Explorar Productos
                </a>
            </div>
        <?php else: ?>
            <div class="row">
                <!-- Items del Carrito -->
                <div class="col-lg-8">
                    <div class="cart-items">
                        <?php foreach ($cartItems as $productId => $item): ?>
                            <div class="cart-item" data-product-id="<?php echo $productId; ?>">
                                <div class="row align-items-center">
                                    <!-- Imagen del Producto -->
                                    <div class="col-md-2">
                                        <?php if ($item['image']): ?>
                                            <img src="<?php echo UPLOADS_URL; ?>/products/<?php echo $item['image']; ?>" 
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                 class="product-image img-fluid">
                                        <?php else: ?>
                                            <div class="product-image bg-light d-flex align-items-center justify-content-center">
                                                <i class="fas fa-image text-muted fa-2x"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Información del Producto -->
                                    <div class="col-md-4">
                                        <h5 class="mb-2">
                                            <a href="<?php echo SITE_URL; ?>/producto/<?php echo $item['slug']; ?>" 
                                               class="text-decoration-none text-dark">
                                                <?php echo htmlspecialchars($item['name']); ?>
                                            </a>
                                        </h5>
                                        <?php if ($item['category_name']): ?>
                                            <p class="text-muted mb-2">
                                                <small><i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($item['category_name']); ?></small>
                                            </p>
                                        <?php endif; ?>
                                        <p class="mb-0">
                                            <?php if ($item['is_free']): ?>
                                                <span class="badge bg-success">GRATIS</span>
                                            <?php else: ?>
                                                <strong class="text-primary"><?php echo formatPrice($item['price']); ?></strong>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    
                                    <!-- Controles de Cantidad -->
                                    <div class="col-md-3">
                                        <div class="quantity-controls">
                                            <label class="form-label">Cantidad:</label>
                                            <div class="input-group">
                                                <button class="btn btn-outline-secondary quantity-btn" type="button" 
                                                        data-action="decrease" data-product-id="<?php echo $productId; ?>">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <input type="number" class="form-control text-center quantity-input" 
                                                       value="<?php echo $item['quantity']; ?>" min="1" max="10" 
                                                       data-product-id="<?php echo $productId; ?>">
                                                <button class="btn btn-outline-secondary quantity-btn" type="button" 
                                                        data-action="increase" data-product-id="<?php echo $productId; ?>">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Subtotal y Acciones -->
                                    <div class="col-md-3 text-center">
                                        <div class="item-subtotal mb-3">
                                            <?php if ($item['is_free']): ?>
                                                <h5 class="text-success mb-0">GRATIS</h5>
                                            <?php else: ?>
                                                <h5 class="text-primary mb-0"><?php echo formatPrice($item['price'] * $item['quantity']); ?></h5>
                                                <?php if ($item['quantity'] > 1): ?>
                                                    <small class="text-muted"><?php echo formatPrice($item['price']); ?> c/u</small>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <button class="btn btn-outline-danger btn-sm remove-item" 
                                                data-product-id="<?php echo $productId; ?>" 
                                                title="Eliminar del carrito">
                                            <i class="fas fa-trash me-1"></i>Eliminar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Acciones del Carrito -->
                    <div class="cart-actions mt-4">
                        <div class="row">
                            <div class="col-md-6">
                                <a href="<?php echo SITE_URL; ?>/productos" class="btn btn-outline-primary">
                                    <i class="fas fa-arrow-left me-2"></i>Continuar Comprando
                                </a>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <button type="button" class="btn btn-outline-danger" onclick="clearCart()">
                                    <i class="fas fa-trash me-2"></i>Vaciar Carrito
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Resumen del Carrito -->
                <div class="col-lg-4">
                    <div class="cart-summary">
                        <h4 class="mb-4">
                            <i class="fas fa-calculator me-2"></i>Resumen del Pedido
                        </h4>
                        
                        <!-- Desglose de precios -->
                        <div class="summary-details">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Productos (<?php echo $cartTotals['items_count']; ?>):</span>
                                <span>
                                    <?php if ($cartTotals['subtotal'] > 0): ?>
                                        <?php echo formatPrice($cartTotals['subtotal']); ?>
                                    <?php else: ?>
                                        <span class="text-success">GRATIS</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            
                            <?php if ($cartTotals['tax'] > 0): ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Impuestos (<?php echo $cartTotals['tax_rate']; ?>%):</span>
                                    <span><?php echo formatPrice($cartTotals['tax']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <hr>
                            
                            <div class="d-flex justify-content-between mb-4">
                                <h5>Total:</h5>
                                <h5 class="text-success"><?php echo formatPrice($cartTotals['total']); ?></h5>
                            </div>
                        </div>
                        
                        <!-- Información adicional -->
                        <div class="benefits mb-4">
                            <h6><i class="fas fa-check-circle text-success me-2"></i>Este pedido incluye:</h6>
                            <ul class="list-unstyled">
                                <li><small><i class="fas fa-download me-2 text-primary"></i>Descarga inmediata</small></li>
                                <li><small><i class="fas fa-code me-2 text-primary"></i>Código fuente completo</small></li>
                                <li><small><i class="fas fa-file-alt me-2 text-primary"></i>Documentación incluida</small></li>
                                <li><small><i class="fas fa-sync me-2 text-primary"></i><?php echo DEFAULT_UPDATE_MONTHS; ?> meses de actualizaciones</small></li>
                                <li><small><i class="fas fa-life-ring me-2 text-primary"></i>Soporte técnico</small></li>
                            </ul>
                        </div>
                        
                        <!-- Botones de acción -->
                        <div class="proceed-buttons">
                            <a href="<?php echo SITE_URL; ?>/pages/checkout.php" class="btn btn-success btn-lg w-100 mb-3">
                                <i class="fas fa-credit-card me-2"></i>
                                <?php echo $cartTotals['total'] > 0 ? 'Proceder al Pago' : 'Confirmar Pedido Gratuito'; ?>
                            </a>
                            
                            <div class="security-info text-center">
                                <small class="text-muted">
                                    <i class="fas fa-shield-alt me-1"></i>Compra 100% segura y protegida
                                </small>
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
        document.addEventListener('DOMContentLoaded', function() {
            // Los event listeners para el carrito ya están en main.js
            // Solo necesitamos actualizar el contador al cargar
            updateCartDisplay();
        });
        
        function clearCart() {
            if (!confirm('¿Estás seguro de que quieres vaciar todo el carrito?')) {
                return;
            }
            
            fetch('/api/cart/clear.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al vaciar el carrito');
            });
        }
    </script>
</body>
</html>