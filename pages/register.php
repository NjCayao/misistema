<?php
// pages/register.php - Sistema de registro actualizado con email integrado
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/settings.php';

// Verificar modo mantenimiento
if (Settings::get('maintenance_mode', '0') == '1' && !isAdmin()) {
    include '../maintenance.php';
    exit;
}

// Redirigir si ya está logueado
if (isLoggedIn()) {
    redirect('/dashboard');
}

$errors = [];
$success = '';

// Procesar formulario de registro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = sanitize($_POST['first_name'] ?? '');
    $lastName = sanitize($_POST['last_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $country = sanitize($_POST['country'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $acceptTerms = isset($_POST['accept_terms']);
    $newsletter = isset($_POST['newsletter']);

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

    if (empty($password)) {
        $errors[] = 'La contraseña es obligatoria';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'La contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Las contraseñas no coinciden';
    }

    if (!$acceptTerms) {
        $errors[] = 'Debes aceptar los términos y condiciones';
    }

    // Verificar si el email ya existe
    if (empty($errors)) {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                $errors[] = 'Este email ya está registrado';
            }
        } catch (Exception $e) {
            logError("Error verificando email existente: " . $e->getMessage());
            $errors[] = 'Error del sistema. Inténtalo más tarde';
        }
    }

    // Crear usuario si no hay errores
    if (empty($errors)) {
        try {
            $db = Database::getInstance()->getConnection();

            // Generar código de verificación
            $verificationCode = generateVerificationCode();
            $verificationExpires = date('Y-m-d H:i:s', strtotime('+' . VERIFICATION_CODE_EXPIRY . ' minutes'));

            // Hash de la contraseña
            $hashedPassword = hashPassword($password);

            $stmt = $db->prepare("
                INSERT INTO users (
                    first_name, last_name, email, phone, country, 
                    password, verification_code, verification_expires, 
                    is_verified, is_active, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 1, NOW())
            ");

            if ($stmt->execute([
                $firstName,
                $lastName,
                $email,
                $phone,
                $country,
                $hashedPassword,
                $verificationCode,
                $verificationExpires
            ])) {
                $userId = $db->lastInsertId();

                // Enviar email de verificación usando el nuevo sistema
                $emailSent = sendVerificationEmail($email, $firstName, $verificationCode);

                // Notificar al admin sobre nuevo usuario (si está habilitado)
                EmailSystem::notifyNewUser([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'country' => $country
                ]);

                if ($emailSent) {
                    setFlashMessage('success', 'Registro exitoso. Revisa tu email para verificar tu cuenta. <br> no olvides revisar la carpeta de spam.');
                    redirect('/verify-email?email=' . urlencode($email));
                } else {
                    setFlashMessage('warning', 'Registro exitoso. Hubo un problema enviando el email, pero puedes intentar verificar tu cuenta. <br> Si no recibes el email, contacta al soporte.');
                    redirect('/verify-email?email=' . urlencode($email));
                }
            } else {
                $errors[] = 'Error al crear la cuenta. Inténtalo más tarde';
            }
        } catch (Exception $e) {
            logError("Error en registro: " . $e->getMessage());
            $errors[] = 'Error del sistema. Inténtalo más tarde';
        }
    }
}

$siteName = Settings::get('site_name', 'MiSistema');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Cuenta - <?php echo htmlspecialchars($siteName); ?></title>

    <meta name="description" content="Crea tu cuenta gratis y accede a nuestros productos">
    <meta name="robots" content="noindex, follow">

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">

    <style>
        .auth-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
        }

        .auth-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            animation: float 20s infinite linear;
        }

        .auth-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            position: relative;
            z-index: 2;
        }

        .auth-header {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .auth-body {
            padding: 2rem;
        }

        .form-floating label {
            color: #6c757d;
        }

        .form-floating>.form-control:focus~label {
            color: #007bff;
        }

        .password-strength {
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
        }

        .strength-weak {
            background: #dc3545;
            width: 25%;
        }

        .strength-fair {
            background: #ffc107;
            width: 50%;
        }

        .strength-good {
            background: #28a745;
            width: 75%;
        }

        .strength-strong {
            background: #007bff;
            width: 100%;
        }

        .social-login {
            border-top: 1px solid #dee2e6;
            padding-top: 2rem;
            margin-top: 2rem;
        }

        .btn-social {
            width: 100%;
            margin-bottom: 0.5rem;
            padding: 0.75rem;
            border-radius: 8px;
            font-weight: 500;
        }

        .btn-google {
            background: #dd4b39;
            border-color: #dd4b39;
            color: white;
        }

        .btn-facebook {
            background: #3b5998;
            border-color: #3b5998;
            color: white;
        }

        .auth-footer {
            background: #f8f9fa;
            padding: 1.5rem 2rem;
            text-align: center;
            border-top: 1px solid #dee2e6;
        }

        @keyframes float {
            0% {
                transform: translateX(0);
            }

            100% {
                transform: translateX(-100px);
            }
        }
    </style>
</head>

<body>
    <div class="auth-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 col-xl-6">
                    <div class="auth-card">
                        <!-- Header -->
                        <div class="auth-header">
                            <h2 class="mb-2">
                                <i class="fas fa-user-plus me-2"></i>Crear Cuenta
                            </h2>
                            <p class="mb-0 opacity-75">Únete a nuestra comunidad de desarrolladores</p>
                        </div>

                        <!-- Body -->
                        <div class="auth-body">
                            <!-- Mostrar errores -->
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <!-- Formulario de Registro -->
                            <form method="POST" id="registerForm" novalidate>
                                <div class="row g-3">
                                    <!-- Nombre -->
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="first_name" name="first_name"
                                                value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                                                placeholder="Nombre" required>
                                            <label for="first_name">
                                                <i class="fas fa-user me-2"></i>Nombre
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Apellido -->
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="last_name" name="last_name"
                                                value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                                                placeholder="Apellido" required>
                                            <label for="last_name">
                                                <i class="fas fa-user me-2"></i>Apellido
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Email -->
                                    <div class="col-12">
                                        <div class="form-floating">
                                            <input type="email" class="form-control" id="email" name="email"
                                                value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                                placeholder="Email" required>
                                            <label for="email">
                                                <i class="fas fa-envelope me-2"></i>Email
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Teléfono -->
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="tel" class="form-control" id="phone" name="phone"
                                                value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                                                placeholder="Teléfono">
                                            <label for="phone">
                                                <i class="fas fa-phone me-2"></i>Teléfono (opcional)
                                            </label>
                                        </div>
                                    </div>

                                    <!-- País -->
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <select class="form-select" id="country" name="country">
                                                <option value="">Seleccionar país</option>
                                                <option value="PE" <?php echo ($_POST['country'] ?? '') === 'PE' ? 'selected' : ''; ?>>Perú</option>
                                                <option value="AR" <?php echo ($_POST['country'] ?? '') === 'AR' ? 'selected' : ''; ?>>Argentina</option>
                                                <option value="CL" <?php echo ($_POST['country'] ?? '') === 'CL' ? 'selected' : ''; ?>>Chile</option>
                                                <option value="CO" <?php echo ($_POST['country'] ?? '') === 'CO' ? 'selected' : ''; ?>>Colombia</option>
                                                <option value="EC" <?php echo ($_POST['country'] ?? '') === 'EC' ? 'selected' : ''; ?>>Ecuador</option>
                                                <option value="MX" <?php echo ($_POST['country'] ?? '') === 'MX' ? 'selected' : ''; ?>>México</option>
                                                <option value="ES" <?php echo ($_POST['country'] ?? '') === 'ES' ? 'selected' : ''; ?>>España</option>
                                                <option value="US" <?php echo ($_POST['country'] ?? '') === 'US' ? 'selected' : ''; ?>>Estados Unidos</option>
                                                <option value="OTHER" <?php echo ($_POST['country'] ?? '') === 'OTHER' ? 'selected' : ''; ?>>Otro</option>
                                            </select>
                                            <label for="country">
                                                <i class="fas fa-globe me-2"></i>País
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Contraseña -->
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="password" class="form-control" id="password" name="password"
                                                placeholder="Contraseña" required>
                                            <label for="password">
                                                <i class="fas fa-lock me-2"></i>Contraseña
                                            </label>
                                        </div>
                                        <div class="password-strength">
                                            <div class="password-strength-bar" id="strengthBar"></div>
                                        </div>
                                        <small id="passwordHelp" class="form-text text-muted">
                                            Mínimo <?php echo PASSWORD_MIN_LENGTH; ?> caracteres
                                        </small>
                                    </div>

                                    <!-- Confirmar Contraseña -->
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                                placeholder="Confirmar contraseña" required>
                                            <label for="confirm_password">
                                                <i class="fas fa-lock me-2"></i>Confirmar
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Términos y Newsletter -->
                                    <div class="col-12">
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="accept_terms" name="accept_terms" required>
                                            <label class="form-check-label" for="accept_terms">
                                                Acepto los <a href="/pages/page.php?slug=terminos-condiciones" target="_blank">términos y condiciones</a>
                                                y la <a href="/pages/page.php?slug=poltica-de-privacidad" target="_blank">política de privacidad</a>
                                            </label>
                                        </div>

                                        <div class="form-check mb-4">
                                            <input class="form-check-input" type="checkbox" id="newsletter" name="newsletter">
                                            <label class="form-check-label" for="newsletter">
                                                Quiero recibir noticias y ofertas por email
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Botón de Registro -->
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary btn-lg w-100">
                                            <i class="fas fa-user-plus me-2"></i>Crear Mi Cuenta
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Footer -->
                        <div class="auth-footer">
                            <p class="mb-0">
                                ¿Ya tienes cuenta? <a href="<?php echo SITE_URL; ?>/pages/login.php" class="text-decoration-none">Iniciar Sesión</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const strengthBar = document.getElementById('strengthBar');
            const passwordHelp = document.getElementById('passwordHelp');
            const form = document.getElementById('registerForm');

            // Validador de fuerza de contraseña
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                const strength = calculatePasswordStrength(password);

                // Actualizar barra de fuerza
                strengthBar.className = 'password-strength-bar';
                if (strength.score > 0) {
                    strengthBar.classList.add(`strength-${strength.level}`);
                }

                // Actualizar texto de ayuda
                passwordHelp.textContent = strength.message;
                passwordHelp.className = `form-text text-${strength.color}`;
            });

            // Validar confirmación de contraseña
            confirmPasswordInput.addEventListener('input', function() {
                const password = passwordInput.value;
                const confirm = this.value;

                if (confirm && password !== confirm) {
                    this.setCustomValidity('Las contraseñas no coinciden');
                    this.classList.add('is-invalid');
                } else {
                    this.setCustomValidity('');
                    this.classList.remove('is-invalid');
                    if (confirm) this.classList.add('is-valid');
                }
            });

            // Validación en tiempo real
            const inputs = form.querySelectorAll('input[required], select[required]');
            inputs.forEach(input => {
                input.addEventListener('blur', validateInput);
                input.addEventListener('input', validateInput);
            });

            function validateInput() {
                if (this.validity.valid) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            }

            // Envío del formulario
            form.addEventListener('submit', function(e) {
                const isValid = form.checkValidity();

                if (!isValid) {
                    e.preventDefault();
                    e.stopPropagation();
                }

                form.classList.add('was-validated');
            });
        });

        function calculatePasswordStrength(password) {
            let score = 0;
            let level = 'weak';
            let message = 'Muy débil';
            let color = 'danger';

            if (password.length >= <?php echo PASSWORD_MIN_LENGTH; ?>) score++;
            if (password.match(/[a-z]/)) score++;
            if (password.match(/[A-Z]/)) score++;
            if (password.match(/[0-9]/)) score++;
            if (password.match(/[^a-zA-Z0-9]/)) score++;

            switch (score) {
                case 0:
                case 1:
                    level = 'weak';
                    message = 'Muy débil';
                    color = 'danger';
                    break;
                case 2:
                    level = 'fair';
                    message = 'Débil';
                    color = 'warning';
                    break;
                case 3:
                    level = 'good';
                    message = 'Buena';
                    color = 'success';
                    break;
                case 4:
                case 5:
                    level = 'strong';
                    message = 'Muy fuerte';
                    color = 'primary';
                    break;
            }

            return {
                score,
                level,
                message,
                color
            };
        }
    </script>
</body>

</html>