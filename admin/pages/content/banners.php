<?php
// admin/pages/content/banners.php
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

// Manejar mensajes de éxito de redirecciones
if (isset($_GET['created'])) {
    $success = 'Banner creado exitosamente';
} elseif (isset($_GET['updated'])) {
    $success = 'Banner actualizado exitosamente';
} elseif (isset($_GET['deleted'])) {
    $success = 'Banner eliminado exitosamente';
} elseif (isset($_GET['reordered'])) {
    $success = 'Orden actualizado exitosamente';
}

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance()->getConnection();
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create':
                    $title = sanitize($_POST['title'] ?? '');
                    $subtitle = sanitize($_POST['subtitle'] ?? '');
                    $description = sanitize($_POST['description'] ?? '');
                    $button_text = sanitize($_POST['button_text'] ?? '');
                    $button_url = sanitize($_POST['button_url'] ?? '');
                    $position = sanitize($_POST['position'] ?? 'home_slider');
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    
                    if (empty($title)) {
                        throw new Exception('El título del banner es obligatorio');
                    }
                    
                    // Manejar subida de imagen
                    $imagePath = '';
                    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                        $uploadResult = uploadFile($_FILES['image'], UPLOADS_PATH . '/banners');
                        if ($uploadResult['success']) {
                            $imagePath = $uploadResult['filename'];
                        } else {
                            throw new Exception('Error al subir imagen: ' . $uploadResult['message']);
                        }
                    }
                    
                    if (empty($imagePath)) {
                        throw new Exception('La imagen del banner es obligatoria');
                    }
                    
                    $stmt = $db->prepare("
                        INSERT INTO banners (title, subtitle, description, image, button_text, button_url, position, is_active, sort_order) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM banners b WHERE b.position = ?))
                    ");
                    $stmt->execute([$title, $subtitle, $description, $imagePath, $button_text, $button_url, $position, $is_active, $position]);
                    
                    $success = 'Banner creado exitosamente';
                    redirect($_SERVER['PHP_SELF'] . '?created=1');
                    break;
                    
                case 'update':
                    $id = intval($_POST['id'] ?? 0);
                    $title = sanitize($_POST['title'] ?? '');
                    $subtitle = sanitize($_POST['subtitle'] ?? '');
                    $description = sanitize($_POST['description'] ?? '');
                    $button_text = sanitize($_POST['button_text'] ?? '');
                    $button_url = sanitize($_POST['button_url'] ?? '');
                    $position = sanitize($_POST['position'] ?? 'home_slider');
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    
                    if (empty($title) || $id <= 0) {
                        throw new Exception('Datos inválidos');
                    }
                    
                    // Obtener imagen actual
                    $stmt = $db->prepare("SELECT image FROM banners WHERE id = ?");
                    $stmt->execute([$id]);
                    $currentImage = $stmt->fetchColumn();
                    
                    $imagePath = $currentImage;
                    
                    // Manejar nueva imagen
                    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                        $uploadResult = uploadFile($_FILES['image'], UPLOADS_PATH . '/banners');
                        if ($uploadResult['success']) {
                            // Eliminar imagen anterior si existe
                            if ($currentImage && file_exists(UPLOADS_PATH . '/banners/' . $currentImage)) {
                                unlink(UPLOADS_PATH . '/banners/' . $currentImage);
                            }
                            $imagePath = $uploadResult['filename'];
                        } else {
                            throw new Exception('Error al subir imagen: ' . $uploadResult['message']);
                        }
                    }
                    
                    $stmt = $db->prepare("
                        UPDATE banners SET title = ?, subtitle = ?, description = ?, image = ?, button_text = ?, button_url = ?, position = ?, is_active = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$title, $subtitle, $description, $imagePath, $button_text, $button_url, $position, $is_active, $id]);
                    
                    $success = 'Banner actualizado exitosamente';
                    redirect($_SERVER['PHP_SELF'] . '?updated=1');
                    break;
                    
                case 'delete':
                    $id = intval($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        throw new Exception('ID inválido');
                    }
                    
                    // Obtener imagen para eliminar
                    $stmt = $db->prepare("SELECT image FROM banners WHERE id = ?");
                    $stmt->execute([$id]);
                    $image = $stmt->fetchColumn();
                    
                    // Eliminar banner
                    $stmt = $db->prepare("DELETE FROM banners WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    // Eliminar imagen si existe
                    if ($image && file_exists(UPLOADS_PATH . '/banners/' . $image)) {
                        unlink(UPLOADS_PATH . '/banners/' . $image);
                    }
                    
                    $success = 'Banner eliminado exitosamente';
                    redirect($_SERVER['PHP_SELF'] . '?deleted=1');
                    break;
                    
                case 'update_order':
                    $orders = $_POST['order'] ?? [];
                    foreach ($orders as $id => $order) {
                        $stmt = $db->prepare("UPDATE banners SET sort_order = ? WHERE id = ?");
                        $stmt->execute([intval($order), intval($id)]);
                    }
                    $success = 'Orden actualizado exitosamente';
                    redirect($_SERVER['PHP_SELF'] . '?reordered=1');
                    break;
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        logError("Error en banners: " . $e->getMessage());
    }
}

// Obtener banners
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT * FROM banners ORDER BY position ASC, sort_order ASC, title ASC");
    $banners = $stmt->fetchAll();
} catch (Exception $e) {
    $banners = [];
    $error = 'Error al obtener banners: ' . $e->getMessage();
}

// Agrupar banners por posición
$bannersByPosition = [];
foreach ($banners as $banner) {
    $bannersByPosition[$banner['position']][] = $banner;
}

// Definir posiciones disponibles
$positions = [
    'home_slider' => 'Slider Principal (Home)',
    'promotion' => 'Promociones',
    'home_hero' => 'Hero Section (tarjetas)',
    // 'footer' => 'Footer',
    // 'header' => 'Header',
    'sidebar' => 'Sidebar (Footer)'
    
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Banners | <?php echo getSetting('site_name', 'MiSistema'); ?></title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/dist/css/adminlte.min.css">
    
    <style>
        .banner-image {
            width: 100px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
        }
        .banner-preview {
            max-width: 300px;
            background: linear-gradient(45deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin: 10px 0;
        }
        .banner-preview h5 {
            margin-bottom: 5px;
            font-weight: bold;
        }
        .banner-preview p {
            margin-bottom: 10px;
            font-size: 0.9em;
        }
        .sortable-row {
            cursor: move;
        }
        .sortable-row:hover {
            background-color: #f8f9fa;
        }
        .position-section {
            border-left: 4px solid #007bff;
            margin-bottom: 30px;
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
                        <h1 class="m-0">Gestión de Banners</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/index.php">Dashboard</a></li>
                            <li class="breadcrumb-item">Contenido</li>
                            <li class="breadcrumb-item active">Banners</li>
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
                    <!-- Lista de Banners por Posición -->
                    <div class="col-md-8">
                        <?php foreach ($positions as $posKey => $posName): ?>
                            <div class="card position-section">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-images"></i> <?php echo $posName; ?>
                                    </h3>
                                    <div class="card-tools">
                                        <span class="badge badge-info">
                                            <?php echo count($bannersByPosition[$posKey] ?? []); ?> banners
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (isset($bannersByPosition[$posKey]) && count($bannersByPosition[$posKey]) > 0): ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th width="120">Imagen</th>
                                                        <th>Contenido</th>
                                                        <th width="80">Estado</th>
                                                        <th width="60">Orden</th>
                                                        <th width="120">Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="sortable-banners" data-position="<?php echo $posKey; ?>">
                                                    <?php foreach ($bannersByPosition[$posKey] as $banner): ?>
                                                        <tr class="sortable-row" data-id="<?php echo $banner['id']; ?>">
                                                            <td>
                                                                <img src="<?php echo UPLOADS_URL; ?>/banners/<?php echo htmlspecialchars($banner['image']); ?>" 
                                                                     alt="<?php echo htmlspecialchars($banner['title']); ?>" 
                                                                     class="banner-image">
                                                            </td>
                                                            <td>
                                                                <div class="banner-preview">
                                                                    <h5><?php echo htmlspecialchars($banner['title']); ?></h5>
                                                                    <?php if ($banner['subtitle']): ?>
                                                                        <h6><?php echo htmlspecialchars($banner['subtitle']); ?></h6>
                                                                    <?php endif; ?>
                                                                    <?php if ($banner['description']): ?>
                                                                        <p><?php echo htmlspecialchars(substr($banner['description'], 0, 100)); ?>...</p>
                                                                    <?php endif; ?>
                                                                    <?php if ($banner['button_text']): ?>
                                                                        <button class="btn btn-light btn-sm">
                                                                            <?php echo htmlspecialchars($banner['button_text']); ?>
                                                                        </button>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <?php if ($banner['is_active']): ?>
                                                                    <span class="badge badge-success">Activo</span>
                                                                <?php else: ?>
                                                                    <span class="badge badge-secondary">Inactivo</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <span class="badge badge-info"><?php echo $banner['sort_order']; ?></span>
                                                            </td>
                                                            <td>
                                                                <button class="btn btn-sm btn-info" onclick="editBanner(<?php echo htmlspecialchars(json_encode($banner)); ?>)">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-danger" onclick="deleteBanner(<?php echo $banner['id']; ?>, '<?php echo htmlspecialchars($banner['title']); ?>')">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="p-3 text-center text-muted">
                                            <i class="fas fa-images fa-3x mb-3"></i>
                                            <p>No hay banners en esta posición</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Formulario -->
                    <div class="col-md-4">
                        <div class="card sticky-top">
                            <div class="card-header">
                                <h3 class="card-title" id="formTitle">Nuevo Banner</h3>
                            </div>
                            <form method="post" enctype="multipart/form-data" id="bannerForm">
                                <div class="card-body">
                                    <input type="hidden" name="action" id="formAction" value="create">
                                    <input type="hidden" name="id" id="bannerId">

                                    <div class="form-group">
                                        <label for="position">Posición *</label>
                                        <select class="form-control" id="position" name="position" required>
                                            <?php foreach ($positions as $key => $name): ?>
                                                <option value="<?php echo $key; ?>"><?php echo $name; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="title">Título *</label>
                                        <input type="text" class="form-control" id="title" name="title" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="subtitle">Subtítulo</label>
                                        <input type="text" class="form-control" id="subtitle" name="subtitle">
                                    </div>

                                    <div class="form-group">
                                        <label for="description">Descripción</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                    </div>

                                    <div class="form-group">
                                        <label for="image">Imagen *</label>
                                        <input type="file" class="form-control-file" id="image" name="image" accept="image/*">
                                        <small class="text-muted">Recomendado: 1200x400px. Formatos: JPG, PNG. Máximo 5MB.</small>
                                        <div id="imagePreview" style="margin-top: 10px; display: none;">
                                            <img id="previewImg" src="" alt="Preview" style="max-width: 100%; height: 150px; object-fit: cover; border-radius: 5px;">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="button_text">Texto del Botón</label>
                                        <input type="text" class="form-control" id="button_text" name="button_text" placeholder="Ver más">
                                    </div>

                                    <div class="form-group">
                                        <label for="button_url">URL del Botón</label>
                                        <input type="url" class="form-control" id="button_url" name="button_url" placeholder="https://...">
                                    </div>

                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" checked>
                                            <label class="custom-control-label" for="is_active">Banner Activo</label>
                                        </div>
                                    </div>

                                    <!-- Vista previa del banner -->
                                    <div class="form-group">
                                        <label>Vista Previa</label>
                                        <div id="bannerPreview" class="banner-preview">
                                            <h5 id="previewTitle">Título del Banner</h5>
                                            <h6 id="previewSubtitle" style="display: none;"></h6>
                                            <p id="previewDescription" style="display: none;"></p>
                                            <button type="button" class="btn btn-light btn-sm" id="previewButton" style="display: none;">
                                                Botón
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <button type="submit" class="btn btn-primary btn-block" id="submitBtn">
                                        <i class="fas fa-save"></i> Crear Banner
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-block" onclick="resetForm()" id="cancelBtn" style="display: none;">
                                        <i class="fas fa-times"></i> Cancelar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </div>

    <!-- Footer -->
    <?php include '../../includes/footer.php'; ?>
</div>

<!-- Modal de confirmación de eliminación -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Confirmar Eliminación</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de que deseas eliminar el banner <strong id="bannerNameToDelete"></strong>?</p>
                <p class="text-danger">Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="bannerIdToDelete">
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- jQuery -->
<script src="<?php echo ADMINLTE_URL; ?>/plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="<?php echo ADMINLTE_URL; ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- jQuery UI -->
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<!-- AdminLTE App -->
<script src="<?php echo ADMINLTE_URL; ?>/dist/js/adminlte.min.js"></script>

<script>
$(document).ready(function() {
    // Preview de imagen
    $('#image').change(function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#previewImg').attr('src', e.target.result);
                $('#imagePreview').show();
            }
            reader.readAsDataURL(file);
        }
    });
    
    // Vista previa del banner en tiempo real
    $('#title').on('input', function() {
        $('#previewTitle').text($(this).val() || 'Título del Banner');
    });
    
    $('#subtitle').on('input', function() {
        const value = $(this).val();
        if (value) {
            $('#previewSubtitle').text(value).show();
        } else {
            $('#previewSubtitle').hide();
        }
    });
    
    $('#description').on('input', function() {
        const value = $(this).val();
        if (value) {
            $('#previewDescription').text(value).show();
        } else {
            $('#previewDescription').hide();
        }
    });
    
    $('#button_text').on('input', function() {
        const value = $(this).val();
        if (value) {
            $('#previewButton').text(value).show();
        } else {
            $('#previewButton').hide();
        }
    });
    
    // Sortable para reordenar banners
    $('.sortable-banners').sortable({
        update: function() {
            updateOrder($(this));
        }
    });
});

function editBanner(banner) {
    $('#formTitle').text('Editar Banner');
    $('#formAction').val('update');
    $('#bannerId').val(banner.id);
    $('#position').val(banner.position);
    $('#title').val(banner.title);
    $('#subtitle').val(banner.subtitle);
    $('#description').val(banner.description);
    $('#button_text').val(banner.button_text);
    $('#button_url').val(banner.button_url);
    $('#is_active').prop('checked', banner.is_active == 1);
    $('#submitBtn').html('<i class="fas fa-save"></i> Actualizar Banner');
    $('#cancelBtn').show();
    
    // Actualizar vista previa
    $('#previewTitle').text(banner.title);
    $('#previewSubtitle').text(banner.subtitle).toggle(!!banner.subtitle);
    $('#previewDescription').text(banner.description).toggle(!!banner.description);
    $('#previewButton').text(banner.button_text).toggle(!!banner.button_text);
    
    if (banner.image) {
        $('#previewImg').attr('src', '<?php echo UPLOADS_URL; ?>/banners/' + banner.image);
        $('#imagePreview').show();
    }
}

function resetForm() {
    $('#formTitle').text('Nuevo Banner');
    $('#formAction').val('create');
    $('#bannerId').val('');
    $('#bannerForm')[0].reset();
    $('#is_active').prop('checked', true);
    $('#submitBtn').html('<i class="fas fa-save"></i> Crear Banner');
    $('#cancelBtn').hide();
    $('#imagePreview').hide();
    
    // Resetear vista previa
    $('#previewTitle').text('Título del Banner');
    $('#previewSubtitle').hide();
    $('#previewDescription').hide();
    $('#previewButton').hide();
}

function deleteBanner(id, name) {
    $('#bannerNameToDelete').text(name);
    $('#bannerIdToDelete').val(id);
    $('#deleteModal').modal('show');
}

function updateOrder(container) {
    const position = container.data('position');
    const orders = {};
    
    container.find('tr').each(function(index) {
        const id = $(this).data('id');
        orders[id] = index + 1;
    });
    
    // Enviar actualización por AJAX
    $.post(window.location.href, {
        action: 'update_order',
        order: orders
    });
}
</script>
</body>
</html>