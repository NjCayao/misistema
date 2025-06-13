<?php
// includes/cart_modal.php - Modal del carrito de compras
require_once __DIR__ . '/../config/cart.php';

// Obtener datos del carrito
$cartItems = Cart::getItems();
$cartTotals = Cart::getTotals();
$cartEmpty = Cart::isEmpty();
?>

<!-- Modal del Carrito -->
<div class="modal fade" id="cartModal" tabindex="-1" aria-labelledby="cartModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cartModalLabel">
                    <i class="fas fa-shopping-cart me-2"></i>
                    Carrito de Compras
                    <span class="badge bg-primary ms-2" id="modal-cart-count"><?php echo $cartTotals['items_count']; ?></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            
            <div class="modal-body" id="cart-modal-body">
                <?php if ($cartEmpty): ?>
                    <!-- Carrito Vacío -->
                    <div class="empty-cart text-center py-5">
                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Tu carrito está vacío</h5>
                        <p class="text-muted mb-4">Explora nuestros productos y agrega algunos al carrito</p>
                        <a href="<?php echo SITE_URL; ?>/productos" class="btn btn-primary" data-bs-dismiss="modal">
                            <i class="fas fa-search me-2"></i>Explorar Productos
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Items del Carrito -->
                    <div class="cart-items">
                        <?php foreach ($cartItems as $productId => $item): ?>
                            <div class="cart-item" data-product-id="<?php echo $productId; ?>">
                                <div class="row align-items-center">
                                    <!-- Imagen del Producto -->
                                    <div class="col-3">
                                        <div class="product-image">
                                            <?php if ($item['image']): ?>
                                                <img src="<?php echo UPLOADS_URL; ?>/products/<?php echo $item['image']; ?>" 
                                                     alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                     class="img-fluid rounded" style="max-height: 60px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="no-image bg-light rounded d-flex align-items-center justify-content-center" style="height: 60px;">
                                                    <i class="fas fa-image text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Información del Producto -->
                                    <div class="col-6">
                                        <h6 class="mb-1">
                                            <a href="<?php echo SITE_URL; ?>/producto/<?php echo $item['slug']; ?>" 
                                               class="text-decoration-none text-dark" data-bs-dismiss="modal">
                                                <?php echo htmlspecialchars($item['name']); ?>
                                            </a>
                                        </h6>
                                        <?php if ($item['category_name']): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($item['category_name']); ?></small>
                                        <?php endif; ?>
                                        
                                        <div class="price-info mt-1">
                                            <?php if ($item['is_free']): ?>
                                                <span class="text-success fw-bold">GRATIS</span>
                                            <?php else: ?>
                                                <span class="text-primary fw-bold"><?php echo formatPrice($item['price']); ?></span>
                                                <?php if ($item['quantity'] > 1): ?>
                                                    <small class="text-muted">
                                                        x<?php echo $item['quantity']; ?> = 
                                                        <span class="item-subtotal"><?php echo formatPrice($item['price'] * $item['quantity']); ?></span>
                                                    </small>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Controles de Cantidad -->
                                    <div class="col-3">
                                        <div class="quantity-controls d-flex align-items-center justify-content-between">
                                            <div class="input-group input-group-sm" style="max-width: 120px;">
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
                                            <button class="btn btn-sm btn-outline-danger ms-2 remove-item" 
                                                    data-product-id="<?php echo $productId; ?>" 
                                                    title="Eliminar del carrito">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <hr>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Totales -->
                    <div class="cart-totals bg-light p-3 rounded">
                        <?php if ($cartTotals['subtotal'] > 0): ?>
                            <div class="row mb-2">
                                <div class="col-6">
                                    <span>Subtotal:</span>
                                </div>
                                <div class="col-6 text-end">
                                    <span class="cart-subtotal"><?php echo formatPrice($cartTotals['subtotal']); ?></span>
                                </div>
                            </div>
                            
                            <?php if ($cartTotals['tax'] > 0): ?>
                                <div class="row mb-2">
                                    <div class="col-6">
                                        <span>Impuestos (<?php echo $cartTotals['tax_rate']; ?>%):</span>
                                    </div>
                                    <div class="col-6 text-end">
                                        <span class="cart-tax"><?php echo formatPrice($cartTotals['tax']); ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <hr class="my-2">
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-6">
                                <strong>Total:</strong>
                            </div>
                            <div class="col-6 text-end">
                                <strong class="text-primary cart-total"><?php echo formatPrice($cartTotals['total']); ?></strong>
                            </div>
                        </div>
                        
                        <?php if (count($cartItems) > count(array_filter($cartItems, fn($item) => !$item['is_free']))): ?>
                            <small class="text-muted mt-2 d-block">
                                <i class="fas fa-info-circle me-1"></i>
                                Incluye productos gratuitos
                            </small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (!$cartEmpty): ?>
                <div class="modal-footer">
                    <div class="w-100">
                        <div class="row g-2">
                            <div class="col-6">
                                <a href="<?php echo SITE_URL; ?>/pages/cart.php" class="btn btn-outline-primary w-100" data-bs-dismiss="modal">
                                    <i class="fas fa-shopping-cart me-2"></i>Ver Carrito
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?php echo SITE_URL; ?>/pages/checkout.php" class="btn btn-success w-100" data-bs-dismiss="modal">
                                    <i class="fas fa-credit-card me-2"></i>Proceder al Pago
                                </a>
                            </div>
                        </div>
                        
                        <div class="text-center mt-2">
                            <button type="button" class="btn btn-link btn-sm text-muted" onclick="clearCart()">
                                <i class="fas fa-trash me-1"></i>Vaciar Carrito
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.cart-item {
    margin-bottom: 1rem;
}

.cart-item:last-child hr {
    display: none;
}

.quantity-controls .input-group {
    max-width: 120px;
}

.quantity-input {
    max-width: 50px;
}

.cart-totals {
    font-size: 0.95rem;
}

.empty-cart {
    min-height: 200px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

@media (max-width: 576px) {
    .cart-item .col-3 {
        flex: 0 0 25%;
    }
    
    .cart-item .col-6 {
        flex: 0 0 50%;
    }
    
    .cart-item .col-3:last-child {
        flex: 0 0 25%;
    }
}
</style>