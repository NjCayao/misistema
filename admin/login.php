<?php
// admin/login.php
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../config/functions.php';

// Si ya está logueado, redirigir al dashboard
if (isAdmin()) {
    redirect(ADMIN_URL . '/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Por favor complete todos los campos';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id, username, password, full_name, is_active FROM admins WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $admin = $stmt->fetch();
            
            if ($admin && $admin['is_active'] && verifyPassword($password, $admin['password'])) {
                // Login exitoso
                $_SESSION[ADMIN_SESSION_NAME] = [
                    'id' => $admin['id'],
                    'username' => $admin['username'],
                    'full_name' => $admin['full_name']
                ];
                
                // Actualizar último login
                $updateStmt = $db->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$admin['id']]);
                
                redirect(ADMIN_URL . '/index.php');
            } else {
                $error = 'Credenciales incorrectas o cuenta inactiva';
            }
        } catch (Exception $e) {
            logError("Error en login admin: " . $e->getMessage());
            $error = 'Error interno del servidor';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login | <?php echo getSetting('site_name', 'MiSistema'); ?></title>
    
    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="../vendor/adminlte/plugins/fontawesome-free/css/all.min.css">
    <!-- icheck bootstrap -->
    <link rel="stylesheet" href="../vendor/adminlte/plugins/icheck-bootstrap/icheck-bootstrap.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="../vendor/adminlte/dist/css/adminlte.min.css">
    
    <style>
        .login-box {
            margin: 7% auto;
        }
        .login-logo {
            font-size: 2rem;
            font-weight: 300;
        }
        .login-card-body {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .btn-primary {
            background: linear-gradient(45deg, #007bff, #0056b3);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(45deg, #0056b3, #004085);
        }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
    </style>
</head>
<body class="hold-transition login-page">
<div class="login-box">
    <div class="login-logo">
        <a href="#" style="color: #fff;">
            <b>Mi</b>Sistema
        </a>
    </div>
    
    <div class="card">
        <div class="card-body login-card-body">
            <p class="login-box-msg">Iniciar sesión como administrador</p>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <i class="icon fas fa-ban"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="input-group mb-3">
                    <input type="text" 
                           class="form-control" 
                           placeholder="Usuario o Email"
                           name="username" 
                           value="<?php echo htmlspecialchars($username ?? ''); ?>"
                           required>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-user"></span>
                        </div>
                    </div>
                </div>
                
                <div class="input-group mb-3">
                    <input type="password" 
                           class="form-control" 
                           placeholder="Contraseña"
                           name="password" 
                           required>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-lock"></span>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-8">
                        <div class="icheck-primary">
                            <input type="checkbox" id="remember">
                            <label for="remember">
                                Recordarme
                            </label>
                        </div>
                    </div>
                    <div class="col-4">
                        <button type="submit" class="btn btn-primary btn-block">Entrar</button>
                    </div>
                </div>
            </form>
            
            <p class="mb-1 mt-3">
                <a href="#" class="text-muted" style="font-size: 0.9rem;">
                    ¿Olvidaste tu contraseña?
                </a>
            </p>
        </div>
    </div>
</div>

<!-- jQuery -->
<script src="../vendor/adminlte/plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="../vendor/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="../vendor/adminlte/dist/js/adminlte.min.js"></script>

<script>
$(document).ready(function() {
    // Auto-focus en el primer campo
    $('input[name="username"]').focus();
    
    // Enter para enviar formulario
    $(document).keypress(function(e) {
        if(e.which == 13) {
            $('form').submit();
        }
    });
});
</script>
</body>
</html>