document.addEventListener('DOMContentLoaded', function() {
    // Header scroll effect
    const header = document.querySelector('.main-header');
    if (header) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 100) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });
    }
    
    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });
    
    // Auto-hide alerts
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.classList.add('fade');
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
    
    // Inicializar carrito
    updateCartDisplay();
    initCartEvents();
});

// === FUNCIONES DEL CARRITO DE COMPRAS ===

// Variables globales del carrito
let cartUpdating = false;

/**
 * Agregar producto al carrito
 */
function addToCart(productId, quantity = 1) {
    if (cartUpdating) return;
    
    cartUpdating = true;
    
    // Mostrar loading en el botón
    const addButton = document.querySelector(`[onclick="addToCart(${productId})"]`);
    if (addButton) {
        const originalText = addButton.innerHTML;
        addButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Agregando...';
        addButton.disabled = true;
    }
    
    // Hacer petición AJAX
    fetch('/api/cart/add.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `product_id=${productId}&quantity=${quantity}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Actualizar contador del carrito
            updateCartCount(data.cart_count);
            
            // Mostrar notificación de éxito
            showCartNotification(data.message, 'success');
            
            // Actualizar display del carrito si el modal está abierto
            if (document.getElementById('cartModal') && document.getElementById('cartModal').classList.contains('show')) {
                loadCartContent();
            }
            
            // Animar el icono del carrito
            animateCartIcon();
            
        } else {
            showCartNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showCartNotification('Error al agregar el producto', 'error');
    })
    .finally(() => {
        cartUpdating = false;
        
        // Restaurar botón
        if (addButton) {
            addButton.innerHTML = addButton.dataset.originalText || '<i class="fas fa-cart-plus me-2"></i>Agregar al Carrito';
            addButton.disabled = false;
        }
    });
}

/**
 * Actualizar cantidad de un producto
 */
function updateCartQuantity(productId, quantity) {
    if (cartUpdating) return;
    
    cartUpdating = true;
    
    fetch('/api/cart/update.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `product_id=${productId}&quantity=${quantity}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCartCount(data.cart_count);
            
            if (quantity === 0) {
                // Producto eliminado
                removeCartItemFromDOM(productId);
                showCartNotification(data.message, 'info');
            } else {
                // Cantidad actualizada
                updateCartItemDOM(productId, data);
            }
            
            // Actualizar totales
            updateCartTotals(data.totals);
            
            // Verificar si el carrito está vacío
            if (data.cart_count === 0) {
                showEmptyCart();
            }
            
        } else {
            showCartNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showCartNotification('Error al actualizar el carrito', 'error');
    })
    .finally(() => {
        cartUpdating = false;
    });
}

/**
 * Eliminar producto del carrito
 */
function removeFromCart(productId) {
    if (cartUpdating) return;
    
    cartUpdating = true;
    
    fetch('/api/cart/remove.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `product_id=${productId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCartCount(data.cart_count);
            removeCartItemFromDOM(productId);
            updateCartTotals(data.totals);
            showCartNotification(data.message, 'info');
            
            if (data.cart_empty) {
                showEmptyCart();
            }
        } else {
            showCartNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showCartNotification('Error al eliminar el producto', 'error');
    })
    .finally(() => {
        cartUpdating = false;
    });
}

/**
 * Vaciar todo el carrito
 */
function clearCart() {
    if (!confirm('¿Estás seguro de que quieres vaciar todo el carrito?')) {
        return;
    }
    
    cartUpdating = true;
    
    // Simular eliminación de todos los productos
    const cartItems = document.querySelectorAll('.cart-item');
    cartItems.forEach(item => {
        const productId = item.dataset.productId;
        removeFromCart(productId);
    });
}

/**
 * Cargar contenido del carrito
 */
function loadCartContent() {
    fetch('/api/cart/get.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCartModal(data);
            updateCartCount(data.items_count);
            
            // Mostrar errores de validación si los hay
            if (!data.validation.valid) {
                data.validation.errors.forEach(error => {
                    showCartNotification(error, 'warning');
                });
            }
        } else {
            showCartNotification('Error al cargar el carrito', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showCartNotification('Error al cargar el carrito', 'error');
    });
}

/**
 * Actualizar contador del carrito
 */
function updateCartCount(count) {
    const cartCountElements = document.querySelectorAll('#cart-count, #modal-cart-count');
    cartCountElements.forEach(element => {
        element.textContent = count;
        
        if (count > 0) {
            element.style.display = 'inline';
        } else {
            element.style.display = 'none';
        }
    });
}

/**
 * Actualizar display del carrito al cargar la página
 */
function updateCartDisplay() {
    fetch('/api/cart/get.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCartCount(data.items_count);
        }
    })
    .catch(error => {
        console.error('Error loading cart:', error);
    });
}

/**
 * Inicializar eventos del carrito
 */
function initCartEvents() {
    // Evento para abrir modal del carrito
    document.addEventListener('click', function(e) {
        if (e.target.closest('.cart-icon a')) {
            e.preventDefault();
            loadCartContent();
            const cartModal = new bootstrap.Modal(document.getElementById('cartModal'));
            cartModal.show();
        }
    });
    
    // Eventos para controles de cantidad
    document.addEventListener('click', function(e) {
        if (e.target.closest('.quantity-btn')) {
            const button = e.target.closest('.quantity-btn');
            const action = button.dataset.action;
            const productId = button.dataset.productId;
            const input = document.querySelector(`.quantity-input[data-product-id="${productId}"]`);
            
            let currentQuantity = parseInt(input.value);
            
            if (action === 'increase' && currentQuantity < 10) {
                currentQuantity++;
            } else if (action === 'decrease' && currentQuantity > 1) {
                currentQuantity--;
            }
            
            input.value = currentQuantity;
            updateCartQuantity(productId, currentQuantity);
        }
    });
    
    // Evento para inputs de cantidad
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('quantity-input')) {
            const productId = e.target.dataset.productId;
            let quantity = parseInt(e.target.value);
            
            if (quantity < 1) quantity = 1;
            if (quantity > 10) quantity = 10;
            
            e.target.value = quantity;
            updateCartQuantity(productId, quantity);
        }
    });
    
    // Evento para eliminar items
    document.addEventListener('click', function(e) {
        if (e.target.closest('.remove-item')) {
            const button = e.target.closest('.remove-item');
            const productId = button.dataset.productId;
            removeFromCart(productId);
        }
    });
}

/**
 * Actualizar modal del carrito
 */
function updateCartModal(data) {
    const modalBody = document.getElementById('cart-modal-body');
    
    if (data.cart_empty) {
        showEmptyCart();
        return;
    }
    
    // Generar HTML de los items
    let itemsHTML = '<div class="cart-items">';
    
    data.items.forEach(item => {
        itemsHTML += `
            <div class="cart-item" data-product-id="${item.id}">
                <div class="row align-items-center">
                    <div class="col-3">
                        <div class="product-image">
                            ${item.image_url ? 
                                `<img src="${item.image_url}" alt="${item.name}" class="img-fluid rounded" style="max-height: 60px; object-fit: cover;">` :
                                `<div class="no-image bg-light rounded d-flex align-items-center justify-content-center" style="height: 60px;">
                                    <i class="fas fa-image text-muted"></i>
                                </div>`
                            }
                        </div>
                    </div>
                    <div class="col-6">
                        <h6 class="mb-1">
                            <a href="${item.product_url}" class="text-decoration-none text-dark" data-bs-dismiss="modal">
                                ${item.name}
                            </a>
                        </h6>
                        ${item.category_name ? `<small class="text-muted">${item.category_name}</small>` : ''}
                        <div class="price-info mt-1">
                            ${item.is_free ? 
                                '<span class="text-success fw-bold">GRATIS</span>' :
                                `<span class="text-primary fw-bold">${item.price}</span>
                                ${item.quantity > 1 ? `<small class="text-muted">x${item.quantity} = <span class="item-subtotal">${item.subtotal}</span></small>` : ''}`
                            }
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="quantity-controls d-flex align-items-center justify-content-between">
                            <div class="input-group input-group-sm" style="max-width: 120px;">
                                <button class="btn btn-outline-secondary quantity-btn" type="button" 
                                        data-action="decrease" data-product-id="${item.id}">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input type="number" class="form-control text-center quantity-input" 
                                       value="${item.quantity}" min="1" max="10" 
                                       data-product-id="${item.id}">
                                <button class="btn btn-outline-secondary quantity-btn" type="button" 
                                        data-action="increase" data-product-id="${item.id}">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <button class="btn btn-sm btn-outline-danger ms-2 remove-item" 
                                    data-product-id="${item.id}" title="Eliminar del carrito">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <hr>
            </div>
        `;
    });
    
    itemsHTML += '</div>';
    
    // Agregar totales
    const totals = data.totals;
    itemsHTML += `
        <div class="cart-totals bg-light p-3 rounded">
            ${totals.subtotal_raw > 0 ? `
                <div class="row mb-2">
                    <div class="col-6"><span>Subtotal:</span></div>
                    <div class="col-6 text-end"><span class="cart-subtotal">${totals.subtotal}</span></div>
                </div>
                ${totals.tax_raw > 0 ? `
                    <div class="row mb-2">
                        <div class="col-6"><span>Impuestos (${totals.tax_rate}%):</span></div>
                        <div class="col-6 text-end"><span class="cart-tax">${totals.tax}</span></div>
                    </div>
                ` : ''}
                <hr class="my-2">
            ` : ''}
            <div class="row">
                <div class="col-6"><strong>Total:</strong></div>
                <div class="col-6 text-end"><strong class="text-primary cart-total">${totals.total}</strong></div>
            </div>
        </div>
    `;
    
    modalBody.innerHTML = itemsHTML;
    
    // Actualizar footer del modal
    updateCartModalFooter(true);
}

/**
 * Mostrar carrito vacío
 */
function showEmptyCart() {
    const modalBody = document.getElementById('cart-modal-body');
    modalBody.innerHTML = `
        <div class="empty-cart text-center py-5">
            <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">Tu carrito está vacío</h5>
            <p class="text-muted mb-4">Explora nuestros productos y agrega algunos al carrito</p>
            <a href="/productos" class="btn btn-primary" data-bs-dismiss="modal">
                <i class="fas fa-search me-2"></i>Explorar Productos
            </a>
        </div>
    `;
    
    updateCartModalFooter(false);
}

/**
 * Actualizar footer del modal
 */
function updateCartModalFooter(hasItems) {
    const modal = document.getElementById('cartModal');
    if (!modal) return;
    
    let footer = modal.querySelector('.modal-footer');
    
    if (!hasItems) {
        if (footer) footer.remove();
        return;
    }
    
    if (!footer) {
        footer = document.createElement('div');
        footer.className = 'modal-footer';
        modal.querySelector('.modal-content').appendChild(footer);
    }
    
    footer.innerHTML = `
        <div class="w-100">
            <div class="row g-2">
                <div class="col-6">
                    <a href="/pages/cart.php" class="btn btn-outline-primary w-100" data-bs-dismiss="modal">
                        <i class="fas fa-shopping-cart me-2"></i>Ver Carrito
                    </a>
                </div>
                <div class="col-6">
                    <a href="/pages/checkout.php" class="btn btn-success w-100" data-bs-dismiss="modal">
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
    `;
}

/**
 * Eliminar item del DOM
 */
function removeCartItemFromDOM(productId) {
    const item = document.querySelector(`[data-product-id="${productId}"]`);
    if (item) {
        item.style.transition = 'opacity 0.3s ease';
        item.style.opacity = '0';
        setTimeout(() => {
            item.remove();
        }, 300);
    }
}

/**
 * Actualizar item en el DOM
 */
function updateCartItemDOM(productId, data) {
    const item = document.querySelector(`[data-product-id="${productId}"]`);
    if (item) {
        const subtotalSpan = item.querySelector('.item-subtotal');
        if (subtotalSpan && data.item_subtotal) {
            subtotalSpan.textContent = data.item_subtotal;
        }
    }
}

/**
 * Actualizar totales en el DOM
 */
function updateCartTotals(totals) {
    const elements = {
        '.cart-subtotal': totals.subtotal,
        '.cart-tax': totals.tax,
        '.cart-total': totals.total
    };
    
    Object.entries(elements).forEach(([selector, value]) => {
        const element = document.querySelector(selector);
        if (element) {
            element.textContent = value;
        }
    });
}

/**
 * Animar icono del carrito
 */
function animateCartIcon() {
    const cartIcon = document.querySelector('.cart-icon');
    if (cartIcon) {
        cartIcon.style.animation = 'bounce 0.6s ease';
        setTimeout(() => {
            cartIcon.style.animation = '';
        }, 600);
    }
}

/**
 * Mostrar notificación del carrito
 */
function showCartNotification(message, type = 'info') {
    // Crear elemento de notificación
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible cart-notification`;
    notification.style.cssText = `
        position: fixed;
        top: 100px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease;
    `;
    
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Animar entrada
    setTimeout(() => {
        notification.style.opacity = '1';
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto-remover después de 4 segundos
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 300);
    }, 4000);
}

// CSS para animaciones
const cartCSS = `
@keyframes bounce {
    0%, 20%, 60%, 100% { transform: translateY(0); }
    40% { transform: translateY(-10px); }
    80% { transform: translateY(-5px); }
}

.cart-notification {
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    border: none;
}
`;

// Agregar CSS al head
if (!document.getElementById('cart-styles')) {
    const style = document.createElement('style');
    style.id = 'cart-styles';
    style.textContent = cartCSS;
    document.head.appendChild(style);
}