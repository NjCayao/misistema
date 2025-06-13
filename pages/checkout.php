<?php
// pages/checkout.php - Página de checkout
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

// Verificar que el carrito no esté vacío
if (Cart::isEmpty()) {
    setFlashMessage('warning', 'Tu carrito está vacío. Agrega algunos productos antes de continuar.');
    redirect(SITE_URL . '/productos');
}

// Validar carrito
$validation = Cart::validate();
if (!$validation['valid']) {
    setFlashMessage('error', 'Hay problemas con algunos productos en tu carrito. Por favor revísalo.');
    redirect(SITE_URL . '/pages/cart.php');
}

// Preparar datos del checkout
$checkoutData = Cart::prepareCheckoutData();
if (!$checkoutData['valid']) {
    setFlashMessage('error', 'Error al preparar el checkout: ' . implode(', ', $checkoutData['errors']));
    redirect(SITE_URL . '/pages/cart.php');
}

// Obtener configuraciones de pago
$stripeEnabled = Settings::get('stripe_enabled', '0') == '1';
$paypalEnabled = Settings::get('paypal_enabled', '0') == '1';
$mercadopagoEnabled = Settings::get('mercadopago_enabled', '0') == '1';
$defaultPaymentMethod = Settings::get('default_payment_method', 'stripe');

// Verificar que al menos una pasarela esté habilitada
$hasPaymentMethods = $stripeEnabled || $paypalEnabled || $mercadopagoEnabled;

// Si solo hay productos gratuitos, no necesita pasarelas de pago
$requiresPayment = $checkoutData['requires_payment'];

// Usuario actual
$user = getCurrentUser();
$isLoggedIn = isLoggedIn();

// Datos para SEO
$siteName = Settings::get('site_name', 'MiSistema');
$pageTitle = 'Checkout - Finalizar Compra';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo htmlspecialchars($siteName); ?></title>
    
    <meta name="description" content="Finaliza tu compra de forma segura">
    <meta name="robots" content="noindex, follow">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    
    <style>
        .checkout-page {
            min-height: 70vh;
        }
        
        .checkout-step {
            padding: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .step-header {
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            background: #007bff;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 1rem;
        }
        
        .payment-method {
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .payment-method:hover {
            border-color: #007bff;
        }
        
        .payment-method.selected {
            border-color: #007bff;
            background: #f8f9ff;
        }
        
        .payment-method input[type="radio"] {
            margin-right: 1rem;
        }
        
        .payment-logo {
            height: 30px;
            width: auto;
            margin-left: auto;
        }
        
        .order-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 2rem;
            position: sticky;
            top: 20px;
        }
        
        .order-item {
            padding: 1rem 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .checkout-form .form-group {
            margin-bottom: 1.5rem;
        }
        
        .payment-form {
            display: none;
            padding-top: 1.5rem;
            border-top: 1px solid #dee2e6;
        }
        
        .payment-form.active {
            display: block;
        }
        
        .security-badges {
            text-align: center;
            margin-top: 2rem;
        }
        
        .security-badge {
            display: inline-block;
            margin: 0 1rem;
            opacity: 0.7;
        }
        
        .processing-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            color: white;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        @media (max-width: 768px) {
            .checkout-step {
                padding: 1.5rem;
            }
            
            .order-summary {
                position: static;
                margin-top: 2rem;
            }
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
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/pages/cart.php">Carrito</a></li>
                <li class="breadcrumb-item active">Checkout</li>
            </ol>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="container checkout-page my-5">
        <!-- Header -->
        <div class="text-center mb-5">
            <h1><i class="fas fa-credit-card me-3"></i>Finalizar Compra</h1>
            <p class="text-muted">Completa tu información para procesar el pedido</p>
        </div>
        
        <div class="row">
            <!-- Formulario de Checkout -->
            <div class="col-lg-8">
                <form id="checkoutForm" method="POST" action="/api/payments/process_payment.php">
                    <!-- Paso 1: Información Personal -->
                    <div class="checkout-step">
                        <div class="step-header">
                            <div class="d-flex align-items-center">
                                <div class="step-number">1</div>
                                <div>
                                    <h4 class="mb-0">Información Personal</h4>
                                    <small class="text-muted">Datos de contacto y facturación</small>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!$isLoggedIn): ?>
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle me-2"></i>¿Ya tienes cuenta?</h6>
                                <p class="mb-2">Inicia sesión para una experiencia más rápida</p>
                                <a href="<?php echo SITE_URL; ?>/pages/login.php?redirect=checkout" class="btn btn-sm btn-primary">
                                    <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="checkout-form">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="first_name" class="form-label">Nombre *</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" 
                                               value="<?php echo $user['first_name'] ?? ''; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="last_name" class="form-label">Apellido *</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" 
                                               value="<?php echo $user['last_name'] ?? ''; ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="email" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo $user['email'] ?? ''; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="phone" class="form-label">Teléfono</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo $user['phone'] ?? ''; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="country" class="form-label">País *</label>
                                <select class="form-select" id="country" name="country" required>
                                    <option value="">Seleccionar país...</option>
                                    <option value="PE" <?php echo ($user['country'] ?? '') == 'PE' ? 'selected' : ''; ?>>Perú</option>
                                    <option value="CO">Colombia</option>
                                    <option value="MX">México</option>
                                    <option value="AR">Argentina</option>
                                    <option value="CL">Chile</option>
                                    <option value="ES">España</option>
                                    <option value="US">Estados Unidos</option>
                                    <option value="other">Otro</option>
                                </select>
                            </div>
                            
                            <?php if (!$isLoggedIn): ?>
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="create_account" name="create_account" value="1" checked>
                                        <label class="form-check-label" for="create_account">
                                            Crear cuenta para futuras compras (recomendado)
                                        </label>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Paso 2: Método de Pago -->
                    <?php if ($requiresPayment): ?>
                        <div class="checkout-step">
                            <div class="step-header">
                                <div class="d-flex align-items-center">
                                    <div class="step-number">2</div>
                                    <div>
                                        <h4 class="mb-0">Método de Pago</h4>
                                        <small class="text-muted">Selecciona cómo deseas pagar</small>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!$hasPaymentMethods): ?>
                                <div class="alert alert-warning">
                                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Métodos de pago no disponibles</h6>
                                    <p class="mb-0">Los métodos de pago están siendo configurados. Por favor intenta más tarde.</p>
                                </div>
                            <?php else: ?>
                                <div class="payment-methods">
                                    <?php if ($stripeEnabled): ?>
                                        <div class="payment-method <?php echo $defaultPaymentMethod == 'stripe' ? 'selected' : ''; ?>" 
                                             data-method="stripe">
                                            <div class="d-flex align-items-center">
                                                <input type="radio" name="payment_method" value="stripe" id="stripe" 
                                                       <?php echo $defaultPaymentMethod == 'stripe' ? 'checked' : ''; ?>>
                                                <label for="stripe" class="flex-grow-1">
                                                    <div class="d-flex align-items-center">
                                                        <div>
                                                            <h6 class="mb-1">Tarjeta de Crédito/Débito</h6>
                                                            <small class="text-muted">Visa, Mastercard, American Express</small>
                                                        </div>
                                                        <div class="payment-logo">
                                                            <i class="fab fa-cc-stripe fa-2x text-primary"></i>
                                                        </div>
                                                    </div>
                                                </label>
                                            </div>
                                            
                                            <div class="payment-form" id="stripe-form">
                                                <div id="card-element">
                                                    <!-- Stripe Elements se insertará aquí -->
                                                </div>
                                                <div id="card-errors" role="alert" class="text-danger mt-2"></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($paypalEnabled): ?>
                                        <div class="payment-method <?php echo $defaultPaymentMethod == 'paypal' ? 'selected' : ''; ?>" 
                                             data-method="paypal">
                                            <div class="d-flex align-items-center">
                                                <input type="radio" name="payment_method" value="paypal" id="paypal"
                                                       <?php echo $defaultPaymentMethod == 'paypal' ? 'checked' : ''; ?>>
                                                <label for="paypal" class="flex-grow-1">
                                                    <div class="d-flex align-items-center">
                                                        <div>
                                                            <h6 class="mb-1">PayPal</h6>
                                                            <small class="text-muted">Paga con tu cuenta PayPal</small>
                                                        </div>
                                                        <div class="payment-logo">
                                                            <i class="fab fa-paypal fa-2x text-primary"></i>
                                                        </div>
                                                    </div>
                                                </label>
                                            </div>
                                            
                                            <div class="payment-form" id="paypal-form">
                                                <div id="paypal-button-container"></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($mercadopagoEnabled): ?>
                                        <div class="payment-method <?php echo $defaultPaymentMethod == 'mercadopago' ? 'selected' : ''; ?>" 
                                             data-method="mercadopago">
                                            <div class="d-flex align-items-center">
                                                <input type="radio" name="payment_method" value="mercadopago" id="mercadopago"
                                                       <?php echo $defaultPaymentMethod == 'mercadopago' ? 'checked' : ''; ?>>
                                                <label for="mercadopago" class="flex-grow-1">
                                                    <div class="d-flex align-items-center">
                                                        <div>
                                                            <h6 class="mb-1">MercadoPago + Yape</h6>
                                                            <small class="text-muted">Tarjetas locales y billeteras digitales</small>
                                                        </div>
                                                        <div class="payment-logo">
                                                            <i class="fas fa-credit-card fa-2x text-success"></i>
                                                        </div>
                                                    </div>
                                                </label>
                                            </div>
                                            
                                            <div class="payment-form" id="mercadopago-form">
                                                <div id="mercadopago-button"></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Solo productos gratuitos -->
                        <div class="checkout-step">
                            <div class="step-header">
                                <div class="d-flex align-items-center">
                                    <div class="step-number">2</div>
                                    <div>
                                        <h4 class="mb-0">Confirmación</h4>
                                        <small class="text-muted">Tu pedido contiene solo productos gratuitos</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-success">
                                <h6><i class="fas fa-gift me-2"></i>¡Productos Gratuitos!</h6>
                                <p class="mb-0">Tu pedido no requiere pago. Haz clic en "Confirmar Pedido" para proceder con la descarga.</p>
                            </div>
                            
                            <input type="hidden" name="payment_method" value="free">
                        </div>
                    <?php endif; ?>
                    
                    <!-- Términos y Condiciones -->
                    <div class="checkout-step">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="accept_terms" name="accept_terms" required>
                            <label class="form-check-label" for="accept_terms">
                                Acepto los <a href="/terminos-condiciones" target="_blank">Términos y Condiciones</a> 
                                y la <a href="/politica-privacidad" target="_blank">Política de Privacidad</a> *
                            </label>
                        </div>
                        
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="newsletter" name="newsletter" value="1">
                            <label class="form-check-label" for="newsletter">
                                Suscribirme al newsletter para recibir ofertas y actualizaciones
                            </label>
                        </div>
                    </div>
                    
                    <!-- Botón de envío -->
                    <div class="d-grid gap-2">
                        <button type="submit" id="submit-button" class="btn btn-success btn-lg" 
                                <?php echo (!$hasPaymentMethods && $requiresPayment) ? 'disabled' : ''; ?>>
                            <i class="fas fa-lock me-2"></i>
                            <?php echo $requiresPayment ? 'Procesar Pago' : 'Confirmar Pedido Gratuito'; ?>
                        </button>
                        
                        <a href="<?php echo SITE_URL; ?>/pages/cart.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Volver al Carrito
                        </a>
                    </div>
                </form>
                
                <!-- Badges de seguridad -->
                <div class="security-badges">
                    <div class="security-badge">
                        <i class="fas fa-shield-alt fa-2x text-success"></i>
                        <br><small>Compra Segura</small>
                    </div>
                    <div class="security-badge">
                        <i class="fas fa-lock fa-2x text-primary"></i>
                        <br><small>SSL Encriptado</small>
                    </div>
                    <div class="security-badge">
                        <i class="fas fa-undo fa-2x text-info"></i>
                        <br><small>Garantía 30 días</small>
                    </div>
                </div>
            </div>
            
            <!-- Resumen del Pedido -->
            <div class="col-lg-4">
                <div class="order-summary">
                    <h4 class="mb-4">
                        <i class="fas fa-receipt me-2"></i>Resumen del Pedido
                    </h4>
                    
                    <!-- Items -->
                    <?php foreach ($checkoutData['items'] as $item): ?>
                        <div class="order-item">
                            <div class="d-flex">
                                <div class="flex-shrink-0 me-3">
                                    <?php if ($item['image']): ?>
                                        <img src="<?php echo UPLOADS_URL; ?>/products/<?php echo $item['image']; ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                             style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">
                                    <?php else: ?>
                                        <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                             style="width: 50px; height: 50px;">
                                            <i class="fas fa-image text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h6>
                                    <small class="text-muted">Cantidad: <?php echo $item['quantity']; ?></small>
                                    <div class="text-end">
                                        <strong>
                                            <?php echo $item['is_free'] ? 'GRATIS' : formatPrice($item['price'] * $item['quantity']); ?>
                                        </strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Totales -->
                    <div class="mt-4 pt-3 border-top">
                        <?php if ($checkoutData['totals']['subtotal'] > 0): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal:</span>
                                <span><?php echo formatPrice($checkoutData['totals']['subtotal']); ?></span>
                            </div>
                            
                            <?php if ($checkoutData['totals']['tax'] > 0): ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Impuestos:</span>
                                    <span><?php echo formatPrice($checkoutData['totals']['tax']); ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between">
                            <h5>Total:</h5>
                            <h5 class="text-success"><?php echo formatPrice($checkoutData['totals']['total']); ?></h5>
                        </div>
                        
                        <?php if (!empty($checkoutData['free_items'])): ?>
                            <div class="alert alert-success py-2 mt-3">
                                <small>
                                    <i class="fas fa-gift me-1"></i>
                                    Incluye <?php echo count($checkoutData['free_items']); ?> producto(s) gratuito(s)
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Información adicional -->
                    <div class="mt-4 pt-3 border-top">
                        <small class="text-muted">
                            <strong>Este pedido incluye:</strong><br>
                            • Descarga inmediata<br>
                            • Código fuente completo<br>
                            • Documentación detallada<br>
                            • <?php echo DEFAULT_UPDATE_MONTHS; ?> meses de actualizaciones<br>
                            • Soporte técnico incluido
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Processing Overlay -->
    <div class="processing-overlay" id="processing-overlay">
        <div class="text-center">
            <div class="spinner-border mb-3" role="status">
                <span class="visually-hidden">Procesando...</span>
            </div>
            <h5>Procesando tu pago...</h5>
            <p>Por favor no cierres esta ventana</p>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
    
    <!-- Scripts de pasarelas de pago (se cargarán en la Fase 4.2) -->
    <?php if ($stripeEnabled): ?>
        <script src="https://js.stripe.com/v3/"></script>
    <?php endif; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Manejar selección de método de pago
            const paymentMethods = document.querySelectorAll('.payment-method');
            const paymentForms = document.querySelectorAll('.payment-form');
            
            paymentMethods.forEach(method => {
                method.addEventListener('click', function() {
                    const methodType = this.dataset.method;
                    const radio = this.querySelector('input[type="radio"]');
                    
                    // Actualizar selección visual
                    paymentMethods.forEach(m => m.classList.remove('selected'));
                    this.classList.add('selected');
                    
                    // Marcar radio button
                    radio.checked = true;
                    
                    // Mostrar/ocultar formularios
                    paymentForms.forEach(form => form.classList.remove('active'));
                    const targetForm = document.getElementById(methodType + '-form');
                    if (targetForm) {
                        targetForm.classList.add('active');
                    }
                });
            });
            
            // Inicializar método seleccionado
            const selectedMethod = document.querySelector('.payment-method.selected');
            if (selectedMethod) {
                selectedMethod.click();
            }
            
            // Manejar envío del formulario
            const checkoutForm = document.getElementById('checkoutForm');
            const submitButton = document.getElementById('submit-button');
            
            checkoutForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Validar formulario
                if (!this.checkValidity()) {
                    this.classList.add('was-validated');
                    return;
                }
                
                // Mostrar overlay de procesamiento
                showProcessingOverlay();
                
                // Procesar según el método de pago
                const selectedPaymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
                
                if (selectedPaymentMethod === 'free') {
                    processFreeOrder();
                } else {
                    // Las pasarelas se implementarán en la Fase 4.2
                    processPayment(selectedPaymentMethod);
                }
            });
        });
        
        function showProcessingOverlay() {
            document.getElementById('processing-overlay').style.display = 'flex';
        }
        
        function hideProcessingOverlay() {
            document.getElementById('processing-overlay').style.display = 'none';
        }
        
        function processFreeOrder() {
            // Simular procesamiento para productos gratuitos
            setTimeout(() => {
                const formData = new FormData(document.getElementById('checkoutForm'));
                
                fetch('/api/payments/process_payment.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    hideProcessingOverlay();
                    
                    if (data.success) {
                        window.location.href = '/pages/success.php?order=' + data.order_number;
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    hideProcessingOverlay();
                    console.error('Error:', error);
                    alert('Error al procesar el pedido');
                });
            }, 2000);
        }
        
        function processPayment(paymentMethod) {
            // Por ahora mostrar mensaje - se implementará en Fase 4.2
            hideProcessingOverlay();
            alert('Integración de ' + paymentMethod + ' se completará en la siguiente fase del desarrollo');
        }
        
        // Validación en tiempo real
        document.querySelectorAll('input[required], select[required]').forEach(input => {
            input.addEventListener('blur', function() {
                if (this.value.trim() === '') {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            });
        });
        
        // Validación de email
        document.getElementById('email').addEventListener('blur', function() {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(this.value)) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            }
        });
    </script>
</body>
</html>