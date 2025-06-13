<?php
// admin/pages/config/general.php
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

// Manejar mensajes de éxito de redirecciones (Patrón PRG)
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'saved':
            $success = 'Configuración guardada exitosamente';
            break;
        case 'logo-updated':
            $success = 'Logo actualizado exitosamente';
            break;
        case 'favicon-updated':
            $success = 'Favicon actualizado exitosamente';
            break;
        case 'images-updated':
            $success = 'Imágenes actualizadas exitosamente';
            break;
    }
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Manejar subida de logo
        if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
            $logoResult = uploadFile($_FILES['site_logo'], UPLOADS_PATH . '/logos');
            if ($logoResult['success']) {
                // Eliminar logo anterior si existe
                $oldLogo = Settings::get('site_logo', '');
                if ($oldLogo && file_exists(UPLOADS_PATH . '/logos/' . $oldLogo)) {
                    unlink(UPLOADS_PATH . '/logos/' . $oldLogo);
                }
                
                Settings::set('site_logo', $logoResult['filename']);
                redirect($_SERVER['PHP_SELF'] . '?success=logo-updated');
            } else {
                throw new Exception('Error al subir logo: ' . $logoResult['message']);
            }
        }

        // Manejar subida de favicon
        if (isset($_FILES['site_favicon']) && $_FILES['site_favicon']['error'] === UPLOAD_ERR_OK) {
            $faviconResult = uploadFile($_FILES['site_favicon'], UPLOADS_PATH . '/logos');
            if ($faviconResult['success']) {
                // Eliminar favicon anterior si existe
                $oldFavicon = Settings::get('site_favicon', '');
                if ($oldFavicon && file_exists(UPLOADS_PATH . '/logos/' . $oldFavicon)) {
                    unlink(UPLOADS_PATH . '/logos/' . $oldFavicon);
                }
                
                Settings::set('site_favicon', $faviconResult['filename']);
                redirect($_SERVER['PHP_SELF'] . '?success=favicon-updated');
            } else {
                throw new Exception('Error al subir favicon: ' . $faviconResult['message']);
            }
        }

        // Manejar subida de ambas imágenes
        $logoUploaded = false;
        $faviconUploaded = false;

        if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
            $logoResult = uploadFile($_FILES['site_logo'], UPLOADS_PATH . '/logos');
            if ($logoResult['success']) {
                $oldLogo = Settings::get('site_logo', '');
                if ($oldLogo && file_exists(UPLOADS_PATH . '/logos/' . $oldLogo)) {
                    unlink(UPLOADS_PATH . '/logos/' . $oldLogo);
                }
                Settings::set('site_logo', $logoResult['filename']);
                $logoUploaded = true;
            } else {
                throw new Exception('Error al subir logo: ' . $logoResult['message']);
            }
        }

        if (isset($_FILES['site_favicon']) && $_FILES['site_favicon']['error'] === UPLOAD_ERR_OK) {
            $faviconResult = uploadFile($_FILES['site_favicon'], UPLOADS_PATH . '/logos');
            if ($faviconResult['success']) {
                $oldFavicon = Settings::get('site_favicon', '');
                if ($oldFavicon && file_exists(UPLOADS_PATH . '/logos/' . $oldFavicon)) {
                    unlink(UPLOADS_PATH . '/logos/' . $oldFavicon);
                }
                Settings::set('site_favicon', $faviconResult['filename']);
                $faviconUploaded = true;
            } else {
                throw new Exception('Error al subir favicon: ' . $faviconResult['message']);
            }
        }

        // Solo procesar configuraciones de texto si no hay archivos subidos o si ya se procesaron
        $settings = [
            'site_name' => sanitize($_POST['site_name'] ?? ''),
            'site_description' => sanitize($_POST['site_description'] ?? ''),
            'site_email' => sanitize($_POST['site_email'] ?? ''),
            'contact_phone' => sanitize($_POST['contact_phone'] ?? ''),
            'contact_address' => sanitize($_POST['contact_address'] ?? ''),
            'facebook_url' => sanitize($_POST['facebook_url'] ?? ''),
            'twitter_url' => sanitize($_POST['twitter_url'] ?? ''),
            'instagram_url' => sanitize($_POST['instagram_url'] ?? ''),
            'youtube_url' => sanitize($_POST['youtube_url'] ?? ''),
            'linkedin_url' => sanitize($_POST['linkedin_url'] ?? ''),
            'site_keywords' => sanitize($_POST['site_keywords'] ?? ''),
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
            'maintenance_message' => sanitize($_POST['maintenance_message'] ?? ''),
            'timezone' => sanitize($_POST['timezone'] ?? 'America/Lima'),
            'currency' => sanitize($_POST['currency'] ?? 'USD'),
            'currency_symbol' => sanitize($_POST['currency_symbol'] ?? '$')
        ];

        // Validaciones
        if (empty($settings['site_name'])) {
            throw new Exception('El nombre del sitio es obligatorio');
        }

        if (!empty($settings['site_email']) && !isValidEmail($settings['site_email'])) {
            throw new Exception('El email del sitio no es válido');
        }

        // Guardar configuraciones
        foreach ($settings as $key => $value) {
            Settings::set($key, $value);
        }

        // Redirigir según lo que se actualizó
        if ($logoUploaded && $faviconUploaded) {
            redirect($_SERVER['PHP_SELF'] . '?success=images-updated');
        } elseif ($logoUploaded) {
            redirect($_SERVER['PHP_SELF'] . '?success=logo-updated');
        } elseif ($faviconUploaded) {
            redirect($_SERVER['PHP_SELF'] . '?success=favicon-updated');
        } else {
            redirect($_SERVER['PHP_SELF'] . '?success=saved');
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
        logError("Error en configuración general: " . $e->getMessage());
    }
}

// Obtener configuraciones actuales
$config = [
    'site_name' => Settings::get('site_name', 'MiSistema'),
    'site_description' => Settings::get('site_description', ''),
    'site_email' => Settings::get('site_email', ''),
    'contact_phone' => Settings::get('contact_phone', ''),
    'contact_address' => Settings::get('contact_address', ''),
    'facebook_url' => Settings::get('facebook_url', ''),
    'twitter_url' => Settings::get('twitter_url', ''),
    'instagram_url' => Settings::get('instagram_url', ''),
    'youtube_url' => Settings::get('youtube_url', ''),
    'linkedin_url' => Settings::get('linkedin_url', ''),
    'site_keywords' => Settings::get('site_keywords', ''),
    'maintenance_mode' => Settings::get('maintenance_mode', '0'),
    'maintenance_message' => Settings::get('maintenance_message', 'Sitio en mantenimiento'),
    'timezone' => Settings::get('timezone', 'America/Lima'),
    'currency' => Settings::get('currency', 'USD'),
    'currency_symbol' => Settings::get('currency_symbol', '$'),
    'site_logo' => Settings::get('site_logo', ''),
    'site_favicon' => Settings::get('site_favicon', '')
];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configuración General | <?php echo $config['site_name']; ?></title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/dist/css/adminlte.min.css">
    
    <style>
        .current-image-preview, .new-image-preview {
            padding: 15px;
            border: 1px solid #e3e6f0;
            border-radius: 8px;
            background-color: #f8f9fc;
        }

        .new-image-preview {
            border-color: #36b9cc;
            background-color: #e7f9fc;
        }

        .no-image-placeholder {
            text-align: center;
            padding: 20px;
            border: 2px dashed #d1d3e2;
            border-radius: 8px;
            background-color: #ffffff;
        }

        .image-container {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .image-container img {
            border: 2px solid #dee2e6;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
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
                            <h1 class="m-0">Configuración General</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
                                <li class="breadcrumb-item">Configuración</li>
                                <li class="breadcrumb-item active">General</li>
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

                    <form method="post" enctype="multipart/form-data">
                        <div class="row">
                            <!-- Información Básica -->
                            <div class="col-md-6">
                                <div class="card card-primary">
                                    <div class="card-header">
                                        <h3 class="card-title">Información Básica</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label for="site_name">Nombre del Sitio *</label>
                                            <input type="text" class="form-control" id="site_name" name="site_name"
                                                value="<?php echo htmlspecialchars($config['site_name']); ?>" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="site_description">Descripción del Sitio</label>
                                            <textarea class="form-control" id="site_description" name="site_description" rows="3"
                                                placeholder="Breve descripción de tu plataforma"><?php echo htmlspecialchars($config['site_description']); ?></textarea>
                                        </div>

                                        <div class="form-group">
                                            <label for="site_keywords">Palabras Clave (SEO)</label>
                                            <input type="text" class="form-control" id="site_keywords" name="site_keywords"
                                                value="<?php echo htmlspecialchars($config['site_keywords']); ?>"
                                                placeholder="software, sistemas, php, desarrollo">
                                        </div>

                                        <div class="form-group">
                                            <label for="timezone">Zona Horaria</label>
                                            <select class="form-control" id="timezone" name="timezone">
                                                <option value="America/Lima" <?php echo $config['timezone'] == 'America/Lima' ? 'selected' : ''; ?>>Lima (UTC-5)</option>
                                                <option value="America/Mexico_City" <?php echo $config['timezone'] == 'America/Mexico_City' ? 'selected' : ''; ?>>Ciudad de México (UTC-6)</option>
                                                <option value="America/Bogota" <?php echo $config['timezone'] == 'America/Bogota' ? 'selected' : ''; ?>>Bogotá (UTC-5)</option>
                                                <option value="America/Argentina/Buenos_Aires" <?php echo $config['timezone'] == 'America/Argentina/Buenos_Aires' ? 'selected' : ''; ?>>Buenos Aires (UTC-3)</option>
                                                <option value="Europe/Madrid" <?php echo $config['timezone'] == 'Europe/Madrid' ? 'selected' : ''; ?>>Madrid (UTC+1)</option>
                                            </select>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="currency">Moneda</label>
                                                    <select class="form-control" id="currency" name="currency">
                                                        <option value="USD" <?php echo $config['currency'] == 'USD' ? 'selected' : ''; ?>>Dólar (USD)</option>
                                                        <option value="EUR" <?php echo $config['currency'] == 'EUR' ? 'selected' : ''; ?>>Euro (EUR)</option>
                                                        <option value="PEN" <?php echo $config['currency'] == 'PEN' ? 'selected' : ''; ?>>Sol Peruano (PEN)</option>
                                                        <option value="MXN" <?php echo $config['currency'] == 'MXN' ? 'selected' : ''; ?>>Peso Mexicano (MXN)</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="currency_symbol">Símbolo</label>
                                                    <input type="text" class="form-control" id="currency_symbol" name="currency_symbol"
                                                        value="<?php echo htmlspecialchars($config['currency_symbol']); ?>" placeholder="$">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Contacto -->
                            <div class="col-md-6">
                                <div class="card card-info">
                                    <div class="card-header">
                                        <h3 class="card-title">Información de Contacto</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label for="site_email">Email de Contacto</label>
                                            <input type="email" class="form-control" id="site_email" name="site_email"
                                                value="<?php echo htmlspecialchars($config['site_email']); ?>">
                                        </div>

                                        <div class="form-group">
                                            <label for="contact_phone">Teléfono</label>
                                            <input type="text" class="form-control" id="contact_phone" name="contact_phone"
                                                value="<?php echo htmlspecialchars($config['contact_phone']); ?>">
                                        </div>

                                        <div class="form-group">
                                            <label for="contact_address">Dirección</label>
                                            <textarea class="form-control" id="contact_address" name="contact_address" rows="3"><?php echo htmlspecialchars($config['contact_address']); ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Logotipos -->
                                <div class="card card-warning">
                                    <div class="card-header">
                                        <h3 class="card-title">Logotipos</h3>
                                    </div>
                                    <div class="card-body">
                                        <!-- Logo del Sitio -->
                                        <div class="form-group">
                                            <label for="site_logo">Logo del Sitio</label>
                                            
                                            <!-- Vista previa actual -->
                                            <?php if ($config['site_logo']): ?>
                                                <div class="current-image-preview mb-3">
                                                    <label class="text-muted">Imagen actual:</label>
                                                    <div class="image-container">
                                                        <img src="<?php echo UPLOADS_URL; ?>/logos/<?php echo htmlspecialchars($config['site_logo']); ?>" 
                                                             alt="Logo actual" class="img-thumbnail" style="max-width: 200px; max-height: 100px;">
                                                        <small class="d-block text-muted mt-1"><?php echo htmlspecialchars($config['site_logo']); ?></small>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="current-image-preview mb-3">
                                                    <div class="no-image-placeholder">
                                                        <i class="fas fa-image fa-3x text-muted"></i>
                                                        <p class="text-muted">No hay logo configurado</p>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Input para nueva imagen -->
                                            <input type="file" class="form-control-file" id="site_logo" name="site_logo" accept="image/*">
                                            <small class="text-muted">Formatos: JPG, PNG, GIF. Máximo 2MB. Recomendado: 200x50px</small>
                                            
                                            <!-- Vista previa de nueva imagen -->
                                            <div id="logo-preview" class="new-image-preview mt-3" style="display: none;">
                                                <label class="text-info">Nueva imagen:</label>
                                                <div class="image-container">
                                                    <img id="logo-preview-img" src="" alt="Preview" class="img-thumbnail" style="max-width: 200px; max-height: 100px;">
                                                    <button type="button" class="btn btn-sm btn-outline-danger mt-2" onclick="clearImagePreview('site_logo', 'logo-preview')">
                                                        <i class="fas fa-times"></i> Cancelar
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Favicon -->
                                        <div class="form-group">
                                            <label for="site_favicon">Favicon</label>
                                            
                                            <!-- Vista previa actual -->
                                            <?php if ($config['site_favicon']): ?>
                                                <div class="current-image-preview mb-3">
                                                    <label class="text-muted">Favicon actual:</label>
                                                    <div class="image-container">
                                                        <img src="<?php echo UPLOADS_URL; ?>/logos/<?php echo htmlspecialchars($config['site_favicon']); ?>" 
                                                             alt="Favicon actual" class="img-thumbnail" style="width: 32px; height: 32px;">
                                                        <small class="d-block text-muted mt-1"><?php echo htmlspecialchars($config['site_favicon']); ?></small>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="current-image-preview mb-3">
                                                    <div class="no-image-placeholder">
                                                        <i class="fas fa-globe fa-2x text-muted"></i>
                                                        <p class="text-muted">No hay favicon configurado</p>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Input para nueva imagen -->
                                            <input type="file" class="form-control-file" id="site_favicon" name="site_favicon" accept="image/*">
                                            <small class="text-muted">Formatos: PNG, ICO, GIF. Máximo 1MB. Recomendado: 32x32px o 16x16px</small>
                                            
                                            <!-- Vista previa de nueva imagen -->
                                            <div id="favicon-preview" class="new-image-preview mt-3" style="display: none;">
                                                <label class="text-info">Nuevo favicon:</label>
                                                <div class="image-container">
                                                    <img id="favicon-preview-img" src="" alt="Preview" class="img-thumbnail" style="width: 64px; height: 64px;">
                                                    <button type="button" class="btn btn-sm btn-outline-danger mt-2" onclick="clearImagePreview('site_favicon', 'favicon-preview')">
                                                        <i class="fas fa-times"></i> Cancelar
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Redes Sociales -->
                            <div class="col-md-6">
                                <div class="card card-success">
                                    <div class="card-header">
                                        <h3 class="card-title">Redes Sociales</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label for="facebook_url">Facebook</label>
                                            <input type="url" class="form-control" id="facebook_url" name="facebook_url"
                                                value="<?php echo htmlspecialchars($config['facebook_url']); ?>" placeholder="https://facebook.com/tu-pagina">
                                        </div>

                                        <div class="form-group">
                                            <label for="twitter_url">Twitter</label>
                                            <input type="url" class="form-control" id="twitter_url" name="twitter_url"
                                                value="<?php echo htmlspecialchars($config['twitter_url']); ?>" placeholder="https://twitter.com/tu-usuario">
                                        </div>

                                        <div class="form-group">
                                            <label for="instagram_url">Instagram</label>
                                            <input type="url" class="form-control" id="instagram_url" name="instagram_url"
                                                value="<?php echo htmlspecialchars($config['instagram_url']); ?>" placeholder="https://instagram.com/tu-usuario">
                                        </div>

                                        <div class="form-group">
                                            <label for="youtube_url">YouTube</label>
                                            <input type="url" class="form-control" id="youtube_url" name="youtube_url"
                                                value="<?php echo htmlspecialchars($config['youtube_url']); ?>" placeholder="https://youtube.com/tu-canal">
                                        </div>

                                        <div class="form-group">
                                            <label for="linkedin_url">LinkedIn</label>
                                            <input type="url" class="form-control" id="linkedin_url" name="linkedin_url"
                                                value="<?php echo htmlspecialchars($config['linkedin_url']); ?>" placeholder="https://linkedin.com/in/tu-perfil">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Mantenimiento -->
                            <div class="col-md-6">
                                <div class="card card-secondary">
                                    <div class="card-header">
                                        <h3 class="card-title">Modo Mantenimiento</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="maintenance_mode" name="maintenance_mode"
                                                    <?php echo $config['maintenance_mode'] == '1' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="maintenance_mode">Activar Modo Mantenimiento</label>
                                            </div>
                                            <small class="text-muted">El sitio público mostrará un mensaje de mantenimiento</small>
                                        </div>

                                        <div class="form-group">
                                            <label for="maintenance_message">Mensaje de Mantenimiento</label>
                                            <textarea class="form-control" id="maintenance_message" name="maintenance_message" rows="3"><?php echo htmlspecialchars($config['maintenance_message']); ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Botones -->
                                <div class="card">
                                    <div class="card-body">
                                        <button type="submit" class="btn btn-primary btn-lg btn-block">
                                            <i class="fas fa-save"></i> Guardar Configuración
                                        </button>
                                        <a href="../../index.php" class="btn btn-secondary btn-block">
                                            <i class="fas fa-arrow-left"></i> Volver al Dashboard
                                        </a>
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

    <!-- jQuery -->
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/jquery/jquery.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/dist/js/adminlte.min.js"></script>

    <script>
        $(document).ready(function() {
            // Preview para logo
            $('#site_logo').change(function() {
                handleImagePreview(this, 'logo-preview', 'logo-preview-img');
            });
            
            // Preview para favicon
            $('#site_favicon').change(function() {
                handleImagePreview(this, 'favicon-preview', 'favicon-preview-img');
            });

            // Mostrar/ocultar mensaje de mantenimiento
            $('#maintenance_mode').change(function() {
                if ($(this).is(':checked')) {
                    $('#maintenance_message').closest('.form-group').show();
                } else {
                    $('#maintenance_message').closest('.form-group').hide();
                }
            });
        });

        function handleImagePreview(input, previewContainerId, previewImgId) {
            const file = input.files[0];
            
            if (file) {
                // Validar tamaño del archivo
                const maxSize = input.id === 'site_favicon' ? 1024 * 1024 : 2 * 1024 * 1024; // 1MB para favicon, 2MB para logo
                
                if (file.size > maxSize) {
                    alert('El archivo es demasiado grande. Máximo ' + (maxSize / (1024 * 1024)) + 'MB');
                    input.value = '';
                    return;
                }
                
                // Validar tipo de archivo
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (input.id === 'site_favicon') {
                    allowedTypes.push('image/x-icon', 'image/vnd.microsoft.icon');
                }
                
                if (!allowedTypes.includes(file.type)) {
                    alert('Tipo de archivo no permitido. Solo se permiten imágenes.');
                    input.value = '';
                    return;
                }
                
                // Crear preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('#' + previewImgId).attr('src', e.target.result);
                    $('#' + previewContainerId).show();
                }
                reader.readAsDataURL(file);
            } else {
                $('#' + previewContainerId).hide();
            }
        }

        function clearImagePreview(inputId, previewContainerId) {
            $('#' + inputId).val('');
            $('#' + previewContainerId).hide();
        }

        // Confirmación antes de enviar formulario con imágenes
        $('form').on('submit', function(e) {
            const logoFile = $('#site_logo')[0].files[0];
            const faviconFile = $('#site_favicon')[0].files[0];
            
            if (logoFile || faviconFile) {
                if (!confirm('¿Estás seguro de actualizar las imágenes? Esta acción reemplazará las imágenes actuales.')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    </script>
</body>

</html>