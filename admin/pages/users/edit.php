<?php
// admin/pages/users/edit.php - Editar usuario
require_once '../../../config/database.php';
require_once '../../../config/constants.php';
require_once '../../../config/functions.php';
require_once '../../../config/settings.php';

// Verificar autenticación
if (!isAdmin()) {
    redirect(ADMIN_URL . '/login.php');
}

$userId = intval($_GET['id'] ?? 0);
$errors = [];
$success = '';

if ($userId <= 0) {
    setFlashMessage('error', 'Usuario no válido');
    redirect('index.php');
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener datos del usuario
    $stmt = $db->prepare("
        SELECT u.*, 
               (SELECT COUNT(*) FROM orders WHERE user_id = u.id AND payment_status = 'completed') as total_orders,
               (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE user_id = u.id AND payment_status = 'completed') as total_spent,
               (SELECT COUNT(*) FROM user_licenses WHERE user_id = u.id AND is_active = 1) as active_licenses
        FROM users u 
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        setFlashMessage('error', 'Usuario no encontrado');
        redirect('index.php');
    }
    
} catch (Exception $e) {
    logError("Error obteniendo usuario: " . $e->getMessage());
    setFlashMessage('error', 'Error al obtener los datos del usuario');
    redirect('index.php');
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = sanitize($_POST['first_name'] ?? '');
    $lastName = sanitize($_POST['last_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $country = sanitize($_POST['country'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $isVerified = isset($_POST['is_verified']) ? 1 : 0;
    $sendNotification = isset($_POST['send_notification']);
    
    // Validaciones
    if (empty($firstName)) {
        $errors[] = 'El nombre es obligatorio';
    }
    
    if (empty($lastName)) {
        $errors[] = 'El apellido es obligatorio';
    }
    
    if (empty($email) || !isValidEmail($email)) {
        $errors[] = 'Ingresa un email válido';
    }
    
    // Validar contraseña solo si se proporciona
    if (!empty($newPassword)) {
        if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
            $errors[] = 'La contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres';
        }
        
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'Las contraseñas no coinciden';
        }
    }
    
    // Verificar si el email ya existe (excepto el actual)
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $userId]);
            
            if ($stmt->fetch()) {
                $errors[] = 'Este email ya está registrado por otro usuario';
            }
        } catch (Exception $e) {
            logError("Error verificando email: " . $e->getMessage());
            $errors[] = 'Error del sistema. Inténtalo más tarde';
        }
    }
    
    // Actualizar usuario si no hay errores
    if (empty($errors)) {
        try {
            $updateFields = [
                'first_name = ?',
                'last_name = ?', 
                'email = ?',
                'phone = ?',
                'country = ?',
                'is_active = ?',
                'is_verified = ?',
                'updated_at = NOW()'
            ];
            
            $params = [$firstName, $lastName, $email, $phone, $country, $isActive, $isVerified];
            
            // Agregar contraseña si se proporcionó
            if (!empty($newPassword)) {
                $updateFields[] = 'password = ?';
                $params[] = hashPassword($newPassword);
            }
            
            $params[] = $userId;
            
            $stmt = $db->prepare("
                UPDATE users 
                SET " . implode(', ', $updateFields) . "
                WHERE id = ?
            ");
            
            if ($stmt->execute($params)) {
                // Enviar notificación si está seleccionado
                if ($sendNotification) {
                    $subject = "Actualización de cuenta";
                    $body = "
                    <h2>Hola $firstName,</h2>
                    <p>Tu cuenta ha sido actualizada por un administrador.</p>
                    <p><strong>Cambios realizados:</strong></p>
                    <ul>
                        <li>Información personal actualizada</li>
                        " . (!empty($newPassword) ? "<li>Contraseña cambiada</li>" : "") . "
                        <li>Estado: " . ($isActive ? "Activo" : "Inactivo") . "</li>
                        <li>Verificación: " . ($isVerified ? "Verificado" : "Pendiente") . "</li>
                    </ul>
                    " . (!empty($newPassword) ? "<p><strong>Nueva contraseña:</strong> $newPassword</p>" : "") . "
                    <p>Si tienes alguna pregunta, contacta con soporte.</p>
                    ";
                    
                    EmailSystem::sendEmail($email, $subject, $body, true);
                }
                
                setFlashMessage('success', "Usuario '$firstName $lastName' actualizado exitosamente.");
                redirect('view.php?id=' . $userId);
            } else {
                $errors[] = 'Error al actualizar el usuario';
            }
            
        } catch (Exception $e) {
            logError("Error actualizando usuario: " . $e->getMessage());
            $errors[] = 'Error del sistema. Inténtalo más tarde';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar Usuario | <?php echo getSetting('site_name', 'MiSistema'); ?></title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/plugins/fontawesome-free/css/all.min.css">
    <!-- AdminLTE -->
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
                            <h1 class="m-0">Editar Usuario: <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="index.php">Usuarios</a></li>
                                <li class="breadcrumb-item active">Editar</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <section class="content">
                <div class="container-fluid">
                    
                    <!-- Mensajes de error -->
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            <i class="icon fas fa-ban"></i>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <!-- Formulario -->
                    <form method="POST" id="editUserForm">
                        <div class="row">
                            <div class="col-lg-8">
                                <!-- Información Personal -->
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fas fa-user me-2"></i>Información Personal
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="first_name">Nombre *</label>
                                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                                           value="<?php echo htmlspecialchars($_POST['first_name'] ?? $user['first_name']); ?>" 
                                                           required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="last_name">Apellido *</label>
                                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                                           value="<?php echo htmlspecialchars($_POST['last_name'] ?? $user['last_name']); ?>" 
                                                           required>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="email">Email *</label>
                                                    <input type="email" class="form-control" id="email" name="email" 
                                                           value="<?php echo htmlspecialchars($_POST['email'] ?? $user['email']); ?>" 
                                                           required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="phone">Teléfono</label>
                                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                                           value="<?php echo htmlspecialchars($_POST['phone'] ?? $user['phone']); ?>">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="country">País</label>
                                            <select class="form-control" id="country" name="country">
                                                <option value="">Seleccionar país</option>
                                                <?php
                                                $selectedCountry = $_POST['country'] ?? $user['country'];
                                                $countries = [
                                                    'PE' => 'Perú', 'AR' => 'Argentina', 'CL' => 'Chile', 'CO' => 'Colombia',
                                                    'EC' => 'Ecuador', 'MX' => 'México', 'ES' => 'España', 'US' => 'Estados Unidos',
                                                    'OTHER' => 'Otro'
                                                ];
                                                foreach ($countries as $code => $name):
                                                ?>
                                                    <option value="<?php echo $code; ?>" <?php echo $selectedCountry === $code ? 'selected' : ''; ?>>
                                                        <?php echo $name; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Cambiar Contraseña -->
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fas fa-lock me-2"></i>Cambiar Contraseña
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i>
                                            Deja estos campos vacíos si no deseas cambiar la contraseña
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="new_password">Nueva Contraseña</label>
                                                    <input type="password" class="form-control" id="new_password" name="new_password">
                                                    <small class="text-muted">Mínimo <?php echo PASSWORD_MIN_LENGTH; ?> caracteres</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="confirm_password">Confirmar Nueva Contraseña</label>
                                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="generatePassword()">
                                            <i class="fas fa-key"></i> Generar Contraseña Automática
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-4">
                                <!-- Estadísticas del Usuario -->
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fas fa-chart-bar me-2"></i>Estadísticas
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <div class="description-block">
                                                    <h5 class="description-header text-info"><?php echo $user['total_orders']; ?></h5>
                                                    <span class="description-text">Compras</span>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="description-block">
                                                    <h5 class="description-header text-success"><?php echo formatPrice($user['total_spent']); ?></h5>
                                                    <span class="description-text">Gastado</span>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="description-block">
                                                    <h5 class="description-header text-warning"><?php echo $user['active_licenses']; ?></h5>
                                                    <span class="description-text">Licencias</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <hr>
                                        
                                        <div class="row">
                                            <div class="col-12">
                                                <strong>Registro:</strong> <?php echo formatDateTime($user['created_at']); ?><br>
                                                <strong>Último Login:</strong> 
                                                <?php if ($user['last_login']): ?>
                                                    <?php echo timeAgo($user['last_login']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Nunca</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Configuración -->
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fas fa-cogs me-2"></i>Configuración
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" 
                                                       <?php echo (isset($_POST['is_active']) ? $_POST['is_active'] : $user['is_active']) ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="is_active">Usuario Activo</label>
                                            </div>
                                            <small class="text-muted">El usuario puede iniciar sesión</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="is_verified" name="is_verified"
                                                       <?php echo (isset($_POST['is_verified']) ? $_POST['is_verified'] : $user['is_verified']) ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="is_verified">Email Verificado</label>
                                            </div>
                                            <small class="text-muted">Cuenta verificada o pendiente</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="send_notification" name="send_notification">
                                                <label class="custom-control-label" for="send_notification">Notificar Cambios</label>
                                            </div>
                                            <small class="text-muted">Enviar email con los cambios realizados</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Acciones -->
                                <div class="card">
                                    <div class="card-body">
                                        <button type="submit" class="btn btn-primary btn-block">
                                            <i class="fas fa-save me-2"></i>Guardar Cambios
                                        </button>
                                        <a href="view.php?id=<?php echo $userId; ?>" class="btn btn-info btn-block">
                                            <i class="fas fa-eye me-2"></i>Ver Usuario
                                        </a>
                                        <a href="index.php" class="btn btn-secondary btn-block">
                                            <i class="fas fa-arrow-left me-2"></i>Volver a la Lista
                                        </a>
                                    </div>
                                </div>
                                
                                <!-- Acciones Avanzadas -->
                                <div class="card card-warning">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fas fa-exclamation-triangle me-2"></i>Acciones Avanzadas
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        <button type="button" class="btn btn-warning btn-sm btn-block" onclick="resetPassword()">
                                            <i class="fas fa-key"></i> Enviar Reset de Contraseña
                                        </button>
                                        <button type="button" class="btn btn-info btn-sm btn-block" onclick="resendVerification()">
                                            <i class="fas fa-envelope"></i> Reenviar Verificación
                                        </button>
                                        <hr>
                                        <button type="button" class="btn btn-danger btn-sm btn-block" onclick="confirmDelete()">
                                            <i class="fas fa-trash"></i> Eliminar Usuario
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>

                </div>
            </section>
        </div>

        <!-- Footer -->
        <?php include '../../includes/footer.php'; ?>
    </div>

    <!-- Scripts -->
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/jquery/jquery.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/dist/js/adminlte.min.js"></script>
    
    <script>
        $(document).ready(function() {
            const newPasswordInput = $('#new_password');
            const confirmPasswordInput = $('#confirm_password');
            
            // Validar confirmación de contraseña
            confirmPasswordInput.on('input', function() {
                const password = newPasswordInput.val();
                const confirm = $(this).val();
                
                if (confirm && password !== confirm) {
                    $(this).addClass('is-invalid');
                } else {
                    $(this).removeClass('is-invalid');
                    if (confirm) $(this).addClass('is-valid');
                }
            });
        });
        
        function generatePassword() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789@#$%&*';
            let password = '';
            for (let i = 0; i < 12; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            
            $('#new_password').val(password);
            $('#confirm_password').val(password);
            $('#confirm_password').trigger('input');
            
            // Mostrar la contraseña generada
            alert('Contraseña generada: ' + password + '\n\nAsegúrate de guardar esta información antes de enviar el formulario.');
        }
        
        function resetPassword() {
            if (confirm('¿Enviar un email de recuperación de contraseña al usuario?')) {
                $.post('../../api/users/reset-password.php', {
                    user_id: <?php echo $userId; ?>
                }, function(response) {
                    if (response.success) {
                        alert('Email de recuperación enviado exitosamente');
                    } else {
                        alert('Error: ' + response.message);
                    }
                }).fail(function() {
                    alert('Error al enviar el email');
                });
            }
        }
        
        function resendVerification() {
            if (confirm('¿Reenviar email de verificación al usuario?')) {
                $.post('../../api/users/resend-verification.php', {
                    user_id: <?php echo $userId; ?>
                }, function(response) {
                    if (response.success) {
                        alert('Email de verificación enviado exitosamente');
                    } else {
                        alert('Error: ' + response.message);
                    }
                }).fail(function() {
                    alert('Error al enviar el email');
                });
            }
        }
        
        function confirmDelete() {
            if (confirm('¿Estás seguro de que deseas eliminar este usuario?\n\nEsta acción no se puede deshacer y eliminará:\n- Toda la información del usuario\n- Sus compras y licencias\n- Su historial completo')) {
                window.location.href = 'delete.php?id=<?php echo $userId; ?>&confirm=1';
            }
        }
    </script>
</body>
</html>