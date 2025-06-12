<?php
// admin/pages/config/email.php
require_once '../../../config/database.php';
require_once '../../../config/constants.php';
require_once '../../../config/functions.php';
require_once '../../../config/settings.php';

// Verificar autenticación
if (!isAdmin()) {
    redirect(ADMIN_URL . '/login.php');
}

$success = '';
$error = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['test_email'])) {
            // Enviar email de prueba
            $testEmail = sanitize($_POST['test_email_address']);
            if (empty($testEmail) || !isValidEmail($testEmail)) {
                throw new Exception('Email de prueba no válido');
            }

            // Aquí implementaremos el envío real más adelante
            $success = "Email de prueba enviado a $testEmail (simulado)";
        } else {
            // Guardar configuración
            $settings = [
                'smtp_enabled' => isset($_POST['smtp_enabled']) ? '1' : '0',
                'smtp_host' => sanitize($_POST['smtp_host'] ?? ''),
                'smtp_port' => intval($_POST['smtp_port'] ?? 587),
                'smtp_encryption' => sanitize($_POST['smtp_encryption'] ?? 'tls'),
                'smtp_username' => sanitize($_POST['smtp_username'] ?? ''),
                'smtp_password' => sanitize($_POST['smtp_password'] ?? ''),
                'from_email' => sanitize($_POST['from_email'] ?? ''),
                'from_name' => sanitize($_POST['from_name'] ?? ''),
                'reply_to_email' => sanitize($_POST['reply_to_email'] ?? ''),
                'reply_to_name' => sanitize($_POST['reply_to_name'] ?? ''),

                // Templates
                'welcome_email_subject' => sanitize($_POST['welcome_email_subject'] ?? ''),
                'welcome_email_template' => $_POST['welcome_email_template'] ?? '',
                'purchase_email_subject' => sanitize($_POST['purchase_email_subject'] ?? ''),
                'purchase_email_template' => $_POST['purchase_email_template'] ?? '',
                'donation_email_subject' => sanitize($_POST['donation_email_subject'] ?? ''),
                'donation_email_template' => $_POST['donation_email_template'] ?? '',
                'verification_email_subject' => sanitize($_POST['verification_email_subject'] ?? ''),
                'verification_email_template' => $_POST['verification_email_template'] ?? '',

                // Configuraciones adicionales
                'email_footer' => $_POST['email_footer'] ?? '',
                'email_notifications_enabled' => isset($_POST['email_notifications_enabled']) ? '1' : '0',
                'admin_notification_email' => sanitize($_POST['admin_notification_email'] ?? ''),
                'notify_new_orders' => isset($_POST['notify_new_orders']) ? '1' : '0',
                'notify_new_users' => isset($_POST['notify_new_users']) ? '1' : '0'
            ];

            // Validaciones
            if ($settings['smtp_enabled'] == '1') {
                if (empty($settings['smtp_host']) || empty($settings['smtp_username']) || empty($settings['smtp_password'])) {
                    throw new Exception('Para habilitar SMTP necesitas configurar host, usuario y contraseña');
                }
            }

            if (!empty($settings['from_email']) && !isValidEmail($settings['from_email'])) {
                throw new Exception('Email remitente no válido');
            }

            if (!empty($settings['reply_to_email']) && !isValidEmail($settings['reply_to_email'])) {
                throw new Exception('Email de respuesta no válido');
            }

            // Guardar configuraciones
            foreach ($settings as $key => $value) {
                Settings::set($key, $value);
            }

            $success = 'Configuración de email guardada exitosamente';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        logError("Error en configuración de email: " . $e->getMessage());
    }
}

// Obtener configuraciones actuales
$config = [
    'smtp_enabled' => Settings::get('smtp_enabled', '0'),
    'smtp_host' => Settings::get('smtp_host', ''),
    'smtp_port' => Settings::get('smtp_port', '587'),
    'smtp_encryption' => Settings::get('smtp_encryption', 'tls'),
    'smtp_username' => Settings::get('smtp_username', ''),
    'smtp_password' => Settings::get('smtp_password', ''),
    'from_email' => Settings::get('from_email', ''),
    'from_name' => Settings::get('from_name', getSetting('site_name', 'MiSistema')),
    'reply_to_email' => Settings::get('reply_to_email', ''),
    'reply_to_name' => Settings::get('reply_to_name', ''),

    // Templates
    'welcome_email_subject' => Settings::get('welcome_email_subject', 'Bienvenido a {SITE_NAME}'),
    'welcome_email_template' => Settings::get('welcome_email_template', 'Hola {USER_NAME},\n\nBienvenido a nuestra plataforma.\n\nSaludos,\nEl equipo de {SITE_NAME}'),
    'purchase_email_subject' => Settings::get('purchase_email_subject', 'Compra confirmada - Orden #{ORDER_NUMBER}'),
    'purchase_email_template' => Settings::get('purchase_email_template', 'Hola {USER_NAME},\n\nTu compra ha sido confirmada.\n\nOrden: {ORDER_NUMBER}\nTotal: {ORDER_TOTAL}\n\nEnlaces de descarga:\n{DOWNLOAD_LINKS}\n\nGracias por tu compra,\nEl equipo de {SITE_NAME}'),
    'donation_email_subject' => Settings::get('donation_email_subject', 'Gracias por tu donación'),
    'donation_email_template' => Settings::get('donation_email_template', 'Hola {USER_NAME},\n\n¡Muchas gracias por tu donación de {DONATION_AMOUNT}!\n\nTu apoyo nos ayuda a seguir creando software de calidad.\n\nCon gratitud,\nEl equipo de {SITE_NAME}'),
    'verification_email_subject' => Settings::get('verification_email_subject', 'Verificar tu cuenta - Código: {VERIFICATION_CODE}'),
    'verification_email_template' => Settings::get('verification_email_template', 'Hola {USER_NAME},\n\nTu código de verificación es: {VERIFICATION_CODE}\n\nEste código expira en 30 minutos.\n\nSaludos,\nEl equipo de {SITE_NAME}'),

    'email_footer' => Settings::get('email_footer', '<p>© ' . date('Y') . ' {SITE_NAME}. Todos los derechos reservados.</p><p>Si no deseas recibir estos emails, <a href="{UNSUBSCRIBE_LINK}">haz clic aquí</a>.</p>'),
    'email_notifications_enabled' => Settings::get('email_notifications_enabled', '1'),
    'admin_notification_email' => Settings::get('admin_notification_email', ''),
    'notify_new_orders' => Settings::get('notify_new_orders', '1'),
    'notify_new_users' => Settings::get('notify_new_users', '1')
];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configuración de Email | <?php echo getSetting('site_name', 'MiSistema'); ?></title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/dist/css/adminlte.min.css">
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">

        <!-- Navbar -->
        <?php include '../../includes/navbar.php'; ?>

        <!-- Sidebar -->
        <?php include '../../includes/sidebar.php'; ?>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Content Header -->
            <div class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0">Configuración de Email</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item">Configuración</li>
                                <li class="breadcrumb-item active">Email</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <section class="content">
                <div class="container-fluid">

                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            <i class="icon fas fa-check"></i> <?php echo $success; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            <i class="icon fas fa-ban"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Configuración SMTP -->
                        <div class="col-md-6">
                            <div class="card card-primary">
                                <div class="card-header">
                                    <h3 class="card-title">Configuración SMTP</h3>
                                    <div class="card-tools">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="smtp_enabled" name="smtp_enabled"
                                                <?php echo $config['smtp_enabled'] == '1' ? 'checked' : ''; ?> form="email_form">
                                            <label class="custom-control-label" for="smtp_enabled">Habilitar</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <form id="email_form" method="post">
                                        <div class="form-group">
                                            <label for="smtp_host">Servidor SMTP</label>
                                            <input type="text" class="form-control" id="smtp_host" name="smtp_host"
                                                value="<?php echo htmlspecialchars($config['smtp_host']); ?>"
                                                placeholder="smtp.gmail.com">
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="smtp_port">Puerto</label>
                                                    <input type="number" class="form-control" id="smtp_port" name="smtp_port"
                                                        value="<?php echo $config['smtp_port']; ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="smtp_encryption">Encriptación</label>
                                                    <select class="form-control" id="smtp_encryption" name="smtp_encryption">
                                                        <option value="tls" <?php echo $config['smtp_encryption'] == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                                        <option value="ssl" <?php echo $config['smtp_encryption'] == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                                        <option value="none" <?php echo $config['smtp_encryption'] == 'none' ? 'selected' : ''; ?>>Ninguna</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="smtp_username">Usuario SMTP</label>
                                            <input type="text" class="form-control" id="smtp_username" name="smtp_username"
                                                value="<?php echo htmlspecialchars($config['smtp_username']); ?>">
                                        </div>

                                        <div class="form-group">
                                            <label for="smtp_password">Contraseña SMTP</label>
                                            <input type="password" class="form-control" id="smtp_password" name="smtp_password"
                                                value="<?php echo htmlspecialchars($config['smtp_password']); ?>">
                                        </div>

                                        <div class="form-group">
                                            <label for="from_email">Email Remitente</label>
                                            <input type="email" class="form-control" id="from_email" name="from_email"
                                                value="<?php echo htmlspecialchars($config['from_email']); ?>">
                                        </div>

                                        <div class="form-group">
                                            <label for="from_name">Nombre Remitente</label>
                                            <input type="text" class="form-control" id="from_name" name="from_name"
                                                value="<?php echo htmlspecialchars($config['from_name']); ?>">
                                        </div>

                                        <div class="form-group">
                                            <label for="reply_to_email">Email de Respuesta</label>
                                            <input type="email" class="form-control" id="reply_to_email" name="reply_to_email"
                                                value="<?php echo htmlspecialchars($config['reply_to_email']); ?>">
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Test Email -->
                            <div class="card card-info">
                                <div class="card-header">
                                    <h3 class="card-title">Probar Configuración</h3>
                                </div>
                                <div class="card-body">
                                    <form method="post">
                                        <div class="form-group">
                                            <label for="test_email_address">Enviar email de prueba a:</label>
                                            <input type="email" class="form-control" id="test_email_address" name="test_email_address"
                                                placeholder="tu@email.com" required>
                                        </div>
                                        <button type="submit" name="test_email" class="btn btn-info">
                                            <i class="fas fa-paper-plane"></i> Enviar Prueba
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Templates de Email -->
                        <div class="col-md-6">
                            <div class="card card-success">
                                <div class="card-header">
                                    <h3 class="card-title">Templates de Email</h3>
                                </div>
                                <div class="card-body">
                                    <!-- Bienvenida -->
                                    <div class="form-group">
                                        <label for="welcome_email_subject">Asunto - Email de Bienvenida</label>
                                        <input type="text" class="form-control" id="welcome_email_subject" name="welcome_email_subject"
                                            value="<?php echo htmlspecialchars($config['welcome_email_subject']); ?>" form="email_form">
                                    </div>
                                    <div class="form-group">
                                        <label for="welcome_email_template">Template - Bienvenida</label>
                                        <textarea class="form-control" id="welcome_email_template" name="welcome_email_template"
                                            rows="3" form="email_form"><?php echo htmlspecialchars($config['welcome_email_template']); ?></textarea>
                                    </div>

                                    <!-- Compra -->
                                    <div class="form-group">
                                        <label for="purchase_email_subject">Asunto - Confirmación de Compra</label>
                                        <input type="text" class="form-control" id="purchase_email_subject" name="purchase_email_subject"
                                            value="<?php echo htmlspecialchars($config['purchase_email_subject']); ?>" form="email_form">
                                    </div>
                                    <div class="form-group">
                                        <label for="purchase_email_template">Template - Compra</label>
                                        <textarea class="form-control" id="purchase_email_template" name="purchase_email_template"
                                            rows="4" form="email_form"><?php echo htmlspecialchars($config['purchase_email_template']); ?></textarea>
                                    </div>

                                    <!-- Donación -->
                                    <div class="form-group">
                                        <label for="donation_email_subject">Asunto - Donación</label>
                                        <input type="text" class="form-control" id="donation_email_subject" name="donation_email_subject"
                                            value="<?php echo htmlspecialchars($config['donation_email_subject']); ?>" form="email_form">
                                    </div>
                                    <div class="form-group">
                                        <label for="donation_email_template">Template - Donación</label>
                                        <textarea class="form-control" id="donation_email_template" name="donation_email_template"
                                            rows="3" form="email_form"><?php echo htmlspecialchars($config['donation_email_template']); ?></textarea>
                                    </div>

                                    <!-- Verificación -->
                                    <div class="form-group">
                                        <label for="verification_email_subject">Asunto - Verificación</label>
                                        <input type="text" class="form-control" id="verification_email_subject" name="verification_email_subject"
                                            value="<?php echo htmlspecialchars($config['verification_email_subject']); ?>" form="email_form">
                                    </div>
                                    <div class="form-group">
                                        <label for="verification_email_template">Template - Verificación</label>
                                        <textarea class="form-control" id="verification_email_template" name="verification_email_template"
                                            rows="3" form="email_form"><?php echo htmlspecialchars($config['verification_email_template']); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Footer y Notificaciones -->
                        <div class="col-md-6">
                            <div class="card card-warning">
                                <div class="card-header">
                                    <h3 class="card-title">Footer de Emails</h3>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label for="email_footer">Footer HTML</label>
                                        <textarea class="form-control" id="email_footer" name="email_footer"
                                            rows="4" form="email_form"><?php echo htmlspecialchars($config['email_footer']); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card card-secondary">
                                <div class="card-header">
                                    <h3 class="card-title">Notificaciones Admin</h3>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="email_notifications_enabled"
                                                name="email_notifications_enabled" form="email_form"
                                                <?php echo $config['email_notifications_enabled'] == '1' ? 'checked' : ''; ?>>
                                            <label class="custom-control-label" for="email_notifications_enabled">Habilitar Notificaciones</label>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="admin_notification_email">Email para Notificaciones</label>
                                        <input type="email" class="form-control" id="admin_notification_email"
                                            name="admin_notification_email" form="email_form"
                                            value="<?php echo htmlspecialchars($config['admin_notification_email']); ?>">
                                    </div>

                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="notify_new_orders"
                                                name="notify_new_orders" form="email_form"
                                                <?php echo $config['notify_new_orders'] == '1' ? 'checked' : ''; ?>>
                                            <label class="custom-control-label" for="notify_new_orders">Notificar Nuevas Órdenes</label>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="notify_new_users"
                                                name="notify_new_users" form="email_form"
                                                <?php echo $config['notify_new_users'] == '1' ? 'checked' : ''; ?>>
                                            <label class="custom-control-label" for="notify_new_users">Notificar Nuevos Usuarios</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Variables Disponibles -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card card-info collapsed-card">
                                <div class="card-header">
                                    <h3 class="card-title">Variables Disponibles en Templates</h3>
                                    <div class="card-tools">
                                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <strong>Usuario:</strong>
                                            <ul class="list-unstyled">
                                                <li><code>{USER_NAME}</code></li>
                                                <li><code>{USER_EMAIL}</code></li>
                                                <li><code>{USER_ID}</code></li>
                                            </ul>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Sitio:</strong>
                                            <ul class="list-unstyled">
                                                <li><code>{SITE_NAME}</code></li>
                                                <li><code>{SITE_URL}</code></li>
                                                <li><code>{CONTACT_EMAIL}</code></li>
                                            </ul>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Orden:</strong>
                                            <ul class="list-unstyled">
                                                <li><code>{ORDER_NUMBER}</code></li>
                                                <li><code>{ORDER_TOTAL}</code></li>
                                                <li><code>{DOWNLOAD_LINKS}</code></li>
                                            </ul>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Otros:</strong>
                                            <ul class="list-unstyled">
                                                <li><code>{VERIFICATION_CODE}</code></li>
                                                <li><code>{DONATION_AMOUNT}</code></li>
                                                <li><code>{UNSUBSCRIBE_LINK}</code></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <button type="submit" form="email_form" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> Guardar Configuración de Email
                            </button>
                            <a href="../../index.php" class="btn btn-secondary ml-2">
                                <i class="fas fa-arrow-left"></i> Volver al Dashboard
                            </a>
                        </div>
                    </div>

                </div>
            </section>
        </div>

        <!-- Footer -->
        <?php include '../../includes/footer.php'; ?>
    </div>

    <!-- jQuery -->
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/jquery/jquery.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/dist/js/adminlte.min.js"></script>

    <script>
        $(document).ready(function() {
            // Habilitar/deshabilitar campos SMTP
            function toggleSMTPConfig() {
                const enabled = $('#smtp_enabled').is(':checked');
                $('#smtp_host, #smtp_port, #smtp_encryption, #smtp_username, #smtp_password').prop('disabled', !enabled);
            }

            $('#smtp_enabled').on('change', toggleSMTPConfig);
            toggleSMTPConfig();

            // Auto-completar algunos campos
            $('#smtp_host').on('change', function() {
                const host = $(this).val().toLowerCase();
                if (host.includes('gmail')) {
                    $('#smtp_port').val(587);
                    $('#smtp_encryption').val('tls');
                } else if (host.includes('outlook') || host.includes('hotmail')) {
                    $('#smtp_port').val(587);
                    $('#smtp_encryption').val('tls');
                }
            });
        });
    </script>
</body>

</html>