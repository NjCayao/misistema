<?php
// admin/pages/products/categories.php - CÓDIGO COMPLETO FINAL
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

// Manejar mensajes de éxito
if (isset($_GET['created'])) {
    $success = 'Categoría creada exitosamente y agregada a elementos disponibles';
} elseif (isset($_GET['updated'])) {
    $success = 'Categoría actualizada exitosamente';
} elseif (isset($_GET['deleted'])) {
    $success = 'Categoría eliminada exitosamente';
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
                        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                        $uploadDir = UPLOADS_PATH . '/categories';
                        
                        if (!in_array($_FILES['image']['type'], $allowedTypes)) {
                            throw new Exception('Tipo de archivo no permitido');
                        }
                        
                        if ($_FILES['image']['size'] > 2 * 1024 * 1024) {
                            throw new Exception('El archivo es demasiado grande (máximo 2MB)');
                        }
                        
                        $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                        $imagePath = uniqid() . '.' . $extension;
                        
                        if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . '/' . $imagePath)) {
                            throw new Exception('Error al subir la imagen');
                        }
                    }
                    
                    // Iniciar transacción
                    $db->beginTransaction();
                    
                    // Crear categoría con sort_order = 0
                    $stmt = $db->prepare("
                        INSERT INTO categories (name, slug, description, image, is_active, sort_order) 
                        VALUES (?, ?, ?, ?, ?, 0)
                    ");
                    $stmt->execute([$name, $slug, $description, $imagePath, $is_active]);
                    
                    // SIEMPRE crear en menu_items automáticamente
                    $stmt = $db->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 as next_order FROM menu_items WHERE menu_location = 'available_categories'");
                    $stmt->execute();
                    $nextOrder = $stmt->fetch()['next_order'];
                    
                    $stmt = $db->prepare("
                        INSERT INTO menu_items (title, url, menu_location, is_active, sort_order) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$name, '/categoria/' . $slug, 'available_categories', 1, $nextOrder]);
                    
                    $db->commit();
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
                    
                    // Obtener datos actuales
                    $stmt = $db->prepare("SELECT slug, image FROM categories WHERE id = ?");
                    $stmt->execute([$id]);
                    $currentCategory = $stmt->fetch();
                    if (!$currentCategory) {
                        throw new Exception('Categoría no encontrada');
                    }
                    
                    $slug = generateSlug($name);
                    
                    // Verificar slug único
                    $stmt = $db->prepare("SELECT id FROM categories WHERE slug = ? AND id != ?");
                    $stmt->execute([$slug, $id]);
                    if ($stmt->fetch()) {
                        throw new Exception('Ya existe una categoría con ese nombre');
                    }
                    
                    // Manejar imagen
                    $imagePath = $currentCategory['image'];
                    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                        $uploadDir = UPLOADS_PATH . '/categories';
                        
                        if (!in_array($_FILES['image']['type'], $allowedTypes)) {
                            throw new Exception('Tipo de archivo no permitido');
                        }
                        
                        if ($_FILES['image']['size'] > 2 * 1024 * 1024) {
                            throw new Exception('El archivo es demasiado grande (máximo 2MB)');
                        }
                        
                        // Eliminar imagen anterior
                        if ($currentCategory['image'] && file_exists($uploadDir . '/' . $currentCategory['image'])) {
                            unlink($uploadDir . '/' . $currentCategory['image']);
                        }
                        
                        $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                        $imagePath = uniqid() . '.' . $extension;
                        
                        if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . '/' . $imagePath)) {
                            throw new Exception('Error al subir la imagen');
                        }
                    }
                    
                    // Iniciar transacción
                    $db->beginTransaction();
                    
                    // Actualizar categoría
                    $stmt = $db->prepare("UPDATE categories SET name = ?, slug = ?, description = ?, image = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([$name, $slug, $description, $imagePath, $is_active, $id]);
                    
                    // Actualizar en menu_items (solo título y URL)
                    $currentUrl = '/categoria/' . $currentCategory['slug'];
                    $newUrl = '/categoria/' . $slug;
                    
                    $stmt = $db->prepare("UPDATE menu_items SET title = ?, url = ? WHERE url = ?");
                    $stmt->execute([$name, $newUrl, $currentUrl]);
                    
                    $db->commit();
                    redirect($_SERVER['PHP_SELF'] . '?updated=1');
                    break;
                    
                case 'delete':
                    $id = intval($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        throw new Exception('ID inválido');
                    }
                    
                    // Verificar productos
                    $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
                    $stmt->execute([$id]);
                    if ($stmt->fetchColumn() > 0) {
                        throw new Exception('No se puede eliminar una categoría que tiene productos');
                    }
                    
                    // Obtener datos
                    $stmt = $db->prepare("SELECT slug, image FROM categories WHERE id = ?");
                    $stmt->execute([$id]);
                    $categoryToDelete = $stmt->fetch();
                    
                    if (!$categoryToDelete) {
                        throw new Exception('Categoría no encontrada');
                    }
                    
                    $db->beginTransaction();
                    
                    // Eliminar categoría
                    $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    // Eliminar de menús
                    $stmt = $db->prepare("DELETE FROM menu_items WHERE url = ?");
                    $stmt->execute(['/categoria/' . $categoryToDelete['slug']]);
                    
                    // Eliminar imagen
                    if ($categoryToDelete['image'] && file_exists(UPLOADS_PATH . '/categories/' . $categoryToDelete['image'])) {
                        unlink(UPLOADS_PATH . '/categories/' . $categoryToDelete['image']);
                    }
                    
                    $db->commit();
                    redirect($_SERVER['PHP_SELF'] . '?deleted=1');
                    break;
            }
        }
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        $error = $e->getMessage();
        logError("Error en categorías: " . $e->getMessage());
    }
}

// Obtener categorías - ORDEN DESCENDENTE POR ID (nuevos primero)
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("
        SELECT c.*, 
               (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.is_active = 1) as product_count,
               CASE WHEN m.id IS NOT NULL THEN 'Sí' ELSE 'No' END as in_menus
        FROM categories c 
        LEFT JOIN menu_items m ON m.url = CONCAT('/categoria/', c.slug) AND m.menu_location = 'available_categories'
        ORDER BY c.id DESC
    ");
    $categories = $stmt->fetchAll();
} catch (Exception $e) {
    $categories = [];
    $error = 'Error al obtener categorías: ' . $e->getMessage();
}

// Si hay un ID en GET, obtener datos para editar
$editCategory = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    try {
        $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $editCategory = $stmt->fetch();
    } catch (Exception $e) {
        $error = 'Error al obtener categoría para editar';
    }
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
        .menu-auto-info {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .menu-auto-info h6 {
            color: #1976d2;
            margin-bottom: 10px;
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
                        <!-- Info sobre sistema automático -->
                        <div class="menu-auto-info">
                            <h6><i class="fas fa-magic"></i> Sistema Automático de Menús</h6>
                            <p class="mb-0">
                                <i class="fas fa-info-circle"></i>
                                <strong>Todas las categorías aparecen automáticamente</strong> en "Categorías Disponibles" del editor de menús. 
                                El estado de la categoría controla si se muestra en el sitio público.
                            </p>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Categorías Existentes</h3>
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
                                                <th width="100">En Menús</th>
                                                <th width="100">Orden</th>
                                                <th width="120">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($categories as $category): ?>
                                                <tr>
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
                                                    <td class="text-center">
                                                        <span class="badge badge-<?php echo $category['in_menus'] == 'Sí' ? 'success' : 'warning'; ?>">
                                                            <?php echo $category['in_menus']; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $category['sort_order']; ?></td>
                                                    <td>
                                                        <a href="?edit=<?php echo $category['id']; ?>" class="btn btn-sm btn-info">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
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
                                <h3 class="card-title">
                                    <?php echo $editCategory ? 'Editar Categoría' : 'Nueva Categoría'; ?>
                                </h3>
                                <?php if ($editCategory): ?>
                                    <div class="card-tools">
                                        <a href="?" class="btn btn-sm btn-secondary">
                                            <i class="fas fa-times"></i> Cancelar
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <form method="post" enctype="multipart/form-data">
                                <div class="card-body">
                                    <input type="hidden" name="action" value="<?php echo $editCategory ? 'update' : 'create'; ?>">
                                    <?php if ($editCategory): ?>
                                        <input type="hidden" name="id" value="<?php echo $editCategory['id']; ?>">
                                    <?php endif; ?>

                                    <div class="form-group">
                                        <label for="name">Nombre *</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo $editCategory ? htmlspecialchars($editCategory['name']) : ''; ?>" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="description">Descripción</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo $editCategory ? htmlspecialchars($editCategory['description']) : ''; ?></textarea>
                                    </div>

                                    <div class="form-group">
                                        <label for="image">Imagen</label>
                                        <input type="file" class="form-control-file" id="image" name="image" accept="image/*">
                                        <small class="text-muted">Formatos: JPG, PNG, GIF. Máximo 2MB.</small>
                                        <div id="imagePreview" style="margin-top: 10px; <?php echo ($editCategory && $editCategory['image']) ? '' : 'display: none;'; ?>">
                                            <img id="previewImg" src="<?php echo ($editCategory && $editCategory['image']) ? UPLOADS_URL . '/categories/' . $editCategory['image'] : ''; ?>" 
                                                 alt="Preview" style="max-width: 100%; height: 100px; object-fit: cover; border-radius: 5px;">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" 
                                                   <?php echo (!$editCategory || $editCategory['is_active']) ? 'checked' : ''; ?>>
                                            <label class="custom-control-label" for="is_active">
                                                <i class="fas fa-eye"></i> Categoría Activa
                                            </label>
                                        </div>
                                        <small class="text-muted">
                                            Controla si la categoría se muestra en el sitio público. 
                                            <strong>Siempre aparece en el editor de menús.</strong>
                                        </small>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> <?php echo $editCategory ? 'Actualizar' : 'Crear'; ?> Categoría
                                    </button>
                                    <?php if ($editCategory): ?>
                                        <a href="?" class="btn btn-secondary">
                                            <i class="fas fa-times"></i> Cancelar
                                        </a>
                                    <?php endif; ?>
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

<!-- Modal de confirmación -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Confirmar Eliminación</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>¿Eliminar la categoría <strong id="categoryNameToDelete"></strong>?</p>
                <p class="text-danger">Esta acción eliminará la categoría de todos los menús.</p>
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

<!-- Scripts -->
<script src="<?php echo ADMINLTE_URL; ?>/plugins/jquery/jquery.min.js"></script>
<script src="<?php echo ADMINLTE_URL; ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo ADMINLTE_URL; ?>/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo ADMINLTE_URL; ?>/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="<?php echo ADMINLTE_URL; ?>/dist/js/adminlte.min.js"></script>

<script>
$(document).ready(function() {
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

function deleteCategory(id, name) {
    $('#categoryNameToDelete').text(name);
    $('#categoryIdToDelete').val(id);
    $('#deleteModal').modal('show');
}
</script>
</body>
</html>