<?php
// pages/success.php - Página de pago exitoso
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
$status = $_GET['status'] ?? 'completed';

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
        WHERE o.order_number = ?
    ");
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch();
    
    if (!$order) {
        setFlashMessage('error', 'Orden no encontrada');
        redirect(SITE_URL);
    }
    
    // Obtener items de la orden
    $stmt = $db->prepare("
        SELECT oi.*, p.slug, p.image, p.is_free, p.download_limit, p.update_months
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order['id']]);
    $orderItems = $stmt->fetchAll();
    
    // Obtener licencias del usuario si está logueado
    $userLicenses = [];
    if ($order['user_id']) {
        $stmt = $db->prepare("
            SELECT ul.*, p.name as product_name, p.slug
            FROM user_licenses ul
            JOIN products p ON ul.product_id = p.id
            WHERE ul.order_id = ?
        ");
        $stmt->execute([$order['id']]);
        $userLicenses = $stmt->fetchAll();
    }
    
} catch (Exception $e) {
    logError("Error en página success: " . $e->getMessage());
    setFlashMessage('error', 'Error al cargar la información de la orden');
    redirect(SITE_URL);
}

$siteName = Settings::get('site_name', 'MiSistema');
$pageTitle = 'Compra Exitosa';

// Determinar tipo de mensaje según status
$statusConfig = [
    'completed' => [
        'icon' => 'fas fa-check-circle',
        'color' => 'success',
        'title' => '¡Pago Exitoso!',
        'message' => 'Tu compra ha sido procesada correctamente.'
    ],
    'pending' => [
        'icon' => 'fas fa-clock',
        'color' => 'warning', 
        'title' => 'Pago Pendiente',
        'message' => 'Tu pago está siendo procesado. Te notificaremos cuando esté confirmado.'
    ],
    'free' => [
        'icon' => 'fas fa-gift',
        'color' => 'success',
        'title' => '¡Descarga Lista!',
        'message' => 'Tus productos gratuitos están listos para descargar.'
    ]
];

$currentStatus = $statusConfig[$status] ?? $statusConfig['completed'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo htmlspecialchars($siteName); ?></title>
    
    <meta name="robots" content="noindex, nofollow">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    
    <style>
        .success-page {
            min-height: 80vh;
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .success-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            color: #333;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .success-icon {
            font-size: 5rem;
            margin-bottom: 2rem;
        }
        
        .order-summary {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 2rem;
            margin: 2rem 0;
        }
        
        .download-section {
            background: #e8f5e8;
            border: 2px solid #28a745;
            border-radius: 15px;
            padding: 2rem;
            margin: 2rem 0;
        }
        
        .download-item {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 1rem 0;
            border: 1px solid #dee2e6;
            transition: all 0.3s ease;
        }
        
        .download-item:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .download-btn {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            color: white;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .download-btn:hover {
            background: linear-gradient(45deg, #20c997, #28a745);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
            color: white;
        }
        
        .next-steps {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 2rem 0;
        }
        
        .order-meta {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .status-pending {
            background: #fff3cd;
            border-color: #ffc107;
        }
        
        .confetti {
            position: fixed;
            width: 10px;
            height: 10px;
            background: #f39c12;
            animation: confetti-fall 3s linear infinite;
        }
        
        @keyframes confetti-fall {
            0% {
                transform: translateY(-100vh) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateY(100vh) rotate(720deg);
                opacity: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <!-- Success Page -->
    <div class="success-page">
        <div class="container">
            <div class="success-card text-center">
                <!-- Status Icon -->
                <div class="success-icon text-<?php echo $currentStatus['color']; ?>">
                    <i class="<?php echo $currentStatus['icon']; ?>"></i>
                </div>
                
                <!-- Title & Message -->
                <h1 class="display-4 mb-3"><?php echo $currentStatus['title']; ?></h1>
                <p class="lead mb-4"><?php echo $currentStatus['message']; ?></p>
                
                <!-- Order Info -->
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
                                <strong>Método de Pago:</strong> <?php echo ucfirst($order['payment_method']); ?><br>
                                <strong>Total:</strong> <?php echo formatPrice($order['total_amount']); ?><br>
                                <strong>Estado:</strong> 
                                <span class="badge bg-<?php echo $order['payment_status'] === 'completed' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($order['payment_status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Downloads Section -->
                <?php if ($order['payment_status'] === 'completed' || $order['total_amount'] == 0): ?>
                    <div class="download-section">
                        <h4 class="text-success mb-3">
                            <i class="fas fa-download me-2"></i>
                            Enlaces de Descarga
                        </h4>
                        <p class="mb-4">Tus productos están listos para descargar. Los enlaces estarán activos por tiempo limitado.</p>
                        
                        <?php foreach ($orderItems as $item): ?>
                            <div class="download-item">
                                <div class="row align-items-center">
                                    <div class="col-md-2 text-center">
                                        <?php if ($item['image']): ?>
                                            <img src="<?php echo UPLOADS_URL; ?>/products/<?php echo $item['image']; ?>" 
                                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                                 style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                                        <?php else: ?>
                                            <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                                 style="width: 60px; height: 60px;">
                                                <i class="fas fa-box text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($item['product_name']); ?></h6>
                                        <small class="text-muted">
                                            <?php if ($item['is_free']): ?>
                                                Producto gratuito
                                            <?php else: ?>
                                                <?php echo formatPrice($item['price']); ?>
                                                <?php if ($item['quantity'] > 1): ?>
                                                    x<?php echo $item['quantity']; ?>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </small>
                                        <br>
                                        <small class="text-success">
                                            <i class="fas fa-shield-alt me-1"></i>
                                            <?php echo $item['download_limit']; ?> descargas • 
                                            <?php echo $item['update_months']; ?> meses de actualizaciones
                                        </small>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <a href="<?php echo SITE_URL; ?>/download/<?php echo $item['product_id']; ?>?order=<?php echo $order['order_number']; ?>" 
                                           class="download-btn">
                                            <i class="fas fa-download me-2"></i>Descargar
                                        </a>
                                        <br>
                                        <a href="<?php echo SITE_URL; ?>/producto/<?php echo $item['slug']; ?>" 
                                           class="btn btn-outline-primary btn-sm mt-2">
                                            <i class="fas fa-info-circle me-1"></i>Ver Detalles
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- User Account Info -->
                <?php if (!$order['user_id']): ?>
                    <div class="next-steps">
                        <h5 class="text-warning mb-3">
                            <i class="fas fa-user-plus me-2"></i>
                            ¿Quieres crear una cuenta?
                        </h5>
                        <p>Crea una cuenta para gestionar tus compras, re-descargar productos y recibir actualizaciones.</p>
                        <a href="<?php echo SITE_URL; ?>/pages/register.php?email=<?php echo urlencode($order['customer_email']); ?>" 
                           class="btn btn-warning">
                            <i class="fas fa-user-plus me-2"></i>Crear Cuenta Gratis
                        </a>
                    </div>
                <?php else: ?>
                    <div class="next-steps">
                        <h5 class="text-info mb-3">
                            <i class="fas fa-user-circle me-2"></i>
                            Tu Cuenta
                        </h5>
                        <p>Puedes gestionar tus compras y descargas desde tu dashboard personal.</p>
                        <a href="<?php echo SITE_URL; ?>/pages/dashboard.php" class="btn btn-info">
                            <i class="fas fa-tachometer-alt me-2"></i>Ir al Dashboard
                        </a>
                    </div>
                <?php endif; ?>
                
                <!-- Email Notice -->
                <div class="alert alert-info mt-4">
                    <h6><i class="fas fa-envelope me-2"></i>Confirmación por Email</h6>
                    <p class="mb-0">
                        Hemos enviado un email de confirmación con los enlaces de descarga a 
                        <strong><?php echo htmlspecialchars($order['customer_email']); ?></strong>
                    </p>
                </div>
                
                <!-- Action Buttons -->
                <div class="mt-4">
                    <a href="<?php echo SITE_URL; ?>/productos" class="btn btn-primary btn-lg me-3">
                        <i class="fas fa-search me-2"></i>Explorar Más Productos
                    </a>
                    
                    <?php if ($order['user_id']): ?>
                        <a href="<?php echo SITE_URL; ?>/pages/dashboard.php?section=orders" class="btn btn-outline-primary btn-lg">
                            <i class="fas fa-history me-2"></i>Ver Mis Compras
                        </a>
                    <?php endif; ?>
                </div>
                
                <!-- Support -->
                <div class="mt-5 pt-4 border-top">
                    <p class="text-muted mb-2">¿Necesitas ayuda?</p>
                    <a href="<?php echo SITE_URL; ?>/contacto" class="btn btn-outline-secondary">
                        <i class="fas fa-life-ring me-2"></i>Contactar Soporte
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
    
    <!-- Confetti Animation for Success -->
    <?php if ($status === 'completed' || $status === 'free'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Crear confetti
            function createConfetti() {
                const colors = ['#f39c12', '#e74c3c', '#3498db', '#2ecc71', '#9b59b6', '#f1c40f'];
                
                for (let i = 0; i < 50; i++) {
                    setTimeout(() => {
                        const confetti = document.createElement('div');
                        confetti.className = 'confetti';
                        confetti.style.left = Math.random() * 100 + 'vw';
                        confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                        confetti.style.animationDuration = (Math.random() * 3 + 2) + 's';
                        confetti.style.animationDelay = Math.random() * 2 + 's';
                        
                        document.body.appendChild(confetti);
                        
                        setTimeout(() => {
                            confetti.remove();
                        }, 5000);
                    }, i * 100);
                }
            }
            
            // Ejecutar confetti al cargar
            createConfetti();
        });
        
        // Auto-limpiar URL después de 10 segundos (remover parámetros de pago)
        setTimeout(() => {
            if (window.location.search) {
                const url = window.location.pathname;
                window.history.replaceState({}, document.title, url);
            }
        }, 10000);
    </script>
    <?php endif; ?>
</body>
</html>