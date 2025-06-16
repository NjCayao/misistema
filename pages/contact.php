<?php
// pages/contact.php - Versión con protección anti-spam

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/settings.php';

// La sesión ya se inició en functions.php, no necesitamos iniciarla aquí

// Verificar modo mantenimiento
if (Settings::get('maintenance_mode', '0') == '1' && !isAdmin()) {
    include '../maintenance.php';
    exit;
}

// Meta información
$siteName = Settings::get('site_name', 'MiSistema');
$pageTitle = 'Contacto - ' . $siteName;
$meta_description = 'Ponte en contacto con ' . $siteName . '. ' . Settings::get('site_description', '');

// Variables para el formulario
$success = '';
$error = '';
$form_data = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'subject' => '',
    'message' => ''
];

// Verificar si el formulario está habilitado
$form_enabled = true;

// ================================
// FUNCIONES DE SEGURIDAD
// ================================

function generateCaptcha() {
    $num1 = rand(1, 10);
    $num2 = rand(1, 10);
    $_SESSION['captcha_answer'] = $num1 + $num2;
    return "$num1 + $num2 = ?";
}

function isValidEmailFormat($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function isDisposableEmail($email) {
    // Lista de dominios de correos temporales comunes
    $disposable_domains = [
        '10minutemail.com', 'tempmail.org', 'guerrillamail.com', 'mailinator.com',
        'yopmail.com', 'temp-mail.org', 'throwaway.email', 'getnada.com',
        'maildrop.cc', 'fakeinbox.com', 'spamgourmet.com', 'sharklasers.com',
        'guerrillamailblock.com', 'pokemail.net', 'spam4.me', 'bccto.me',
        'chacuo.net', 'dispostable.com', 'emailondeck.com', 'mytrashmail.com',
        // Agregar más según necesites
    ];
    
    $domain = strtolower(substr(strrchr($email, "@"), 1));
    return in_array($domain, $disposable_domains);
}

function checkEmailMXRecord($email) {
    $domain = substr(strrchr($email, "@"), 1);
    return checkdnsrr($domain, "MX");
}

function isSpamContent($text) {
    $spam_keywords = [
        'viagra', 'casino', 'lottery', 'winner', 'congratulations',
        'million dollars', 'nigerian prince', 'inheritance', 'bitcoin',
        'cryptocurrency', 'investment', 'loan', 'credit', 'mortgage',
        'make money', 'work from home', 'click here', 'limited time',
        'urgent', 'act now', 'free money', 'guaranteed', 'risk free'
    ];
    
    $text_lower = strtolower($text);
    foreach ($spam_keywords as $keyword) {
        if (strpos($text_lower, $keyword) !== false) {
            return true;
        }
    }
    return false;
}

function isRateLimited($ip) {
    // Permitir máximo 3 mensajes por hora por IP
    $max_attempts = 3;
    $time_window = 3600; // 1 hora
    
    if (!isset($_SESSION['contact_attempts'])) {
        $_SESSION['contact_attempts'] = [];
    }
    
    $current_time = time();
    $attempts = $_SESSION['contact_attempts'];
    
    // Limpiar intentos antiguos
    $attempts = array_filter($attempts, function($timestamp) use ($current_time, $time_window) {
        return ($current_time - $timestamp) < $time_window;
    });
    
    if (count($attempts) >= $max_attempts) {
        return true;
    }
    
    return false;
}

function recordAttempt() {
    if (!isset($_SESSION['contact_attempts'])) {
        $_SESSION['contact_attempts'] = [];
    }
    $_SESSION['contact_attempts'][] = time();
}

function logContactAttempt($email, $ip, $status, $reason = '') {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO contact_logs (email, ip_address, status, reason, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$email, $ip, $status, $reason]);
    } catch (Exception $e) {
        // Log error pero no mostrar al usuario
        error_log("Error logging contact attempt: " . $e->getMessage());
    }
}

// ================================
// PROCESAR FORMULARIO
// ================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $form_enabled) {
    try {
        $user_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // 1. VERIFICAR RATE LIMITING
        if (isRateLimited($user_ip)) {
            throw new Exception('Has enviado demasiados mensajes. Intenta nuevamente en una hora.');
        }
        
        // 2. VERIFICAR CAPTCHA
        $captcha_answer = intval($_POST['captcha'] ?? 0);
        if (!isset($_SESSION['captcha_answer']) || $captcha_answer !== $_SESSION['captcha_answer']) {
            throw new Exception('La respuesta del captcha es incorrecta.');
        }
        
        // 3. VERIFICAR HONEYPOT (campo oculto)
        if (!empty($_POST['website'])) {
            logContactAttempt('', $user_ip, 'blocked', 'honeypot_triggered');
            throw new Exception('Error de validación. Intenta nuevamente.');
        }
        
        // 4. OBTENER Y SANITIZAR DATOS
        $form_data = [
            'name' => sanitize($_POST['name'] ?? ''),
            'email' => sanitize($_POST['email'] ?? ''),
            'phone' => sanitize($_POST['phone'] ?? ''),
            'subject' => sanitize($_POST['subject'] ?? ''),
            'message' => sanitize($_POST['message'] ?? '')
        ];

        // 5. VALIDACIONES BÁSICAS
        if (empty($form_data['name']) || strlen($form_data['name']) < 2) {
            throw new Exception('El nombre debe tener al menos 2 caracteres.');
        }
        
        if (empty($form_data['email'])) {
            throw new Exception('El email es obligatorio.');
        }
        
        if (empty($form_data['subject']) || strlen($form_data['subject']) < 5) {
            throw new Exception('El asunto debe tener al menos 5 caracteres.');
        }
        
        if (empty($form_data['message']) || strlen($form_data['message']) < 10) {
            throw new Exception('El mensaje debe tener al menos 10 caracteres.');
        }
        
        // 6. VALIDACIONES DE EMAIL
        if (!isValidEmailFormat($form_data['email'])) {
            logContactAttempt($form_data['email'], $user_ip, 'blocked', 'invalid_email_format');
            throw new Exception('El formato del email no es válido.');
        }
        
        if (isDisposableEmail($form_data['email'])) {
            logContactAttempt($form_data['email'], $user_ip, 'blocked', 'disposable_email');
            throw new Exception('No se permiten correos temporales o desechables.');
        }
        
        if (!checkEmailMXRecord($form_data['email'])) {
            logContactAttempt($form_data['email'], $user_ip, 'blocked', 'invalid_mx_record');
            throw new Exception('El dominio del email no existe o no puede recibir correos.');
        }
        
        // 7. VERIFICAR CONTENIDO SPAM
        $full_content = $form_data['name'] . ' ' . $form_data['subject'] . ' ' . $form_data['message'];
        if (isSpamContent($full_content)) {
            logContactAttempt($form_data['email'], $user_ip, 'blocked', 'spam_content');
            throw new Exception('El mensaje contiene contenido no permitido.');
        }
        
        // 8. VERIFICAR LONGITUD MÁXIMA
        if (strlen($form_data['message']) > 2000) {
            throw new Exception('El mensaje es demasiado largo (máximo 2000 caracteres).');
        }
        
        // 9. TODO: ENVIAR EMAIL
        // Aquí implementar el envío de email real
        
        // 10. REGISTRAR INTENTO EXITOSO
        recordAttempt();
        logContactAttempt($form_data['email'], $user_ip, 'success', 'message_sent');
        
        // 11. LIMPIAR SESIÓN
        unset($_SESSION['captcha_answer']);
        
        $success = 'Gracias por contactarnos. Tu mensaje ha sido enviado exitosamente.';
        
        // Limpiar formulario
        $form_data = [
            'name' => '',
            'email' => '',
            'phone' => '',
            'subject' => '',
            'message' => ''
        ];
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        // Regenerar captcha en caso de error
        unset($_SESSION['captcha_answer']);
    }
}

// Generar nuevo captcha
$captcha_question = generateCaptcha();

// Obtener datos de contacto
$site_email = Settings::get('site_email', '');
$contact_phone = Settings::get('contact_phone', '');
$contact_address = Settings::get('contact_address', '');
$facebook_url = Settings::get('facebook_url', '');
$twitter_url = Settings::get('twitter_url', '');
$instagram_url = Settings::get('instagram_url', '');
$linkedin_url = Settings::get('linkedin_url', '');
$youtube_url = Settings::get('youtube_url', '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    
    <meta name="description" content="<?php echo htmlspecialchars($meta_description); ?>">
    <meta name="keywords" content="contacto, soporte, <?php echo htmlspecialchars($siteName); ?>">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    
    <style>
        .contact-hero {
            background: var(--primary-gradient);
            color: white;
            padding: 80px 0 60px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .contact-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            animation: float 20s infinite linear;
        }
        
        .contact-hero-content {
            position: relative;
            z-index: 2;
        }
        
        .contact-section {
            padding: 60px 0;
        }
        
        .contact-card {
            background: white;
            border-radius: 15px;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            height: 100%;
            overflow: hidden;
        }
        
        .contact-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }
        
        .contact-card-header {
            background: var(--primary-gradient);
            color: white;
            padding: 25px;
            text-align: center;
        }
        
        .contact-card-body {
            padding: 30px;
        }
        
        .contact-info-item {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background: var(--light-color);
            border-radius: 10px;
            transition: var(--transition);
        }
        
        .contact-info-item:hover {
            background: #e3f2fd;
            transform: translateX(5px);
        }
        
        .contact-icon {
            width: 50px;
            height: 50px;
            background: var(--primary-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .contact-details h6 {
            margin: 0;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .contact-details p {
            margin: 5px 0 0 0;
            color: var(--secondary-color);
        }
        
        .contact-details a {
            color: var(--primary-color);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .contact-details a:hover {
            color: var(--dark-color);
        }
        
        .form-floating {
            margin-bottom: 20px;
        }
        
        .form-floating .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            transition: var(--transition);
        }
        
        .form-floating .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        
        .btn-send {
            background: var(--primary-gradient);
            border: none;
            border-radius: 50px;
            padding: 15px 40px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .btn-send:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 123, 255, 0.3);
        }
        
        .btn-send::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s ease;
        }
        
        .btn-send:hover::before {
            left: 100%;
        }
        
        .social-links-contact {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
        }
        
        .social-link-contact {
            width: 50px;
            height: 50px;
            background: var(--light-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--secondary-color);
            font-size: 1.2rem;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .social-link-contact:hover {
            color: white;
            transform: translateY(-3px);
        }
        
        .social-link-contact.facebook:hover { background: #3b5998; }
        .social-link-contact.twitter:hover { background: #1da1f2; }
        .social-link-contact.instagram:hover { background: #e4405f; }
        .social-link-contact.linkedin:hover { background: #0077b5; }
        .social-link-contact.youtube:hover { background: #ff0000; }
        
        .alert-custom {
            border: none;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .alert-success-custom {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 5px solid #28a745;
        }
        
        .alert-danger-custom {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left: 5px solid #dc3545;
        }
        
        .captcha-container {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .captcha-question {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .security-info {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            color: #1565c0;
        }
        
        .honeypot {
            position: absolute;
            left: -9999px;
            width: 1px;
            height: 1px;
            opacity: 0;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <!-- Hero Section -->
    <section class="contact-hero">
        <div class="contact-hero-content">
            <div class="container">
                <h1 class="display-4 fw-bold mb-3">Contáctanos</h1>
                <p class="lead mb-0">¿Tienes alguna pregunta? Estamos aquí para ayudarte</p>
            </div>
        </div>
    </section>
    
    <!-- Breadcrumb -->
    <div class="container mt-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>">Inicio</a></li>
                <li class="breadcrumb-item active">Contacto</li>
            </ol>
        </nav>
    </div>
    
    <!-- Contact Section -->
    <section class="contact-section">
        <div class="container">
            <!-- Alertas -->
            <?php if ($success): ?>
                <div class="alert alert-success-custom alert-custom">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger-custom alert-custom">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <div class="row g-4">
                <!-- Información de Contacto -->
                <div class="col-lg-5">
                    <div class="contact-card">
                        <div class="contact-card-header">
                            <h3 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                Información de Contacto
                            </h3>
                        </div>
                        <div class="contact-card-body">
                            <?php if ($site_email): ?>
                                <div class="contact-info-item">
                                    <div class="contact-icon">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <div class="contact-details">
                                        <h6>Email</h6>
                                        <p>
                                            <a href="mailto:<?php echo htmlspecialchars($site_email); ?>">
                                                <?php echo htmlspecialchars($site_email); ?>
                                            </a>
                                        </p>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($contact_phone): ?>
                                <div class="contact-info-item">
                                    <div class="contact-icon">
                                        <i class="fas fa-phone"></i>
                                    </div>
                                    <div class="contact-details">
                                        <h6>Teléfono</h6>
                                        <p>
                                            <a href="tel:<?php echo htmlspecialchars($contact_phone); ?>">
                                                <?php echo htmlspecialchars($contact_phone); ?>
                                            </a>
                                        </p>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($contact_address): ?>
                                <div class="contact-info-item">
                                    <div class="contact-icon">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </div>
                                    <div class="contact-details">
                                        <h6>Dirección</h6>
                                        <p><?php echo nl2br(htmlspecialchars($contact_address)); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Redes Sociales -->
                            <?php if ($facebook_url || $twitter_url || $instagram_url || $linkedin_url || $youtube_url): ?>
                                <div class="mt-4">
                                    <h6 class="text-center mb-3">Síguenos en</h6>
                                    <div class="social-links-contact">
                                        <?php if ($facebook_url): ?>
                                            <a href="<?php echo htmlspecialchars($facebook_url); ?>" target="_blank" class="social-link-contact facebook">
                                                <i class="fab fa-facebook-f"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($twitter_url): ?>
                                            <a href="<?php echo htmlspecialchars($twitter_url); ?>" target="_blank" class="social-link-contact twitter">
                                                <i class="fab fa-twitter"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($instagram_url): ?>
                                            <a href="<?php echo htmlspecialchars($instagram_url); ?>" target="_blank" class="social-link-contact instagram">
                                                <i class="fab fa-instagram"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($linkedin_url): ?>
                                            <a href="<?php echo htmlspecialchars($linkedin_url); ?>" target="_blank" class="social-link-contact linkedin">
                                                <i class="fab fa-linkedin-in"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($youtube_url): ?>
                                            <a href="<?php echo htmlspecialchars($youtube_url); ?>" target="_blank" class="social-link-contact youtube">
                                                <i class="fab fa-youtube"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Formulario de Contacto -->
                <div class="col-lg-7">
                    <?php if ($form_enabled): ?>
                        <div class="contact-card">
                            <div class="contact-card-header">
                                <h3 class="mb-0">
                                    <i class="fas fa-paper-plane me-2"></i>
                                    Envíanos un Mensaje
                                </h3>
                            </div>
                            <div class="contact-card-body">
                                <!-- Información de seguridad -->
                                <div class="security-info">
                                    <i class="fas fa-shield-alt me-2"></i>
                                    <strong>Seguridad:</strong> Este formulario está protegido contra spam. 
                                    No se permiten correos temporales ni contenido promocional.
                                </div>
                                
                                <form method="post" id="contactForm">
                                    <!-- Honeypot (campo oculto para bots) -->
                                    <input type="text" name="website" class="honeypot" tabindex="-1" autocomplete="off">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="text" class="form-control" id="name" name="name" 
                                                       value="<?php echo htmlspecialchars($form_data['name']); ?>" 
                                                       placeholder="Tu nombre" required minlength="2" maxlength="50">
                                                <label for="name">Nombre *</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="email" class="form-control" id="email" name="email" 
                                                       value="<?php echo htmlspecialchars($form_data['email']); ?>" 
                                                       placeholder="tu@email.com" required maxlength="100">
                                                <label for="email">Email *</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="tel" class="form-control" id="phone" name="phone" 
                                                       value="<?php echo htmlspecialchars($form_data['phone']); ?>" 
                                                       placeholder="Teléfono" maxlength="20">
                                                <label for="phone">Teléfono (opcional)</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="text" class="form-control" id="subject" name="subject" 
                                                       value="<?php echo htmlspecialchars($form_data['subject']); ?>" 
                                                       placeholder="Asunto del mensaje" required minlength="5" maxlength="100">
                                                <label for="subject">Asunto *</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-floating">
                                        <textarea class="form-control" id="message" name="message" 
                                                  style="height: 150px" placeholder="Tu mensaje" required 
                                                  minlength="10" maxlength="2000"><?php echo htmlspecialchars($form_data['message']); ?></textarea>
                                        <label for="message">Mensaje *</label>
                                    </div>
                                    
                                    <!-- CAPTCHA -->
                                    <div class="captcha-container">
                                        <div class="captcha-question"><?php echo $captcha_question; ?></div>
                                        <input type="number" class="form-control d-inline-block" name="captcha" 
                                               style="width: 80px; margin: 0 auto;" required min="0" max="20">
                                        <small class="text-muted d-block mt-2">
                                            <i class="fas fa-info-circle"></i> Resuelve esta operación para verificar que eres humano
                                        </small>
                                    </div>
                                    
                                    <div class="text-center">
                                        <button type="submit" class="btn btn-primary btn-send">
                                            <i class="fas fa-paper-plane me-2"></i>
                                            Enviar Mensaje
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="contact-card">
                            <div class="contact-card-body text-center py-5">
                                <i class="fas fa-envelope-open-text fa-4x text-muted mb-4"></i>
                                <h4>Formulario no disponible</h4>
                                <p class="text-muted">Por favor, utiliza la información de contacto para comunicarte con nosotros.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('contactForm');
            
            if (form) {
                form.addEventListener('submit', function(e) {
                    // Validación del lado del cliente
                    const requiredFields = form.querySelectorAll('[required]');
                    let isValid = true;
                    
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            field.classList.add('is-invalid');
                            isValid = false;
                        } else {
                            field.classList.remove('is-invalid');
                        }
                    });
                    
                    if (!isValid) {
                        e.preventDefault();
                        alert('Por favor, completa todos los campos obligatorios.');
                        return false;
                    }
                    
                    // Validar email
                    const email = document.getElementById('email');
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(email.value)) {
                        e.preventDefault();
                        email.classList.add('is-invalid');
                        alert('Por favor, introduce un email válido.');
                        return false;
                    }
                    
                    // Validar que no sea un dominio temporal común (lado cliente)
                    const disposableDomains = ['10minutemail.com', 'tempmail.org', 'guerrillamail.com', 'mailinator.com', 'yopmail.com'];
                    const emailDomain = email.value.split('@')[1]?.toLowerCase();
                    if (disposableDomains.includes(emailDomain)) {
                        e.preventDefault();
                        email.classList.add('is-invalid');
                        alert('No se permiten correos temporales. Usa tu email personal.');
                        return false;
                    }
                    
                    // Validar captcha
                    const captcha = document.querySelector('input[name="captcha"]');
                    if (!captcha.value || captcha.value < 0 || captcha.value > 20) {
                        e.preventDefault();
                        captcha.classList.add('is-invalid');
                        alert('Por favor, resuelve correctamente la operación matemática.');
                        return false;
                    }
                });
                
                // Limpiar errores al escribir
                form.querySelectorAll('input, textarea').forEach(field => {
                    field.addEventListener('input', function() {
                        this.classList.remove('is-invalid');
                    });
                });
            }
        });
    </script>
</body>
</html>