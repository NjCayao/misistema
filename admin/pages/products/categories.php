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
    $success = 'Categoría creada exitosamente y agregada a elementos disponibles';
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
                    $available_in_menus = isset($_POST['available_in_menus']) ? 1 : 0;
                    
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
                                                                // También actualizar en todas las otras ubicaciones donde pueda estar
                                        $stmt = $db->prepare("
                                            UPDATE menu_items 
                                            SET title = ?, url = ?, is_active = ?, updated_at = NOW() 
                                            WHERE url = ? AND menu_location != 'available_categories'
                                        ");
                                        $stmt->execute([$name, $newUrl, $is_active, $currentUrl]);
                                        
                                    } else {
                            throw new Exception('Error al subir imagen: ' . $uploadResult['message']);
                        }
                    }
                    
                    // Iniciar transacción
                    $db->beginTransaction();
                    
                    // Crear categoría
                    $stmt = $db->prepare("
                        INSERT INTO categories (name, slug, description, image, is_active, sort_order) 
                        VALUES (?, ?, ?, ?, ?, (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM categories c))
                    ");
                    $stmt->execute([$name, $slug, $description, $imagePath, $is_active]);
                    $categoryId = $db->lastInsertId();
                    
                    // Agregar automáticamente a elementos disponibles si está marcado
                    if ($available_in_menus) {
                        // Obtener próximo orden
                        $stmt = $db->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order FROM menu_items WHERE menu_location = 'available_categories'");
                        $stmt->execute();
                        $nextOrder = $stmt->fetch()['next_order'];
                        
                        // Crear elemento en disponibles
                        $stmt = $db->prepare("
                            INSERT INTO menu_items (title, url, menu_location, is_active, sort_order, created_at) 
                            VALUES (?, ?, 'available_categories', ?, ?, NOW())
                        ");
                        $stmt->execute([$name, '/categoria/' . $slug, $is_active, $nextOrder]);
                    }
                    
                    $db->commit();
                    $success = 'Categoría creada exitosamente' . ($available_in_menus ? ' y agregada a elementos disponibles' : '');
                    redirect($_SERVER['PHP_SELF'] . '?created=1');
                    break;
                    
                case 'update':
                    $id = intval($_POST['id'] ?? 0);
                    $name = sanitize($_POST['name'] ?? '');
                    $description = sanitize($_POST['description'] ?? '');
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    $available_in_menus = isset($_POST['available_in_menus']) ? 1 : 0;
                    
                    if (empty($name) || $id <= 0) {
                        throw new Exception('Datos inválidos');
                    }
                    
                    // Obtener slug actual
                    $stmt = $db->prepare("SELECT slug FROM categories WHERE id = ?");
                    $stmt->execute([$id]);
                    $currentCategory = $stmt->fetch();
                    if (!$currentCategory) {
                        throw new Exception('Categoría no encontrada');
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
                    
                    // Iniciar transacción
                    $db->beginTransaction();
                    
                    // Actualizar categoría
                    $stmt = $db->prepare("UPDATE categories SET name = ?, slug = ?, description = ?, image = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([$name, $slug, $description, $imagePath, $is_active, $id]);
                    
                    // Gestionar elementos disponibles
                    $currentUrl = '/categoria/' . $currentCategory['slug'];
                    $newUrl = '/categoria/' . $slug;
                    
                    // Verificar si ya existe en elementos disponibles
                    $stmt = $db->prepare("SELECT id FROM menu_items WHERE url = ? AND menu_location = 'available_categories'");
                    $stmt->execute([$currentUrl]);
                    $existingMenu = $stmt->fetch();
                    
                    if ($available_in_menus) {
                        if ($existingMenu) {
                            // Actualizar elemento existente
                            $stmt = $db->prepare("
                                UPDATE menu_items 
                                SET title = ?, url = ?, is_active = ?, updated_at = NOW() 
                                WHERE id = ?
                            ");
                            $stmt->execute([$name, $newUrl, $is_active, $existingMenu['id']]);
                        } else {
                            // Crear nuevo elemento disponible
                            $stmt = $db->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order FROM menu_items WHERE menu_location = 'available_categories'");
                            $stmt->execute();
                            $nextOrder = $stmt->fetch()['next_order'];
                            
                            $stmt = $db->prepare("
                                INSERT INTO menu_items (title, url, menu_location, is_active, sort_order, created_at) 
                                VALUES (?, ?, 'available_categories', ?, ?, NOW())
                            ");
                            $stmt->execute([$name, $newUrl, $is_active, $nextOrder]);
                        }
                        
                        // También actualizar en todas las otras ubicaciones donde pueda estar
                        $stmt = $db->prepare("
                            UPDATE menu_items 
                            SET title = ?, url = ?, is_active = ?, updated_at = NOW() 
                            WHERE url = ? AND menu_location != 'available_categories'
                        ");
                        $stmt->execute([$name, $newUrl, $is_active, $currentUrl]);
                        
                    } else {
                        // Eliminar de elementos disponibles si existe
                        if ($existingMenu) {
                            $stmt = $db->prepare("DELETE FROM menu_items WHERE id = ?");
                            $stmt->execute([$existingMenu['id']]);
                        }
                    }
                    
                    $db->commit();
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
                    
                    // Obtener datos de la categoría
                    $stmt = $db->prepare("SELECT slug, image FROM categories WHERE id = ?");
                    $stmt->execute([$id]);
                    $categoryToDelete = $stmt->fetch();
                    
                    if (!$categoryToDelete) {
                        throw new Exception('Categoría no encontrada');
                    }
                    
                    // Iniciar transacción
                    $db->beginTransaction();
                    
                    // Eliminar categoría
                    $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    // Eliminar automáticamente de todos los menús
                    $stmt = $db->prepare("DELETE FROM menu_items WHERE url = ?");
                    $stmt->execute(['/categoria/' . $categoryToDelete['slug']]);
                    
                    // Eliminar imagen si existe
                    if ($categoryToDelete['image'] && file_exists(UPLOADS_PATH . '/categories/' . $categoryToDelete['image'])) {
                        unlink(UPLOADS_PATH . '/categories/' . $categoryToDelete['image']);
                    }
                    
                    $db->commit();
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
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        $error = $e->getMessage();
        logError("Error en categorías: " . $e->getMessage());
    }
}

// Obtener categorías
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("
        SELECT c.*, 
               (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.is_active = 1) as product_count,
               CASE WHEN m.id IS NOT NULL THEN 1 ELSE 0 END as available_in_menus,
               CASE WHEN c.is_active = 1 AND m.id IS NOT NULL THEN 1 ELSE 0 END as in_menus
        FROM categories c 
        LEFT JOIN menu_items m ON m.url = CONCAT('/categoria/', c.slug) AND m.menu_location = 'available_categories'
        ORDER BY c.sort_order ASC, c.name ASC
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
        $stmt = $db->prepare("
            SELECT c.*, 
                   CASE WHEN m.id IS NOT NULL THEN 1 ELSE 0 END as available_in_menus,
                   CASE WHEN c.is_active = 1 AND m.id IS NOT NULL THEN 1 ELSE 0 END as in_menus
            FROM categories c 
            LEFT JOIN menu_items m ON m.url = CONCAT('/categoria/', c.slug) AND m.menu_location = 'available_categories'
            WHERE c.id = ?
        ");
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
        .sortable-row {
            cursor: move;
        }
        .sortable-row:hover {
            background-color: #f8f9fa;
        }
        .menu-auto-info {
            background: #e8f5e8;
            border: 1px solid #4caf50;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .menu-auto-info h6 {
            color: #2e7d32;
            margin-bottom: 10px;
        }
        .sync-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }
        .sync-available {
            background-color: #4caf50;
        }
        .sync-not-available {
            background-color: #f44336;
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
                                Las categorías marcadas como "Disponible en menús" aparecerán automáticamente en la sección "Elementos Disponibles" del editor de menús, 
                                donde podrás organizarlas fácilmente arrastrando a donde desees (header, footer, etc.).
                            </p>
                        </div>
                        
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
                                                                                                        <th width="120">En Menús</th>
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
                                                            <span class="badge badge-success">
                                                                <span class="sync-indicator sync-available"></span>Activa
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge badge-secondary">
                                                                <span class="sync-indicator sync-not-available"></span>Inactiva
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if ($category['in_menus']): ?>
                                                            <span class="badge badge-success">
                                                                <i class="fas fa-check"></i> Sí
                                                            </span>
                                                        <?php elseif ($category['available_in_menus']): ?>
                                                            <span class="badge badge-warning">
                                                                <i class="fas fa-exclamation-triangle"></i> Inactivo
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge badge-light">
                                                                <i class="fas fa-times"></i> No
                                                            </span>
                                                        <?php endif; ?>
                                                        
                                                        <!-- Tooltip explicativo -->
                                                        <?php if ($category['available_in_menus'] && !$category['in_menus']): ?>
                                                            <br><small class="text-muted">Disponible pero inactivo</small>
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
                                <h3 class="card-title" id="formTitle">
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
                            <form method="post" enctype="multipart/form-data" id="categoryForm">
                                <div class="card-body">
                                    <input type="hidden" name="action" id="formAction" value="<?php echo $editCategory ? 'update' : 'create'; ?>">
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
                                            <label class="custom-control-label" for="is_active">Categoría Activa</label>
                                        </div>
                                    </div>

                                    <!-- NUEVA OPCIÓN: Disponible en menús -->
                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="available_in_menus" name="available_in_menus" 
                                                   <?php echo (!$editCategory) || ($editCategory && $editCategory['available_in_menus']) ? 'checked' : ''; ?>>
                                            <label class="custom-control-label" for="available_in_menus">
                                                <i class="fas fa-sitemap"></i> Disponible en Menús
                                            </label>
                                        </div>
                                        <small class="text-muted">
                                            Si está marcado, la categoría aparecerá en "Elementos Disponibles" del editor de menús para que puedas organizarla donde desees.
                                        </small>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <button type="submit" class="btn btn-primary" id="submitBtn">
                                        <i class="fas fa-save"></i> <?php echo $editCategory ? 'Actualizar Categoría' : 'Crear Categoría'; ?>
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
                <p class="text-danger">Esta acción no se puede deshacer y también eliminará la categoría de todos los menús.</p>
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