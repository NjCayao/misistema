<?php
// admin/pages/products/index.php
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
    $success = 'Producto creado exitosamente';
} elseif (isset($_GET['updated'])) {
    $success = 'Producto actualizado exitosamente';
} elseif (isset($_GET['deleted'])) {
    $success = 'Producto eliminado exitosamente';
}

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance()->getConnection();

        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create':
                    $name = sanitize($_POST['name'] ?? '');
                    $description = $_POST['description'] ?? '';
                    $short_description = sanitize($_POST['short_description'] ?? '');
                    $price = floatval($_POST['price'] ?? 0);
                    $category_id = intval($_POST['category_id'] ?? 0) ?: null;
                    $is_free = isset($_POST['is_free']) ? 1 : 0;
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
                    $demo_url = sanitize($_POST['demo_url'] ?? '');
                    $meta_title = sanitize($_POST['meta_title'] ?? '');
                    $meta_description = sanitize($_POST['meta_description'] ?? '');
                    $download_limit = intval($_POST['download_limit'] ?? 5);
                    $update_months = intval($_POST['update_months'] ?? 12);

                    if (empty($name)) {
                        throw new Exception('El nombre del producto es obligatorio');
                    }

                    $slug = generateSlug($name);

                    // Verificar que el slug no exista
                    $stmt = $db->prepare("SELECT id FROM products WHERE slug = ?");
                    $stmt->execute([$slug]);
                    if ($stmt->fetch()) {
                        throw new Exception('Ya existe un producto con ese nombre');
                    }

                    // Manejar subida de imagen
                    $imagePath = '';
                    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                        $uploadResult = uploadFile($_FILES['image'], UPLOADS_PATH . '/products', ALLOWED_IMAGE_TYPES);
                        if ($uploadResult['success']) {
                            $imagePath = $uploadResult['filename'];
                        } else {
                            throw new Exception('Error al subir imagen: ' . $uploadResult['message']);
                        }
                    }

                    $stmt = $db->prepare("
                        INSERT INTO products (
                            category_id, name, slug, description, short_description, price, is_free, 
                            is_active, is_featured, image, demo_url, meta_title, meta_description, 
                            download_limit, update_months
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $category_id,
                        $name,
                        $slug,
                        $description,
                        $short_description,
                        $price,
                        $is_free,
                        $is_active,
                        $is_featured,
                        $imagePath,
                        $demo_url,
                        $meta_title,
                        $meta_description,
                        $download_limit,
                        $update_months
                    ]);

                    $success = 'Producto creado exitosamente';
                    redirect($_SERVER['PHP_SELF'] . '?created=1');
                    break;

                case 'update':
                    $id = intval($_POST['id'] ?? 0);
                    $name = sanitize($_POST['name'] ?? '');
                    $description = $_POST['description'] ?? '';
                    $short_description = sanitize($_POST['short_description'] ?? '');
                    $price = floatval($_POST['price'] ?? 0);
                    $category_id = intval($_POST['category_id'] ?? 0) ?: null;
                    $is_free = isset($_POST['is_free']) ? 1 : 0;
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
                    $demo_url = sanitize($_POST['demo_url'] ?? '');
                    $meta_title = sanitize($_POST['meta_title'] ?? '');
                    $meta_description = sanitize($_POST['meta_description'] ?? '');
                    $download_limit = intval($_POST['download_limit'] ?? 5);
                    $update_months = intval($_POST['update_months'] ?? 12);

                    if (empty($name) || $id <= 0) {
                        throw new Exception('Datos inválidos');
                    }

                    $slug = generateSlug($name);

                    // Verificar que el slug no exista (excepto para el mismo registro)
                    $stmt = $db->prepare("SELECT id FROM products WHERE slug = ? AND id != ?");
                    $stmt->execute([$slug, $id]);
                    if ($stmt->fetch()) {
                        throw new Exception('Ya existe un producto con ese nombre');
                    }

                    // Obtener imagen actual
                    $stmt = $db->prepare("SELECT image FROM products WHERE id = ?");
                    $stmt->execute([$id]);
                    $currentImage = $stmt->fetchColumn();

                    $imagePath = $currentImage;

                    // Manejar nueva imagen
                    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                        $uploadResult = uploadFile($_FILES['image'], UPLOADS_PATH . '/products', ALLOWED_IMAGE_TYPES);
                        if ($uploadResult['success']) {
                            // Eliminar imagen anterior si existe
                            if ($currentImage && file_exists(UPLOADS_PATH . '/products/' . $currentImage)) {
                                unlink(UPLOADS_PATH . '/products/' . $currentImage);
                            }
                            $imagePath = $uploadResult['filename'];
                        } else {
                            throw new Exception('Error al subir imagen: ' . $uploadResult['message']);
                        }
                    }

                    $stmt = $db->prepare("
                        UPDATE products SET 
                            category_id = ?, name = ?, slug = ?, description = ?, short_description = ?, 
                            price = ?, is_free = ?, is_active = ?, is_featured = ?, image = ?, demo_url = ?, 
                            meta_title = ?, meta_description = ?, download_limit = ?, update_months = ?, 
                            updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $category_id,
                        $name,
                        $slug,
                        $description,
                        $short_description,
                        $price,
                        $is_free,
                        $is_active,
                        $is_featured,
                        $imagePath,
                        $demo_url,
                        $meta_title,
                        $meta_description,
                        $download_limit,
                        $update_months,
                        $id
                    ]);

                    $success = 'Producto actualizado exitosamente';
                    redirect($_SERVER['PHP_SELF'] . '?updated=1');
                    break;

                case 'delete':
                    $id = intval($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        throw new Exception('ID inválido');
                    }

                    // Verificar que no tenga órdenes asociadas
                    $stmt = $db->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
                    $stmt->execute([$id]);
                    $orderCount = $stmt->fetchColumn();

                    if ($orderCount > 0) {
                        throw new Exception('No se puede eliminar un producto que tiene órdenes asociadas');
                    }

                    // Obtener imagen e información para eliminar
                    $stmt = $db->prepare("SELECT image FROM products WHERE id = ?");
                    $stmt->execute([$id]);
                    $image = $stmt->fetchColumn();

                    // Eliminar versiones y archivos asociados
                    $stmt = $db->prepare("SELECT file_path FROM product_versions WHERE product_id = ?");
                    $stmt->execute([$id]);
                    $versions = $stmt->fetchAll();

                    foreach ($versions as $version) {
                        if ($version['file_path'] && file_exists($version['file_path'])) {
                            unlink($version['file_path']);
                        }
                    }

                    // Eliminar producto (esto eliminará versiones por cascada)
                    $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
                    $stmt->execute([$id]);

                    // Eliminar imagen si existe
                    if ($image && file_exists(UPLOADS_PATH . '/products/' . $image)) {
                        unlink(UPLOADS_PATH . '/products/' . $image);
                    }

                    $success = 'Producto eliminado exitosamente';
                    redirect($_SERVER['PHP_SELF'] . '?deleted=1');
                    break;

                case 'add_version':
                    $product_id = intval($_POST['product_id'] ?? 0);
                    $version = sanitize($_POST['version'] ?? '');
                    $changelog = sanitize($_POST['changelog'] ?? '');
                    $is_current = isset($_POST['is_current']) ? 1 : 0;

                    if ($product_id <= 0 || empty($version)) {
                        throw new Exception('Datos de versión inválidos');
                    }

                    // Verificar que el producto existe
                    $stmt = $db->prepare("SELECT id, name FROM products WHERE id = ?");
                    $stmt->execute([$product_id]);
                    $product = $stmt->fetch();
                    if (!$product) {
                        throw new Exception('Producto no encontrado');
                    }

                    // Verificar que la versión no exista
                    $stmt = $db->prepare("SELECT id FROM product_versions WHERE product_id = ? AND version = ?");
                    $stmt->execute([$product_id, $version]);
                    if ($stmt->fetch()) {
                        throw new Exception('Ya existe esa versión para este producto');
                    }

                    // Manejar subida de archivo
                    if (!isset($_FILES['version_file']) || $_FILES['version_file']['error'] !== UPLOAD_ERR_OK) {
                        throw new Exception('Debe seleccionar un archivo para la versión');
                    }

                    $file = $_FILES['version_file'];
                    $allowedTypes = ['application/zip', 'application/x-zip-compressed', 'application/x-rar-compressed', 'application/octet-stream'];

                    if (!in_array($file['type'], $allowedTypes) && !in_array(pathinfo($file['name'], PATHINFO_EXTENSION), ['zip', 'rar'])) {
                        throw new Exception('Solo se permiten archivos ZIP o RAR');
                    }

                    if ($file['size'] > 500 * 1024 * 1024) { // 500MB máximo
                        throw new Exception('El archivo no puede ser mayor a 500MB');
                    }

                    // Crear directorio si no existe
                    $productSlug = generateSlug($product['name']);
                    $versionDir = DOWNLOADS_PATH . '/products/' . $productSlug . '/' . $version;
                    if (!is_dir($versionDir)) {
                        mkdir($versionDir, 0755, true);
                    }

                    // Generar nombre único para el archivo
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $fileName = $productSlug . '_v' . $version . '.' . $extension;
                    $filePath = $versionDir . '/' . $fileName;

                    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                        throw new Exception('Error al guardar el archivo');
                    }

                    // Si es versión actual, quitar la marca de las otras
                    if ($is_current) {
                        $stmt = $db->prepare("UPDATE product_versions SET is_current = 0 WHERE product_id = ?");
                        $stmt->execute([$product_id]);
                    }

                    // Insertar nueva versión
                    $stmt = $db->prepare("
                        INSERT INTO product_versions (product_id, version, file_path, file_size, changelog, is_current) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$product_id, $version, $filePath, $file['size'], $changelog, $is_current]);

                    $success = 'Versión agregada exitosamente';
                    // Redirigir de vuelta al modal
                    redirect($_SERVER['PHP_SELF'] . '?versions=' . $product_id . '&success=version_added');
                    break;

                case 'delete_version':
                    $version_id = intval($_POST['version_id'] ?? 0);
                    if ($version_id <= 0) {
                        throw new Exception('ID de versión inválido');
                    }

                    // Obtener información de la versión
                    $stmt = $db->prepare("SELECT file_path, product_id FROM product_versions WHERE id = ?");
                    $stmt->execute([$version_id]);
                    $version = $stmt->fetch();

                    if (!$version) {
                        throw new Exception('Versión no encontrada');
                    }

                    // Eliminar archivo físico
                    if ($version['file_path'] && file_exists($version['file_path'])) {
                        unlink($version['file_path']);
                    }

                    // Eliminar registro de BD
                    $stmt = $db->prepare("DELETE FROM product_versions WHERE id = ?");
                    $stmt->execute([$version_id]);

                    $success = 'Versión eliminada exitosamente';
                    redirect($_SERVER['PHP_SELF'] . '?versions=' . $version['product_id'] . '&success=version_deleted');
                    break;

                case 'set_current_version':
                    $version_id = intval($_POST['version_id'] ?? 0);
                    if ($version_id <= 0) {
                        throw new Exception('ID de versión inválido');
                    }

                    // Obtener product_id
                    $stmt = $db->prepare("SELECT product_id FROM product_versions WHERE id = ?");
                    $stmt->execute([$version_id]);
                    $result = $stmt->fetch();

                    if (!$result) {
                        throw new Exception('Versión no encontrada');
                    }

                    $product_id = $result['product_id'];

                    // Quitar marca de versión actual de todas las versiones del producto
                    $stmt = $db->prepare("UPDATE product_versions SET is_current = 0 WHERE product_id = ?");
                    $stmt->execute([$product_id]);

                    // Marcar como actual la versión seleccionada
                    $stmt = $db->prepare("UPDATE product_versions SET is_current = 1 WHERE id = ?");
                    $stmt->execute([$version_id]);

                    $success = 'Versión actual actualizada';
                    redirect($_SERVER['PHP_SELF'] . '?versions=' . $product_id . '&success=current_updated');
                    break;
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        logError("Error en productos: " . $e->getMessage());
    }
}

// Obtener productos con información de categoría
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("
        SELECT p.*, c.name as category_name,
               (SELECT COUNT(*) FROM product_versions pv WHERE pv.product_id = p.id) as version_count
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        ORDER BY p.created_at DESC
    ");
    $products = $stmt->fetchAll();
} catch (Exception $e) {
    $products = [];
    $error = 'Error al obtener productos: ' . $e->getMessage();
}

// Obtener categorías para el formulario
try {
    $stmt = $db->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC");
    $categories = $stmt->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

// Lógica de edición/creación
$editProduct = null;
$createMode = isset($_GET['create']);

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    try {
        $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $editProduct = $stmt->fetch();
        if (!$editProduct) {
            $error = 'Producto no encontrado';
            redirect($_SERVER['PHP_SELF']);
        }
    } catch (Exception $e) {
        $error = 'Error al obtener producto para editar';
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Productos | <?php echo getSetting('site_name', 'MiSistema'); ?></title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/dist/css/adminlte.min.css">

    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/plugins/toastr/toastr.min.css">

    <!-- Summernote -->
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/plugins/summernote/summernote-bs4.min.css">

    <style>
        .product-image {
            width: 80px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
        }

        .product-preview {
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .note-editor {
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
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
                            <h1 class="m-0">Gestión de Productos</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/index.php">Dashboard</a></li>
                                <li class="breadcrumb-item">Productos</li>
                                <li class="breadcrumb-item active">Todos los Productos</li>
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

                    <?php if (!$editProduct && !$createMode): ?>
                        <!-- Vista de Lista -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">Lista de Productos</h3>
                                        <div class="card-tools">
                                            <a href="?create=1" class="btn btn-primary">
                                                <i class="fas fa-plus"></i> Nuevo Producto
                                            </a>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-striped" id="productsTable">
                                                <thead>
                                                    <tr>
                                                        <th>Imagen</th>
                                                        <th>Producto</th>
                                                        <th>Categoría</th>
                                                        <th>Precio</th>
                                                        <th>Estado</th>
                                                        <th>Versiones</th>
                                                        <th width="120">Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($products as $product): ?>
                                                        <tr>
                                                            <td>
                                                                <?php if ($product['image']): ?>
                                                                    <img src="<?php echo UPLOADS_URL; ?>/products/<?php echo htmlspecialchars($product['image']); ?>"
                                                                        alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                                        class="product-image">
                                                                <?php else: ?>
                                                                    <div class="product-image bg-light d-flex align-items-center justify-content-center">
                                                                        <i class="fas fa-image text-muted"></i>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                                <?php if ($product['is_featured']): ?>
                                                                    <span class="badge badge-warning ml-2">Destacado</span>
                                                                <?php endif; ?>
                                                                <br>
                                                                <div class="product-preview text-muted">
                                                                    <?php echo htmlspecialchars($product['short_description']); ?>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <?php if ($product['category_name']): ?>
                                                                    <span class="badge badge-info"><?php echo htmlspecialchars($product['category_name']); ?></span>
                                                                <?php else: ?>
                                                                    <span class="text-muted">Sin categoría</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($product['is_free']): ?>
                                                                    <span class="badge badge-success">GRATIS</span>
                                                                <?php else: ?>
                                                                    <strong><?php echo formatPrice($product['price']); ?></strong>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($product['is_active']): ?>
                                                                    <span class="badge badge-success">Activo</span>
                                                                <?php else: ?>
                                                                    <span class="badge badge-secondary">Inactivo</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <span class="badge badge-primary"><?php echo $product['version_count']; ?> versiones</span>
                                                            </td>
                                                            <td>
                                                                <a href="?edit=<?php echo $product['id']; ?>" class="btn btn-sm btn-info" title="Editar">
                                                                    <i class="fas fa-edit"></i>
                                                                </a>
                                                                <button class="btn btn-sm btn-success" onclick="openVersionsModal(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')" title="Gestionar Versiones">
                                                                    <i class="fas fa-code-branch"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-danger" onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')" title="Eliminar">
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
                        </div>
                    <?php else: ?>
                        <!-- Vista de Edición/Creación -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <?php echo $createMode ? 'Nuevo Producto' : 'Editar Producto'; ?>
                                        </h3>
                                        <div class="card-tools">
                                            <a href="?" class="btn btn-secondary">
                                                <i class="fas fa-arrow-left"></i> Volver a la Lista
                                            </a>
                                        </div>
                                    </div>
                                    <form method="post" enctype="multipart/form-data">
                                        <div class="card-body">
                                            <input type="hidden" name="action" value="<?php echo $createMode ? 'create' : 'update'; ?>">
                                            <?php if ($editProduct): ?>
                                                <input type="hidden" name="id" value="<?php echo $editProduct['id']; ?>">
                                            <?php endif; ?>

                                            <div class="row">
                                                <!-- Información Principal -->
                                                <div class="col-md-8">
                                                    <div class="card">
                                                        <div class="card-header">
                                                            <h3 class="card-title">Información Principal</h3>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="form-group">
                                                                <label for="name">Nombre del Producto *</label>
                                                                <input type="text" class="form-control" id="name" name="name"
                                                                    value="<?php echo $editProduct ? htmlspecialchars($editProduct['name']) : ''; ?>" required>
                                                            </div>

                                                            <div class="form-group">
                                                                <label for="short_description">Descripción Corta</label>
                                                                <input type="text" class="form-control" id="short_description" name="short_description"
                                                                    value="<?php echo $editProduct ? htmlspecialchars($editProduct['short_description']) : ''; ?>"
                                                                    maxlength="500" placeholder="Descripción breve para listados">
                                                            </div>

                                                            <div class="form-group">
                                                                <label for="description">Descripción Completa</label>
                                                                <textarea class="form-control" id="description" name="description" rows="10"><?php echo $editProduct ? htmlspecialchars($editProduct['description']) : ''; ?></textarea>
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="form-group">
                                                                        <label for="category_id">Categoría</label>
                                                                        <select class="form-control" id="category_id" name="category_id">
                                                                            <option value="">Sin categoría</option>
                                                                            <?php foreach ($categories as $category): ?>
                                                                                <option value="<?php echo $category['id']; ?>"
                                                                                    <?php echo ($editProduct && $editProduct['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                                                </option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="form-group">
                                                                        <label for="demo_url">URL de Demo</label>
                                                                        <input type="url" class="form-control" id="demo_url" name="demo_url"
                                                                            value="<?php echo $editProduct ? htmlspecialchars($editProduct['demo_url']) : ''; ?>"
                                                                            placeholder="https://demo.ejemplo.com">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- SEO -->
                                                    <div class="card">
                                                        <div class="card-header">
                                                            <h3 class="card-title">SEO</h3>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="form-group">
                                                                <label for="meta_title">Meta Título</label>
                                                                <input type="text" class="form-control" id="meta_title" name="meta_title"
                                                                    value="<?php echo $editProduct ? htmlspecialchars($editProduct['meta_title']) : ''; ?>"
                                                                    maxlength="60">
                                                                <small class="text-muted">Recomendado: 50-60 caracteres</small>
                                                            </div>

                                                            <div class="form-group">
                                                                <label for="meta_description">Meta Descripción</label>
                                                                <textarea class="form-control" id="meta_description" name="meta_description"
                                                                    rows="3" maxlength="160"><?php echo $editProduct ? htmlspecialchars($editProduct['meta_description']) : ''; ?></textarea>
                                                                <small class="text-muted">Recomendado: 150-160 caracteres</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Configuración -->
                                                <div class="col-md-4">
                                                    <div class="card">
                                                        <div class="card-header">
                                                            <h3 class="card-title">Configuración</h3>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="form-group">
                                                                <div class="custom-control custom-switch">
                                                                    <input type="checkbox" class="custom-control-input" id="is_active" name="is_active"
                                                                        <?php echo (!$editProduct || $editProduct['is_active']) ? 'checked' : ''; ?>>
                                                                    <label class="custom-control-label" for="is_active">Producto Activo</label>
                                                                </div>
                                                            </div>

                                                            <div class="form-group">
                                                                <div class="custom-control custom-switch">
                                                                    <input type="checkbox" class="custom-control-input" id="is_featured" name="is_featured"
                                                                        <?php echo ($editProduct && $editProduct['is_featured']) ? 'checked' : ''; ?>>
                                                                    <label class="custom-control-label" for="is_featured">Producto Destacado</label>
                                                                </div>
                                                            </div>

                                                            <div class="form-group">
                                                                <div class="custom-control custom-switch">
                                                                    <input type="checkbox" class="custom-control-input" id="is_free" name="is_free"
                                                                        <?php echo ($editProduct && $editProduct['is_free']) ? 'checked' : ''; ?>>
                                                                    <label class="custom-control-label" for="is_free">Producto Gratuito</label>
                                                                </div>
                                                            </div>

                                                            <div class="form-group" id="price_group">
                                                                <div class="card">
                                                                    <div class="card-header">
                                                                        <h3 class="card-title">
                                                                            <i class="fas fa-calculator"></i> Configuración de Precios
                                                                        </h3>
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <!-- Selector de modo de precio -->
                                                                        <div class="form-group">
                                                                            <label>Modo de Configuración</label>
                                                                            <div class="custom-control custom-radio">
                                                                                <input class="custom-control-input" type="radio" id="price_mode_final" name="price_mode" value="final" checked>
                                                                                <label for="price_mode_final" class="custom-control-label">Precio Final (lo que paga el cliente)</label>
                                                                            </div>
                                                                            <div class="custom-control custom-radio">
                                                                                <input class="custom-control-input" type="radio" id="price_mode_desired" name="price_mode" value="desired">
                                                                                <label for="price_mode_desired" class="custom-control-label">Precio Deseado (lo que quiero recibir)</label>
                                                                            </div>
                                                                        </div>

                                                                        <!-- Campo precio final -->
                                                                        <div class="form-group" id="final_price_group">
                                                                            <label for="price">Precio Final ($)</label>
                                                                            <input type="number" class="form-control" id="price" name="price"
                                                                                value="<?php echo $editProduct ? $editProduct['price'] : '0'; ?>"
                                                                                step="0.01" min="0">
                                                                            <small class="text-muted">Precio que pagará el cliente</small>
                                                                        </div>

                                                                        <!-- Campo precio deseado -->
                                                                        <div class="form-group" id="desired_price_group" style="display: none;">
                                                                            <label for="desired_price">Precio que Quiero Recibir ($)</label>
                                                                            <input type="number" class="form-control" id="desired_price"
                                                                                step="0.01" min="0" placeholder="Ej: 50.00">
                                                                            <small class="text-muted">Monto neto que deseas recibir después de comisiones</small>
                                                                        </div>

                                                                        <!-- Calculadora de precios -->
                                                                        <div class="mt-3" id="price_calculator" style="display: none;">
                                                                            <h6><i class="fas fa-chart-line"></i> Cálculo por Pasarela de Pago</h6>
                                                                            <div class="table-responsive">
                                                                                <table class="table table-sm">
                                                                                    <thead>
                                                                                        <tr>
                                                                                            <th>Pasarela</th>
                                                                                            <th>Comisión</th>
                                                                                            <th>Precio Final</th>
                                                                                            <th>Recibes</th>
                                                                                            <th>Comisión Total</th>
                                                                                        </tr>
                                                                                    </thead>
                                                                                    <tbody id="price_breakdown">
                                                                                        <!-- Se llena dinámicamente -->
                                                                                    </tbody>
                                                                                </table>
                                                                            </div>
                                                                            <div class="alert alert-info">
                                                                                <i class="fas fa-info-circle"></i>
                                                                                <strong>Recomendación:</strong> Usa el precio promedio sugerido: <span id="suggested_price">$0.00</span>
                                                                            </div>
                                                                        </div>

                                                                        <!-- Botón para aplicar precio sugerido -->
                                                                        <div class="text-center" id="apply_suggested_group" style="display: none;">
                                                                            <button type="button" class="btn btn-success btn-sm" onclick="applySuggestedPrice()">
                                                                                <i class="fas fa-magic"></i> Aplicar Precio Sugerido
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="card">
                                                        <div class="card-header">
                                                            <h3 class="card-title">Imagen del Producto</h3>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="form-group">
                                                                <input type="file" class="form-control-file" id="image" name="image" accept="image/*">
                                                                <small class="text-muted">Formatos: JPG, PNG, GIF. Máximo 5MB.</small>
                                                                <?php if ($editProduct && $editProduct['image']): ?>
                                                                    <div class="mt-2">
                                                                        <img src="<?php echo UPLOADS_URL; ?>/products/<?php echo htmlspecialchars($editProduct['image']); ?>"
                                                                            alt="Imagen actual" class="img-thumbnail" style="max-width: 200px;">
                                                                        <small class="d-block text-muted">Imagen actual</small>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="card">
                                                        <div class="card-header">
                                                            <h3 class="card-title">Límites y Configuración</h3>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="form-group">
                                                                <label for="download_limit">Límite de Descargas</label>
                                                                <input type="number" class="form-control" id="download_limit" name="download_limit"
                                                                    value="<?php echo $editProduct ? $editProduct['download_limit'] : '5'; ?>"
                                                                    min="1" max="100">
                                                                <small class="text-muted">Número máximo de descargas por cliente</small>
                                                            </div>

                                                            <div class="form-group">
                                                                <label for="update_months">Meses de Actualizaciones</label>
                                                                <input type="number" class="form-control" id="update_months" name="update_months"
                                                                    value="<?php echo $editProduct ? $editProduct['update_months'] : '12'; ?>"
                                                                    min="1" max="60">
                                                                <small class="text-muted">Meses de actualizaciones gratuitas</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-footer">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save"></i> <?php echo $createMode ? 'Crear Producto' : 'Actualizar Producto'; ?>
                                            </button>
                                            <a href="?" class="btn btn-secondary">
                                                <i class="fas fa-times"></i> Cancelar
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

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
                    <p>¿Estás seguro de que deseas eliminar el producto <strong id="productNameToDelete"></strong>?</p>
                    <p class="text-danger">Esta acción eliminará también todas las versiones y archivos asociados.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="productIdToDelete">
                        <button type="submit" class="btn btn-danger">Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Gestión de Versiones -->
    <div class="modal fade" id="versionsModal" tabindex="-1" role="dialog" aria-labelledby="versionsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="versionsModalLabel">
                        <i class="fas fa-code-branch"></i> Gestionar Versiones - <span id="productNameInModal"></span>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <!-- Formulario para agregar versión -->
                        <div class="col-md-5">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Agregar Nueva Versión</h3>
                                </div>
                                <form id="versionForm" method="post" enctype="multipart/form-data">
                                    <div class="card-body">
                                        <input type="hidden" name="action" value="add_version">
                                        <input type="hidden" name="product_id" id="modalProductId">

                                        <div class="form-group">
                                            <label for="version">Número de Versión *</label>
                                            <input type="text" class="form-control" id="version" name="version"
                                                placeholder="1.0, 1.1, 2.0, etc." required>
                                            <small class="text-muted">Formato: 1.0, 1.1, 2.0, etc.</small>
                                        </div>

                                        <div class="form-group">
                                            <label for="version_file">Archivo de la Versión *</label>
                                            <input type="file" class="form-control-file" id="version_file" name="version_file"
                                                accept=".zip,.rar" required>
                                            <small class="text-muted">Formatos permitidos: ZIP, RAR. Máximo 500MB.</small>
                                        </div>

                                        <div class="form-group">
                                            <label for="changelog">Changelog</label>
                                            <textarea class="form-control" id="changelog" name="changelog" rows="4"
                                                placeholder="Describe los cambios en esta versión..."></textarea>
                                        </div>

                                        <div class="form-group">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="is_current" name="is_current">
                                                <label class="custom-control-label" for="is_current">Marcar como versión actual</label>
                                            </div>
                                            <small class="text-muted">La versión actual será la que se descargue por defecto</small>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-upload"></i> Subir Versión
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Lista de versiones existentes -->
                        <div class="col-md-7">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Versiones Existentes</h3>
                                </div>
                                <div class="card-body p-0">
                                    <div id="versionsListContainer">
                                        <!-- Se carga dinámicamente con AJAX -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmación de eliminación de versión -->
    <div class="modal fade" id="deleteVersionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Confirmar Eliminación</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p>¿Estás seguro de que deseas eliminar la versión <strong id="versionToDelete"></strong>?</p>
                    <p class="text-danger">Esta acción eliminará también el archivo asociado.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="delete_version">
                        <input type="hidden" name="version_id" id="versionIdToDelete">
                        <button type="submit" class="btn btn-danger">Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para mostrar changelog completo -->
    <div class="modal fade" id="changelogModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-list"></i> Changelog - <span id="changelogVersion"></span>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div id="changelogContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
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
    <!-- Summernote -->
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/summernote/summernote-bs4.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/summernote/lang/summernote-es-ES.js"></script>
    <!-- AdminLTE App -->
    <script src="<?php echo ADMINLTE_URL; ?>/dist/js/adminlte.min.js"></script>

    <!-- Toastr JS -->
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/toastr/toastr.min.js"></script>


    <script>
        // Variables globales para el modal de versiones
        let currentProductId = null;

        function openVersionsModal(productId, productName) {
            currentProductId = productId;
            $('#modalProductId').val(productId);
            $('#productNameInModal').text(productName);

            // Limpiar formulario
            $('#versionForm')[0].reset();
            $('#modalProductId').val(productId);

            // Cargar versiones existentes
            loadVersionsList(productId);

            // Mostrar modal
            $('#versionsModal').modal('show');
        }

        function loadVersionsList(productId) {
            $('#versionsListContainer').html('<div class="text-center p-3"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>');

            // AJAX para cargar versiones
            $.ajax({
                url: 'versions_ajax.php',
                type: 'GET',
                data: {
                    product_id: productId
                },
                success: function(response) {
                    $('#versionsListContainer').html(response);
                },
                error: function() {
                    $('#versionsListContainer').html('<div class="alert alert-danger">Error al cargar versiones</div>');
                }
            });
        }

        function deleteVersion(versionId, version) {
            $('#versionToDelete').text(version);
            $('#versionIdToDelete').val(versionId);
            $('#deleteVersionModal').modal('show');
        }

        function setCurrentVersion(versionId) {
            if (confirm('¿Marcar esta versión como actual?')) {
                // Enviar formulario para marcar como actual
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
            <input type="hidden" name="action" value="set_current_version">
            <input type="hidden" name="version_id" value="${versionId}">
        `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function downloadVersion(versionId) {
            window.open('download_version.php?version_id=' + versionId, '_blank');
        }

        // Validación del formulario de versión
        $('#versionForm').on('submit', function(e) {
            const version = $('#version').val();
            const file = $('#version_file')[0].files[0];

            // Validar formato de versión
            if (!/^\d+\.\d+(\.\d+)?$/.test(version)) {
                alert('El formato de versión debe ser: 1.0, 1.1, 2.0, etc.');
                e.preventDefault();
                return;
            }

            // Validar archivo
            if (!file) {
                alert('Debe seleccionar un archivo');
                e.preventDefault();
                return;
            }

            // Validar tamaño (500MB)
            if (file.size > 500 * 1024 * 1024) {
                alert('El archivo no puede ser mayor a 500MB');
                e.preventDefault();
                return;
            }

            // Validar extensión
            const allowedExtensions = ['zip', 'rar'];
            const fileExtension = file.name.split('.').pop().toLowerCase();
            if (!allowedExtensions.includes(fileExtension)) {
                alert('Solo se permiten archivos ZIP o RAR');
                e.preventDefault();
                return;
            }
        });

        // Mostrar progreso de subida
        $('#versionForm').on('submit', function() {
            const submitBtn = $(this).find('button[type="submit"]');
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Subiendo...');
        });
    </script>

    <script>
        $(document).ready(function() {
            // Inicializar DataTable
            $('#productsTable').DataTable({
                "responsive": true,
                "lengthChange": false,
                "autoWidth": false,
                "order": [
                    [1, "asc"]
                ]
            });

            // Inicializar Summernote
            $('#description').summernote({
                height: 300,
                lang: 'es-ES',
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'italic', 'underline', 'clear']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link', 'picture']],
                    ['view', ['fullscreen', 'codeview', 'help']]
                ]
            });

            // Manejar checkbox de producto gratuito
            $('#is_free').change(function() {
                if ($(this).is(':checked')) {
                    $('#price').val('0').prop('disabled', true);
                    $('#price_group').hide();
                } else {
                    $('#price').prop('disabled', false);
                    $('#price_group').show();
                }
            });

            // Ejecutar al cargar si ya está marcado como gratuito
            if ($('#is_free').is(':checked')) {
                $('#price').val('0').prop('disabled', true);
                $('#price_group').hide();
            }

            // Contador de caracteres para SEO
            $('#meta_title').on('input', function() {
                const length = $(this).val().length;
                const color = length > 60 ? 'text-danger' : (length > 50 ? 'text-warning' : 'text-success');
                $(this).next('small').removeClass('text-muted text-success text-warning text-danger').addClass(color);
            });

            $('#meta_description').on('input', function() {
                const length = $(this).val().length;
                const color = length > 160 ? 'text-danger' : (length > 150 ? 'text-warning' : 'text-success');
                $(this).next('small').removeClass('text-muted text-success text-warning text-danger').addClass(color);
            });
        });

        function deleteProduct(id, name) {
            $('#productNameToDelete').text(name);
            $('#productIdToDelete').val(id);
            $('#deleteModal').modal('show');
        }
    </script>


    <!-- JavaScript para el cálculo de precios -->
    <script>
        // Configuración de comisiones (normalmente vendrían de la base de datos)
        const paymentGateways = {
            stripe: {
                name: 'Stripe',
                commission: <?php echo Settings::get('stripe_commission', '3.5'); ?>,
                fixedFee: <?php echo Settings::get('stripe_fixed_fee', '0.30'); ?>,
                enabled: <?php echo Settings::get('stripe_enabled', '0') == '1' ? 'true' : 'false'; ?>
            },
            paypal: {
                name: 'PayPal',
                commission: <?php echo Settings::get('paypal_commission', '4.5'); ?>,
                fixedFee: <?php echo Settings::get('paypal_fixed_fee', '0.25'); ?>,
                enabled: <?php echo Settings::get('paypal_enabled', '0') == '1' ? 'true' : 'false'; ?>
            },
            mercadopago: {
                name: 'MercadoPago',
                commission: <?php echo Settings::get('mercadopago_commission', '5.2'); ?>,
                fixedFee: <?php echo Settings::get('mercadopago_fixed_fee', '0.15'); ?>,
                enabled: <?php echo Settings::get('mercadopago_enabled', '0') == '1' ? 'true' : 'false'; ?>
            }
        };

        $(document).ready(function() {
            // Manejar cambio de modo de precio
            $('input[name="price_mode"]').change(function() {
                const mode = $(this).val();

                if (mode === 'final') {
                    $('#final_price_group').show();
                    $('#desired_price_group').hide();
                    $('#price_calculator').hide();
                    $('#apply_suggested_group').hide();
                } else {
                    $('#final_price_group').hide();
                    $('#desired_price_group').show();
                    $('#price_calculator').show();
                    $('#apply_suggested_group').show();
                    calculatePricesFromDesired();
                }
            });

            // Calcular precios cuando cambie el precio deseado
            $('#desired_price').on('input', calculatePricesFromDesired);

            // Calcular breakdown cuando cambie el precio final
            $('#price').on('input', function() {
                if ($('#price_mode_final').is(':checked')) {
                    calculateBreakdownFromFinal();
                }
            });
        });

        function calculatePricesFromDesired() {
            const desiredAmount = parseFloat($('#desired_price').val()) || 0;

            if (desiredAmount <= 0) {
                $('#price_breakdown').html('<tr><td colspan="5" class="text-center text-muted">Ingresa un monto deseado</td></tr>');
                $('#suggested_price').text('$0.00');
                return;
            }

            let html = '';
            let totalFinalPrices = 0;
            let enabledGateways = 0;

            Object.keys(paymentGateways).forEach(key => {
                const gateway = paymentGateways[key];

                if (gateway.enabled) {
                    // Calcular precio final: (deseado + tarifa_fija) / (1 - comision/100)
                    const finalPrice = (desiredAmount + gateway.fixedFee) / (1 - gateway.commission / 100);
                    const totalFees = finalPrice - desiredAmount;

                    html += `
                <tr>
                    <td><strong>${gateway.name}</strong></td>
                    <td>${gateway.commission}% + $${gateway.fixedFee}</td>
                    <td><strong>$${finalPrice.toFixed(2)}</strong></td>
                    <td class="text-success">$${desiredAmount.toFixed(2)}</td>
                    <td class="text-danger">$${totalFees.toFixed(2)}</td>
                </tr>
            `;

                    totalFinalPrices += finalPrice;
                    enabledGateways++;
                }
            });

            if (enabledGateways === 0) {
                html = '<tr><td colspan="5" class="text-center text-warning">No hay pasarelas de pago habilitadas</td></tr>';
                $('#suggested_price').text('$0.00');
            } else {
                const averagePrice = totalFinalPrices / enabledGateways;
                $('#suggested_price').text('$' + averagePrice.toFixed(2));
            }

            $('#price_breakdown').html(html);
        }

        function calculateBreakdownFromFinal() {
            const finalPrice = parseFloat($('#price').val()) || 0;

            if (finalPrice <= 0) {
                return;
            }

            // Mostrar qué recibirías con cada pasarela
            let html = '<div class="mt-2"><small class="text-muted">Con este precio recibirías:</small><ul class="list-unstyled">';

            Object.keys(paymentGateways).forEach(key => {
                const gateway = paymentGateways[key];

                if (gateway.enabled) {
                    // Calcular lo que recibes: precio_final * (1 - comision/100) - tarifa_fija
                    const received = (finalPrice * (1 - gateway.commission / 100)) - gateway.fixedFee;
                    const fees = finalPrice - received;

                    html += `<li><strong>${gateway.name}:</strong> $${Math.max(0, received).toFixed(2)} (comisión: $${fees.toFixed(2)})</li>`;
                }
            });

            html += '</ul></div>';

            // Insertar después del campo precio
            $('#final_price_group .text-muted').next().remove(); // Remover breakdown anterior
            $('#final_price_group').append(html);
        }

        function applySuggestedPrice() {
            const suggestedPrice = $('#suggested_price').text().replace('$', '');
            $('#price').val(suggestedPrice);

            // Cambiar a modo precio final
            $('#price_mode_final').prop('checked', true).trigger('change');

            // Mostrar confirmación
            toastr.success('Precio sugerido aplicado correctamente');
        }

        // Validación adicional en el formulario
        $('form').on('submit', function(e) {
            const isFree = $('#is_free').is(':checked');
            const price = parseFloat($('#price').val()) || 0;

            if (!isFree && price <= 0) {
                e.preventDefault();
                alert('El precio debe ser mayor a $0 para productos pagos');
                return false;
            }
        });
    </script>

    <!-- CSS adicional -->
    <style>
        #price_calculator .table th {
            font-size: 0.8rem;
            padding: 0.5rem;
        }

        #price_calculator .table td {
            font-size: 0.85rem;
            padding: 0.5rem;
        }

        .custom-control-label {
            font-size: 0.9rem;
        }

        #price_breakdown tr:hover {
            background-color: #f8f9fa;
        }
    </style>
</body>

</html>