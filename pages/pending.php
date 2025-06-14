<?php
// pages/pending.php - Página de pago pendiente
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/settings.php';

// Verificar modo mantenimiento
if (Settings::get('maintenance_mode', '0') == '1' && !isAdmin()) {
    include '../maintenance.php';
    exit;
}

$orderNumber = $_GET['order'] ?? '';
$paymentMethod = $_GET['method'] ?? '';
$estimatedTime = $_GET['time'] ?? '24 horas';

if (empty($orderNumber)) {
    redirect(SITE_URL);
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener orden
    $stmt = $db->prepare("
        SELECT o.*, 
               (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as items_count
        FROM orders o 
        WHERE o.order_number = ? AND o.payment_status = 'pending'
    ");
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch();
    
    if (!$order) {
        // Verificar si ya fue completada
        $stmt = $db->prepare("SELECT payment_status FROM orders WHERE order_number = ?");
        $stmt->execute([$orderNumber]);
        $status = $stmt->fetchColumn();
        
        if ($status === 'completed') {
            redirect(SITE_URL . '/pages/success.php?order=' . $orderNumber);
        } else {
            setFlashMessage('error', 'Orden no encontrada o ya procesada');
            redirect(SITE_URL);
        }
    }
    
    // Obtener items de la orden
    $stmt = $db->prepare("
        SELECT oi.*, p.slug, p.image, p.is_free
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order['id']]);
    $orderItems = $stmt->fetchAll();
    
} catch (Exception $e) {
    logError("Error en página pending: " . $e->getMessage());
    setFlashMessage('error', 'Error al cargar la información de la orden');
    redirect(SITE_URL);
}

// Configuración según método de pago
$paymentConfig = [
    'paypal' => [
        'icon' => 'fab fa-paypal',
        'color' => 'info',
        'name' => 'PayPal',
        'description' => 'Tu pago está siendo procesado por PayPal. Esto puede tomar unos minutos.',
        'instructions' => [
            'El pago será confirmado automáticamente',
            'Recibirás un email cuando esté listo',
            'No es necesario hacer nada más'
        ],
        'typical_time' => '5-15 minutos'
    ],
    'mercadopago' => [
        'icon' => 'fas fa-credit-card',
        'color' => 'warning',
        'name' => 'MercadoPago',
        'description' => 'Tu pago está siendo verificado por MercadoPago.',
        'instructions' => [
            'Si pagaste con transferencia, puede tomar hasta 2 días hábiles',
            'Los pagos con Yape se procesan en minutos',
            'Recibirás confirmación por email'
        ],
        'typical_time' => '15 minutos - 2 días'
    ],
    'stripe' => [
        'icon' => 'fab fa-cc-stripe',
        'color' => 'primary',
        'name' => 'Stripe',
        'description' => 'Tu pago está siendo procesado.',
        'instructions' => [
            'La verificación suele ser instantánea',
            'En casos excepcionales puede tomar unos minutos'
        ],
        'typical_time' => '1-5 minutos'
    ]
];

$currentPayment = $paymentConfig[$paymentMethod] ?? [
    'icon' => 'fas fa-clock',
    'color' => 'secondary',
    'name' => 'Procesador de Pagos',
    'description' => 'Tu pago está siendo procesado.',
    'instructions' => ['Recibirás confirmación por email cuando esté listo'],
    'typical_time' => $estimatedTime
];

$siteName = Settings::get('site_name', 'MiSistema');
$pageTitle = 'Pago Pendiente';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo htmlspecialchars($siteName); ?></title>
    
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="refresh" content="300"> <!-- Refrescar cada 5 minutos -->
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    
    <style>
        .pending-page {
            min-height: 80vh;
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .pending-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            color: #333;
            max-width: 700px;
            margin: 0 auto;
        }
        
        .pending-icon {
            font-size: 5rem;
            margin-bottom: 2rem;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.7; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        .order-summary {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 2rem;
            margin: 2rem 0;
        }
        
        .payment-info {
            background: #e8f4fd;
            border: 2px solid #bee5eb;
            border-radius: 15px;
            padding: 2rem;
            margin: 2rem 0;
        }
        
        .instructions {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 2rem 0;
        }
        
        .status-tracker {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            margin: 2rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 2rem;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -6px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #28a745;
        }
        
        .timeline-item.pending::before {
            background: #ffc107;
            animation: blink 1.5s infinite;
        }
        
        .timeline-item.future::before {
            background: #dee2e6;
        }
        
        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0.3; }
        }
        
        .refresh-notice {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 0.9rem;
            z-index: 1000;
        }
        
        .auto-refresh {
            font-size: 0.8rem;
            color: #6c757d;
            text-align: center;
            margin-top: 1rem;
        }
        
        .contact-support {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 2rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <!-- Refresh Notice -->
    <div class="refresh-notice" id="refresh-notice" style="display: none;">
        <i class="fas fa-sync-alt fa-spin me-2"></i>
        Verificando estado del pago...
    </div>
    
    <!-- Pending Page -->
    <div class="pending-page">
        <div class="container">
            <div class="pending-card text-center">
                <!-- Pending Icon -->
                <div class="pending-icon text-<?php echo $currentPayment['color']; ?>">
                    <i class="<?php echo $currentPayment['icon']; ?>"></i>
                </div>
                
                <!-- Title & Description -->
                <h1 class="display-4 mb-3">Pago en Proceso</h1>
                <p class="lead mb-4"><?php echo $currentPayment['description']; ?></p>
                
                <!-- Order Summary -->
                <div class="order-summary">
                    <h4 class="mb-3">
                        <i class="fas fa-receipt me-2"></i>
                        Orden #<?php echo htmlspecialchars($order['order_number']); ?>
                    </h4>
                    
                    <div class="row text-start">
                        <div class="col-md-6">
                            <div class="order-meta">
                                <strong>Cliente:</strong> <?php echo htmlspecialchars($order['customer_name']); ?><br>
                                <strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?><br>
                                <strong>Fecha:</strong> <?php echo formatDateTime($order['created_at']); ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="order-meta">
                                <strong>Método:</strong> <?php echo $currentPayment['name']; ?><br>
                                <strong>Total:</strong> <?php echo formatPrice($order['total_amount']); ?><br>
                                <strong>Items:</strong> <?php echo $order['items_count']; ?> producto(s)
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Info -->
                <div class="payment-info">
                    <h5 class="text-info mb-3">
                        <i class="fas fa-info-circle me-2"></i>
                        Información del Procesamiento
                    </h5>
                    <p><strong>Tiempo estimado:</strong> <?php echo $currentPayment['typical_time']; ?></p>
                    <p class="mb-0">Te notificaremos por email tan pronto como el pago sea confirmado.</p>
                </div>
                
                <!-- Status Tracker -->
                <div class="status-tracker">
                    <h5 class="mb-4">Estado del Proceso</h5>
                    <div class="timeline text-start">
                        <div class="timeline-item">
                            <h6>Orden Creada</h6>
                            <small class="text-muted">
                                <?php echo formatDateTime($order['created_at']); ?>
                            </small>
                        </div>
                        <div class="timeline-item">
                            <h6>Datos Enviados</h6>
                            <small class="text-muted">Información enviada al procesador</small>
                        </div>
                        <div class="timeline-item pending">
                            <h6>Verificando Pago</h6>
                            <small class="text-warning">
                                <i class="fas fa-spinner fa-spin me-1"></i>
                                En proceso...
                            </small>
                        </div>
                        <div class="timeline-item future">
                            <h6>Pago Confirmado</h6>
                            <small class="text-muted">Pendiente</small>
                        </div>
                        <div class="timeline-item future">
                            <h6>Productos Disponibles</h6>
                            <small class="text-muted">Pendiente</small>
                        </div>
                    </div>
                </div>
                
                <!-- Instructions -->
                <div class="instructions">
                    <h5 class="text-warning mb-3">
                        <i class="fas fa-list-check me-2"></i>
                        Qué Esperar
                    </h5>
                    <ul class="text-start">
                        <?php foreach ($currentPayment['instructions'] as $instruction): ?>
                            <li><?php echo $instruction; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <!-- Products Preview -->
                <div class="order-summary">
                    <h5 class="mb-3">Productos Incluidos</h5>
                    <?php foreach ($orderItems as $item): ?>
                        <div class="d-flex align-items-center mb-3 p-2 bg-white rounded">
                            <div class="me-3">
                                <?php if ($item['image']): ?>
                                    <img src="<?php echo UPLOADS_URL; ?>/products/<?php echo $item['image']; ?>" 
                                         alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                         style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">
                                <?php else: ?>
                                    <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                         style="width: 50px; height: 50px;">
                                        <i class="fas fa-box text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1 text-start">
                                <h6 class="mb-1"><?php echo htmlspecialchars($item['product_name']); ?></h6>
                                <small class="text-muted">
                                    <?php if ($item['is_free']): ?>
                                        Gratuito
                                    <?php else: ?>
                                        <?php echo formatPrice($item['price']); ?>
                                        <?php if ($item['quantity'] > 1): ?>
                                            x<?php echo $item['quantity']; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Action Buttons -->
                <div class="mt-4">
                    <button onclick="checkOrderStatus()" class="btn btn-primary btn-lg me-3">
                        <i class="fas fa-sync-alt me-2"></i>Verificar Estado
                    </button>
                    
                    <a href="<?php echo SITE_URL; ?>/productos" class="btn btn-outline-primary btn-lg">
                        <i class="fas fa-search me-2"></i>Seguir Comprando
                    </a>
                </div>
                
                <!-- Auto Refresh Notice -->
                <div class="auto-refresh">
                    <i class="fas fa-info-circle me-1"></i>
                    Esta página se actualiza automáticamente cada 5 minutos
                </div>
                
                <!-- Contact Support -->
                <div class="contact-support">
                    <h6 class="mb-3">¿El pago se está tardando más de lo esperado?</h6>
                    <p class="text-muted mb-3">
                        Si han pasado más de <?php echo $currentPayment['typical_time']; ?> desde tu pago, 
                        contacta a nuestro equipo de soporte.
                    </p>
                    <div class="row">
                        <div class="col-md-6">
                            <a href="<?php echo SITE_URL; ?>/contacto?order=<?php echo $order['order_number']; ?>" 
                               class="btn btn-outline-secondary w-100 mb-2">
                                <i class="fas fa-envelope me-2"></i>Contactar Soporte
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="https://wa.me/<?php echo Settings::get('whatsapp_number', ''); ?>?text=Hola,%20tengo%20una%20consulta%20sobre%20la%20orden%20<?php echo $order['order_number']; ?>" 
                               target="_blank" class="btn btn-outline-success w-100 mb-2">
                                <i class="fab fa-whatsapp me-2"></i>WhatsApp
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Security Notice -->
                <div class="alert alert-info mt-4">
                    <h6><i class="fas fa-shield-alt me-2"></i>Pago Seguro</h6>
                    <small>
                        Tu pago está siendo procesado de forma segura. No cierres esta ventana hasta recibir la confirmación.
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
    
    <script>
        let checkInterval;
        let refreshCountdown = 300; // 5 minutos
        
        document.addEventListener('DOMContentLoaded', function() {
            // Verificar estado cada 30 segundos
            checkInterval = setInterval(checkOrderStatus, 30000);
            
            // Countdown para refresh
            const countdownInterval = setInterval(() => {
                refreshCountdown--;
                if (refreshCountdown <= 0) {
                    showRefreshNotice();
                    location.reload();
                }
            }, 1000);
            
            // Log para analytics
            if (typeof gtag !== 'undefined') {
                gtag('event', 'payment_pending', {
                    'order_number': '<?php echo $order['order_number']; ?>',
                    'payment_method': '<?php echo $paymentMethod; ?>'
                });
            }
        });
        
        function checkOrderStatus() {
            showRefreshNotice();
            
            fetch('/api/orders/check_status.php?order=<?php echo $order['order_number']; ?>')
                .then(response => response.json())
                .then(data => {
                    hideRefreshNotice();
                    
                    if (data.success) {
                        if (data.status === 'completed') {
                            // Pago completado - redirigir a success
                            clearInterval(checkInterval);
                            window.location.href = '/pages/success.php?order=<?php echo $order['order_number']; ?>';
                        } else if (data.status === 'failed') {
                            // Pago fallido - redirigir a failed
                            clearInterval(checkInterval);
                            window.location.href = '/pages/failed.php?order=<?php echo $order['order_number']; ?>&reason=' + (data.reason || 'unknown');
                        }
                        // Si sigue pending, no hacer nada
                    }
                })
                .catch(error => {
                    hideRefreshNotice();
                    console.error('Error checking order status:', error);
                });
        }
        
        function showRefreshNotice() {
            document.getElementById('refresh-notice').style.display = 'block';
        }
        
        function hideRefreshNotice() {
            document.getElementById('refresh-notice').style.display = 'none';
        }
        
        // Prevenir que el usuario cierre accidentalmente
        window.addEventListener('beforeunload', function(e) {
            const message = '¿Estás seguro de que quieres salir? Tu pago todavía se está procesando.';
            e.returnValue = message;
            return message;
        });
        
        // Limpiar interval al salir
        window.addEventListener('unload', function() {
            if (checkInterval) {
                clearInterval(checkInterval);
            }
        });
    </script>
</body>
</html>