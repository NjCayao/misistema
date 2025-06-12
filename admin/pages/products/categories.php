<?php
// admin/pages/products/categories.php
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
    $success = 'Categoría creada exitosamente';
} elseif (isset($_GET['updated'])) {
    $success = 'Categoría actualizada exitosamente';
} elseif (isset($_GET['deleted'])) {
    $success = 'Categoría eliminada exitosamente';
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
                    $name = sanitize($_POST['name'] ?? '');
                    $description = sanitize($_POST['description'] ?? '');
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    
                    if (empty($name)) {
                        throw new Exception('El nombre de la categoría es obligatorio');
                    }
                    
                    $slug = generateSlug($name);
                    
                    // Verificar que el slug no exista
                    $stmt = $db->prepare("SELECT id FROM categories WHERE slug = ?");
                    $stmt->execute([$slug]);
                    if ($stmt->fetch()) {
                        throw new Exception('Ya existe una categoría con ese nombre');
                    }
                    
                    // Manejar subida de imagen
                    $imagePath = '';
                    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                        $uploadResult = uploadFile($_FILES['image'], UPLOADS_PATH . '/categories', ALLOWED_IMAGE_TYPES);
                        if ($uploadResult['success']) {
                            $imagePath = $uploadResult['filename'];
                        } else {
                            throw new Exception('Error al subir imagen: ' . $uploadResult['message']);
                        }
                    }
                    
                    $stmt = $db->prepare("
                        INSERT INTO categories (name, slug, description, image, is_active, sort_order) 
                        VALUES (?, ?, ?, ?, ?, (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM categories c))
                    ");
                    $stmt->execute([$name, $slug, $description, $imagePath, $is_active]);
                    
                    $success = 'Categoría creada exitosamente';
                    redirect($_SERVER['PHP_SELF'] . '?created=1');
                    break;
                    
                case 'update':
                    $id = intval($_POST['id'] ?? 0);
                    $name = sanitize($_POST['name'] ?? '');
                    $description = sanitize($_POST['description'] ?? '');
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    
                    if (empty($name) || $id <= 0) {
                        throw new Exception('Datos inválidos');
                    }
                    
                    $slug = generateSlug($name);
                    
                    // Verificar que el slug no exista (excepto para el mismo registro)
                    $stmt = $db->prepare("SELECT id FROM categories WHERE slug = ? AND id != ?");
                    $stmt->execute([$slug, $id]);
                    if ($stmt->fetch()) {
                        throw new Exception('Ya existe una categoría con ese nombre');
                    }
                    
                    // Obtener imagen actual
                    $stmt = $db->prepare("SELECT image FROM categories WHERE id = ?");
                    $stmt->execute([$id]);
                    $currentImage = $stmt->fetchColumn();
                    
                    $imagePath = $currentImage;
                    
                    // Manejar nueva imagen
                    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                        $uploadResult = uploadFile($_FILES['image'], UPLOADS_PATH . '/categories', ALLOWED_IMAGE_TYPES);
                        if ($uploadResult['success']) {
                            // Eliminar imagen anterior si existe
                            if ($currentImage && file_exists(UPLOADS_PATH . '/categories/' . $currentImage)) {
                                unlink(UPLOADS_PATH . '/categories/' . $currentImage);
                            }
                            $imagePath = $uploadResult['filename'];
                        } else {
                            throw new Exception('Error al subir imagen: ' . $uploadResult['message']);
                        }
                    }
                    
                    $stmt = $db->prepare("UPDATE categories SET name = ?, slug = ?, description = ?, image = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([$name, $slug, $description, $imagePath, $is_active, $id]);
                    
                    $success = 'Categoría actualizada exitosamente';
                    redirect($_SERVER['PHP_SELF'] . '?updated=1');
                    break;
                    
                case 'delete':
                    $id = intval($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        throw new Exception('ID inválido');
                    }
                    
                    // Verificar que no tenga productos
                    $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
                    $stmt->execute([$id]);
                    $productCount = $stmt->fetchColumn();
                    
                    if ($productCount > 0) {
                        throw new Exception('No se puede eliminar una categoría que tiene productos asociados');
                    }
                    
                    // Obtener imagen para eliminar
                    $stmt = $db->prepare("SELECT image FROM categories WHERE id = ?");
                    $stmt->execute([$id]);
                    $image = $stmt->fetchColumn();
                    
                    // Eliminar categoría
                    $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    // Eliminar imagen si existe
                    if ($image && file_exists(UPLOADS_PATH . '/categories/' . $image)) {
                        unlink(UPLOADS_PATH . '/categories/' . $image);
                    }
                    
                    $success = 'Categoría eliminada exitosamente';
                    redirect($_SERVER['PHP_SELF'] . '?deleted=1');
                    break;
                    
                case 'update_order':
                    $orders = $_POST['order'] ?? [];
                    foreach ($orders as $id => $order) {
                        $stmt = $db->prepare("UPDATE categories SET sort_order = ? WHERE id = ?");
                        $stmt->execute([intval($order), intval($id)]);
                    }
                    $success = 'Orden actualizado exitosamente';
                    redirect($_SERVER['PHP_SELF'] . '?reordered=1');
                    break;
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        logError("Error en categorías: " . $e->getMessage());
    }
}

// Obtener categorías
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("
        SELECT c.*, 
               (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.is_active = 1) as product_count
        FROM categories c 
        ORDER BY c.sort_order ASC, c.name ASC
    ");
    $categories = $stmt->fetchAll();
} catch (Exception $e) {
    $categories = [];
    $error = 'Error al obtener categorías: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Categorías | <?php echo getSetting('site_name', 'MiSistema'); ?></title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/dist/css/adminlte.min.css">
    
    <style>
        .category-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
        .sortable-row {
            cursor: move;
        }
        .sortable-row:hover {
            background-color: #f8f9fa;
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
                        <h1 class="m-0">Gestión de Categorías</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/index.php">Dashboard</a></li>
                            <li class="breadcrumb-item">Productos</li>
                            <li class="breadcrumb-item active">Categorías</li>
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
                    <!-- Lista de Categorías -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Categorías Existentes</h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-info btn-sm" onclick="toggleSortMode()">
                                        <i class="fas fa-sort"></i> Ordenar
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-striped" id="categoriesTable">
                                        <thead>
                                            <tr>
                                                <th width="60">Imagen</th>
                                                <th>Nombre</th>
                                                <th>Slug</th>
                                                <th width="100">Productos</th>
                                                <th width="80">Estado</th>
                                                <th width="100">Orden</th>
                                                <th width="120">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody id="sortableCategories">
                                            <?php foreach ($categories as $category): ?>
                                                <tr class="sortable-row" data-id="<?php echo $category['id']; ?>">
                                                    <td>
                                                        <?php if ($category['image']): ?>
                                                            <img src="<?php echo UPLOADS_URL; ?>/categories/<?php echo htmlspecialchars($category['image']); ?>" 
                                                                 alt="<?php echo htmlspecialchars($category['name']); ?>" 
                                                                 class="category-image">
                                                        <?php else: ?>
                                                            <div class="category-image bg-light d-flex align-items-center justify-content-center">
                                                                <i class="fas fa-image text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                                        <?php if ($category['description']): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars(substr($category['description'], 0, 50)); ?>...</small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><code><?php echo htmlspecialchars($category['slug']); ?></code></td>
                                                    <td>
                                                        <span class="badge badge-info"><?php echo $category['product_count']; ?></span>
                                                    </td>
                                                    <td>
                                                        <?php if ($category['is_active']): ?>
                                                            <span class="badge badge-success">Activa</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-secondary">Inactiva</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <input type="number" class="form-control form-control-sm order-input" 
                                                               value="<?php echo $category['sort_order']; ?>" 
                                                               data-id="<?php echo $category['id']; ?>" 
                                                               style="width: 60px; display: none;">
                                                        <span class="order-display"><?php echo $category['sort_order']; ?></span>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-info" onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-danger" onclick="deleteCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Formulario -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title" id="formTitle">Nueva Categoría</h3>
                            </div>
                            <form method="post" enctype="multipart/form-data" id="categoryForm">
                                <div class="card-body">
                                    <input type="hidden" name="action" id="formAction" value="create">
                                    <input type="hidden" name="id" id="categoryId">

                                    <div class="form-group">
                                        <label for="name">Nombre *</label>
                                        <input type="text" class="form-control" id="name" name="name" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="description">Descripción</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                    </div>

                                    <div class="form-group">
                                        <label for="image">Imagen</label>
                                        <input type="file" class="form-control-file" id="image" name="image" accept="image/*">
                                        <small class="text-muted">Formatos: JPG, PNG, GIF. Máximo 2MB.</small>
                                        <div id="imagePreview" style="margin-top: 10px; display: none;">
                                            <img id="previewImg" src="" alt="Preview" style="max-width: 100%; height: 100px; object-fit: cover; border-radius: 5px;">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" checked>
                                            <label class="custom-control-label" for="is_active">Categoría Activa</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <button type="submit" class="btn btn-primary" id="submitBtn">
                                        <i class="fas fa-save"></i> Crear Categoría
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="resetForm()" id="cancelBtn" style="display: none;">
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
                <p>¿Estás seguro de que deseas eliminar la categoría <strong id="categoryNameToDelete"></strong>?</p>
                <p class="text-danger">Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="categoryIdToDelete">
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
<!-- DataTables -->
<script src="<?php echo ADMINLTE_URL; ?>/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo ADMINLTE_URL; ?>/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<!-- jQuery UI -->
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<!-- AdminLTE App -->
<script src="<?php echo ADMINLTE_URL; ?>/dist/js/adminlte.min.js"></script>

<script>
let sortMode = false;

$(document).ready(function() {
    // Inicializar DataTable
    $('#categoriesTable').DataTable({
        "responsive": true,
        "lengthChange": false,
        "autoWidth": false,
        "ordering": false,
        "info": false,
        "paging": false,
        "searching": true
    });
    
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
});

function editCategory(category) {
    $('#formTitle').text('Editar Categoría');
    $('#formAction').val('update');
    $('#categoryId').val(category.id);
    $('#name').val(category.name);
    $('#description').val(category.description);
    $('#is_active').prop('checked', category.is_active == 1);
    $('#submitBtn').html('<i class="fas fa-save"></i> Actualizar Categoría');
    $('#cancelBtn').show();
    
    if (category.image) {
        $('#previewImg').attr('src', '<?php echo UPLOADS_URL; ?>/categories/' + category.image);
        $('#imagePreview').show();
    }
}

function resetForm() {
    $('#formTitle').text('Nueva Categoría');
    $('#formAction').val('create');
    $('#categoryId').val('');
    $('#categoryForm')[0].reset();
    $('#is_active').prop('checked', true);
    $('#submitBtn').html('<i class="fas fa-save"></i> Crear Categoría');
    $('#cancelBtn').hide();
    $('#imagePreview').hide();
}

function deleteCategory(id, name) {
    $('#categoryNameToDelete').text(name);
    $('#categoryIdToDelete').val(id);
    $('#deleteModal').modal('show');
}

function toggleSortMode() {
    sortMode = !sortMode;
    
    if (sortMode) {
        $('.order-input').show();
        $('.order-display').hide();
        $('#sortableCategories').sortable({
            update: function() {
                updateOrder();
            }
        });
        $('.btn-info:contains("Ordenar")').html('<i class="fas fa-save"></i> Guardar Orden');
    } else {
        $('.order-input').hide();
        $('.order-display').show();
        $('#sortableCategories').sortable('destroy');
        $('.btn-info:contains("Guardar")').html('<i class="fas fa-sort"></i> Ordenar');
    }
}

function updateOrder() {
    const orders = {};
    $('#sortableCategories tr').each(function(index) {
        const id = $(this).data('id');
        orders[id] = index + 1;
        $(this).find('.order-input').val(index + 1);
        $(this).find('.order-display').text(index + 1);
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