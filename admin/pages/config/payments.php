<?php
// admin/pages/config/payments.php
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
        $settings = [
            // Stripe
            'stripe_publishable_key' => sanitize($_POST['stripe_publishable_key'] ?? ''),
            'stripe_secret_key' => sanitize($_POST['stripe_secret_key'] ?? ''),
            'stripe_webhook_secret' => sanitize($_POST['stripe_webhook_secret'] ?? ''),
            'stripe_enabled' => isset($_POST['stripe_enabled']) ? '1' : '0',
            'stripe_commission' => floatval($_POST['stripe_commission'] ?? 3.5),
            'stripe_fixed_fee' => floatval($_POST['stripe_fixed_fee'] ?? 0.30),

            // PayPal
            'paypal_client_id' => sanitize($_POST['paypal_client_id'] ?? ''),
            'paypal_client_secret' => sanitize($_POST['paypal_client_secret'] ?? ''),
            'paypal_webhook_id' => sanitize($_POST['paypal_webhook_id'] ?? ''),
            'paypal_sandbox' => isset($_POST['paypal_sandbox']) ? '1' : '0',
            'paypal_enabled' => isset($_POST['paypal_enabled']) ? '1' : '0',
            'paypal_commission' => floatval($_POST['paypal_commission'] ?? 4.5),
            'paypal_fixed_fee' => floatval($_POST['paypal_fixed_fee'] ?? 0.25),

            // MercadoPago
            'mercadopago_public_key' => sanitize($_POST['mercadopago_public_key'] ?? ''),
            'mercadopago_access_token' => sanitize($_POST['mercadopago_access_token'] ?? ''),
            'mercadopago_webhook_secret' => sanitize($_POST['mercadopago_webhook_secret'] ?? ''),
            'mercadopago_sandbox' => isset($_POST['mercadopago_sandbox']) ? '1' : '0',
            'mercadopago_enabled' => isset($_POST['mercadopago_enabled']) ? '1' : '0',
            'mercadopago_commission' => floatval($_POST['mercadopago_commission'] ?? 5.2),
            'mercadopago_fixed_fee' => floatval($_POST['mercadopago_fixed_fee'] ?? 0.15),

            // Configuraciones generales
            'default_payment_method' => sanitize($_POST['default_payment_method'] ?? 'stripe'),
            'allow_multiple_payments' => isset($_POST['allow_multiple_payments']) ? '1' : '0',
            'payment_timeout' => intval($_POST['payment_timeout'] ?? 30),
            'auto_refund_failed' => isset($_POST['auto_refund_failed']) ? '1' : '0'
        ];

        // Validaciones
        $enabledGateways = 0;
        if ($settings['stripe_enabled'] == '1') {
            if (empty($settings['stripe_publishable_key']) || empty($settings['stripe_secret_key'])) {
                throw new Exception('Para habilitar Stripe necesitas las claves API');
            }
            $enabledGateways++;
        }

        if ($settings['paypal_enabled'] == '1') {
            if (empty($settings['paypal_client_id']) || empty($settings['paypal_client_secret'])) {
                throw new Exception('Para habilitar PayPal necesitas Client ID y Secret');
            }
            $enabledGateways++;
        }

        if ($settings['mercadopago_enabled'] == '1') {
            if (empty($settings['mercadopago_public_key']) || empty($settings['mercadopago_access_token'])) {
                throw new Exception('Para habilitar MercadoPago necesitas las claves API');
            }
            $enabledGateways++;
        }

        if ($enabledGateways == 0) {
            throw new Exception('Debes habilitar al menos una pasarela de pago');
        }

        // Guardar configuraciones
        foreach ($settings as $key => $value) {
            Settings::set($key, $value);
        }

        $success = 'Configuración de pagos guardada exitosamente';
    } catch (Exception $e) {
        $error = $e->getMessage();
        logError("Error en configuración de pagos: " . $e->getMessage());
    }
}

// Obtener configuraciones actuales
$config = [
    // Stripe
    'stripe_publishable_key' => Settings::get('stripe_publishable_key', ''),
    'stripe_secret_key' => Settings::get('stripe_secret_key', ''),
    'stripe_webhook_secret' => Settings::get('stripe_webhook_secret', ''),
    'stripe_enabled' => Settings::get('stripe_enabled', '0'),
    'stripe_commission' => Settings::get('stripe_commission', '3.5'),
    'stripe_fixed_fee' => Settings::get('stripe_fixed_fee', '0.30'),

    // PayPal
    'paypal_client_id' => Settings::get('paypal_client_id', ''),
    'paypal_client_secret' => Settings::get('paypal_client_secret', ''),
    'paypal_webhook_id' => Settings::get('paypal_webhook_id', ''),
    'paypal_sandbox' => Settings::get('paypal_sandbox', '1'),
    'paypal_enabled' => Settings::get('paypal_enabled', '0'),
    'paypal_commission' => Settings::get('paypal_commission', '4.5'),
    'paypal_fixed_fee' => Settings::get('paypal_fixed_fee', '0.25'),

    // MercadoPago
    'mercadopago_public_key' => Settings::get('mercadopago_public_key', ''),
    'mercadopago_access_token' => Settings::get('mercadopago_access_token', ''),
    'mercadopago_webhook_secret' => Settings::get('mercadopago_webhook_secret', ''),
    'mercadopago_sandbox' => Settings::get('mercadopago_sandbox', '1'),
    'mercadopago_enabled' => Settings::get('mercadopago_enabled', '0'),
    'mercadopago_commission' => Settings::get('mercadopago_commission', '5.2'),
    'mercadopago_fixed_fee' => Settings::get('mercadopago_fixed_fee', '0.15'),

    // Generales
    'default_payment_method' => Settings::get('default_payment_method', 'stripe'),
    'allow_multiple_payments' => Settings::get('allow_multiple_payments', '1'),
    'payment_timeout' => Settings::get('payment_timeout', '30'),
    'auto_refund_failed' => Settings::get('auto_refund_failed', '0')
];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configuración de Pagos | <?php echo getSetting('site_name', 'MiSistema'); ?></title>

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
                            <h1 class="m-0">Configuración de Pagos</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item">Configuración</li>
                                <li class="breadcrumb-item active">Pagos</li>
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

                    <form method="post">
                        <div class="row">

                            <!-- Stripe -->
                            <div class="col-md-12">
                                <div class="card card-primary">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fab fa-cc-stripe"></i> Stripe
                                        </h3>
                                        <div class="card-tools">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="stripe_enabled" name="stripe_enabled"
                                                    <?php echo $config['stripe_enabled'] == '1' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="stripe_enabled">Habilitar</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="stripe_publishable_key">Publishable Key</label>
                                                    <input type="text" class="form-control" id="stripe_publishable_key" name="stripe_publishable_key"
                                                        value="<?php echo htmlspecialchars($config['stripe_publishable_key']); ?>"
                                                        placeholder="pk_test_...">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="stripe_secret_key">Secret Key</label>
                                                    <input type="password" class="form-control" id="stripe_secret_key" name="stripe_secret_key"
                                                        value="<?php echo htmlspecialchars($config['stripe_secret_key']); ?>"
                                                        placeholder="sk_test_...">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="stripe_webhook_secret">Webhook Secret</label>
                                                    <input type="text" class="form-control" id="stripe_webhook_secret" name="stripe_webhook_secret"
                                                        value="<?php echo htmlspecialchars($config['stripe_webhook_secret']); ?>"
                                                        placeholder="whsec_...">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="stripe_commission">Comisión (%)</label>
                                                    <input type="number" class="form-control" id="stripe_commission" name="stripe_commission"
                                                        value="<?php echo $config['stripe_commission']; ?>" step="0.1" min="0" max="20">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="stripe_fixed_fee">Tarifa Fija ($)</label>
                                                    <input type="number" class="form-control" id="stripe_fixed_fee" name="stripe_fixed_fee"
                                                        value="<?php echo $config['stripe_fixed_fee']; ?>" step="0.01" min="0">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- PayPal -->
                            <div class="col-md-12">
                                <div class="card card-info">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fab fa-paypal"></i> PayPal
                                        </h3>
                                        <div class="card-tools">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="paypal_enabled" name="paypal_enabled"
                                                    <?php echo $config['paypal_enabled'] == '1' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="paypal_enabled">Habilitar</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="paypal_client_id">Client ID</label>
                                                    <input type="text" class="form-control" id="paypal_client_id" name="paypal_client_id"
                                                        value="<?php echo htmlspecialchars($config['paypal_client_id']); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="paypal_client_secret">Client Secret</label>
                                                    <input type="password" class="form-control" id="paypal_client_secret" name="paypal_client_secret"
                                                        value="<?php echo htmlspecialchars($config['paypal_client_secret']); ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="paypal_webhook_id">Webhook ID</label>
                                                    <input type="text" class="form-control" id="paypal_webhook_id" name="paypal_webhook_id"
                                                        value="<?php echo htmlspecialchars($config['paypal_webhook_id']); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="paypal_commission">Comisión (%)</label>
                                                    <input type="number" class="form-control" id="paypal_commission" name="paypal_commission"
                                                        value="<?php echo $config['paypal_commission']; ?>" step="0.1" min="0" max="20">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="paypal_fixed_fee">Tarifa Fija ($)</label>
                                                    <input type="number" class="form-control" id="paypal_fixed_fee" name="paypal_fixed_fee"
                                                        value="<?php echo $config['paypal_fixed_fee']; ?>" step="0.01" min="0">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="paypal_sandbox" name="paypal_sandbox"
                                                    <?php echo $config['paypal_sandbox'] == '1' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="paypal_sandbox">Modo Sandbox (Pruebas)</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- MercadoPago -->
                            <div class="col-md-12">
                                <div class="card card-warning">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fas fa-credit-card"></i> MercadoPago + Yape
                                        </h3>
                                        <div class="card-tools">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="mercadopago_enabled" name="mercadopago_enabled"
                                                    <?php echo $config['mercadopago_enabled'] == '1' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="mercadopago_enabled">Habilitar</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="mercadopago_public_key">Public Key</label>
                                                    <input type="text" class="form-control" id="mercadopago_public_key" name="mercadopago_public_key"
                                                        value="<?php echo htmlspecialchars($config['mercadopago_public_key']); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="mercadopago_access_token">Access Token</label>
                                                    <input type="password" class="form-control" id="mercadopago_access_token" name="mercadopago_access_token"
                                                        value="<?php echo htmlspecialchars($config['mercadopago_access_token']); ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="mercadopago_webhook_secret">Webhook Secret</label>
                                                    <input type="text" class="form-control" id="mercadopago_webhook_secret" name="mercadopago_webhook_secret"
                                                        value="<?php echo htmlspecialchars($config['mercadopago_webhook_secret']); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="mercadopago_commission">Comisión (%)</label>
                                                    <input type="number" class="form-control" id="mercadopago_commission" name="mercadopago_commission"
                                                        value="<?php echo $config['mercadopago_commission']; ?>" step="0.1" min="0" max="20">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="mercadopago_fixed_fee">Tarifa Fija ($)</label>
                                                    <input type="number" class="form-control" id="mercadopago_fixed_fee" name="mercadopago_fixed_fee"
                                                        value="<?php echo $config['mercadopago_fixed_fee']; ?>" step="0.01" min="0">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="mercadopago_sandbox" name="mercadopago_sandbox"
                                                    <?php echo $config['mercadopago_sandbox'] == '1' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="mercadopago_sandbox">Modo Sandbox (Pruebas)</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Configuración General -->
                            <div class="col-md-12">
                                <div class="card card-secondary">
                                    <div class="card-header">
                                        <h3 class="card-title">Configuración General</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="default_payment_method">Método de Pago por Defecto</label>
                                                    <select class="form-control" id="default_payment_method" name="default_payment_method">
                                                        <option value="stripe" <?php echo $config['default_payment_method'] == 'stripe' ? 'selected' : ''; ?>>Stripe</option>
                                                        <option value="paypal" <?php echo $config['default_payment_method'] == 'paypal' ? 'selected' : ''; ?>>PayPal</option>
                                                        <option value="mercadopago" <?php echo $config['default_payment_method'] == 'mercadopago' ? 'selected' : ''; ?>>MercadoPago</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="payment_timeout">Timeout de Pago (minutos)</label>
                                                    <input type="number" class="form-control" id="payment_timeout" name="payment_timeout"
                                                        value="<?php echo $config['payment_timeout']; ?>" min="5" max="120">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="allow_multiple_payments" name="allow_multiple_payments"
                                                    <?php echo $config['allow_multiple_payments'] == '1' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="allow_multiple_payments">Permitir Múltiples Métodos de Pago</label>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="auto_refund_failed" name="auto_refund_failed"
                                                    <?php echo $config['auto_refund_failed'] == '1' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="auto_refund_failed">Auto Reembolso en Pagos Fallidos</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Calculadora de Precios -->
                            <div class="col-md-12">
                                <div class="card card-success">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fas fa-calculator"></i> Calculadora de Precios
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="calc_desired_amount">Precio que quiero recibir ($)</label>
                                                    <input type="number" class="form-control" id="calc_desired_amount" step="0.01" min="0" placeholder="50.00">
                                                </div>
                                            </div>
                                            <div class="col-md-8">
                                                <label>Precio final por pasarela:</label>
                                                <div id="price_breakdown"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save"></i> Guardar Configuración de Pagos
                                </button>
                                <a href="../../index.php" class="btn btn-secondary ml-2">
                                    <i class="fas fa-arrow-left"></i> Volver al Dashboard
                                </a>
                            </div>
                        </div>
                    </form>

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
            // Calculadora de precios
            function calculatePrices() {
                const desiredAmount = parseFloat($('#calc_desired_amount').val()) || 0;
                if (desiredAmount <= 0) {
                    $('#price_breakdown').html('<p class="text-muted">Ingresa un monto para ver el cálculo</p>');
                    return;
                }

                const gateways = [{
                        name: 'Stripe',
                        commission: parseFloat($('#stripe_commission').val()) || 0,
                        fixedFee: parseFloat($('#stripe_fixed_fee').val()) || 0,
                        enabled: $('#stripe_enabled').is(':checked')
                    },
                    {
                        name: 'PayPal',
                        commission: parseFloat($('#paypal_commission').val()) || 0,
                        fixedFee: parseFloat($('#paypal_fixed_fee').val()) || 0,
                        enabled: $('#paypal_enabled').is(':checked')
                    },
                    {
                        name: 'MercadoPago',
                        commission: parseFloat($('#mercadopago_commission').val()) || 0,
                        fixedFee: parseFloat($('#mercadopago_fixed_fee').val()) || 0,
                        enabled: $('#mercadopago_enabled').is(':checked')
                    }
                ];

                let html = '<div class="row">';

                gateways.forEach(gateway => {
                    if (gateway.enabled) {
                        // Calcular precio final: (deseado + tarifa_fija) / (1 - comision/100)
                        const finalPrice = (desiredAmount + gateway.fixedFee) / (1 - gateway.commission / 100);
                        const totalFees = finalPrice - desiredAmount;

                        html += `
                    <div class="col-md-4">
                        <div class="small-box bg-light">
                            <div class="inner">
                                <h4>$${finalPrice.toFixed(2)}</h4>
                                <p>${gateway.name}</p>
                                <small>Comisión: $${totalFees.toFixed(2)}</small>
                            </div>
                        </div>
                    </div>
                `;
                    }
                });

                html += '</div>';
                $('#price_breakdown').html(html);
            }

            // Eventos para la calculadora
            $('#calc_desired_amount, input[name$="_commission"], input[name$="_fixed_fee"]').on('input', calculatePrices);
            $('input[name$="_enabled"]').on('change', calculatePrices);

            // Calcular al cargar
            calculatePrices();

            // Mostrar/ocultar configuraciones basado en si están habilitadas
            function toggleGatewayConfig() {
                $('input[name$="_enabled"]').each(function() {
                    const gateway = $(this).attr('name').replace('_enabled', '');
                    const card = $(this).closest('.card');
                    const inputs = card.find('input:not([name$="_enabled"])');

                    if ($(this).is(':checked')) {
                        inputs.prop('disabled', false);
                        card.removeClass('collapsed-card');
                    } else {
                        inputs.prop('disabled', true);
                    }
                });
            }

            $('input[name$="_enabled"]').on('change', toggleGatewayConfig);
            toggleGatewayConfig();
        });
    </script>
</body>

</html>