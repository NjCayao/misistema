<?php
// admin/pages/content/menus_simple.php
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

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance()->getConnection();
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create':
                    $title = sanitize($_POST['title'] ?? '');
                    $url = sanitize($_POST['url'] ?? '');
                    $parent_id = intval($_POST['parent_id'] ?? 0) ?: null;
                    $menu_location = sanitize($_POST['menu_location'] ?? 'main');
                    $icon = sanitize($_POST['icon'] ?? '');
                    $target = sanitize($_POST['target'] ?? '_self');
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    
                    if (empty($title) || empty($url)) {
                        throw new Exception('El título y la URL son obligatorios');
                    }
                    
                    $stmt = $db->prepare("
                        INSERT INTO menu_items (title, url, parent_id, menu_location, icon, target, is_active, sort_order) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM menu_items mi WHERE mi.menu_location = ? AND mi.parent_id IS NULL))
                    ");
                    $stmt->execute([$title, $url, $parent_id, $menu_location, $icon, $target, $is_active, $menu_location]);
                    
                    $success = 'Elemento de menú creado exitosamente';
                    // Redireccionar después de crear para evitar re-envío
                    redirect($_SERVER['PHP_SELF'] . '?created=1');
                    break;
                    
                case 'update':
                    $id = intval($_POST['id'] ?? 0);
                    $title = sanitize($_POST['title'] ?? '');
                    $url = sanitize($_POST['url'] ?? '');
                    $parent_id = intval($_POST['parent_id'] ?? 0) ?: null;
                    $menu_location = sanitize($_POST['menu_location'] ?? 'main');
                    $icon = sanitize($_POST['icon'] ?? '');
                    $target = sanitize($_POST['target'] ?? '_self');
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    
                    if (empty($title) || empty($url) || $id <= 0) {
                        throw new Exception('Datos inválidos');
                    }
                    
                    if ($parent_id == $id) {
                        throw new Exception('Un elemento no puede ser padre de sí mismo');
                    }
                    
                    $stmt = $db->prepare("
                        UPDATE menu_items SET title = ?, url = ?, parent_id = ?, menu_location = ?, icon = ?, target = ?, is_active = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$title, $url, $parent_id, $menu_location, $icon, $target, $is_active, $id]);
                    
                    $success = 'Elemento de menú actualizado exitosamente';
                    // Redireccionar para limpiar formulario
                    redirect($_SERVER['PHP_SELF'] . '?updated=1');
                    break;
                    
                case 'delete':
                    $id = intval($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        throw new Exception('ID inválido');
                    }
                    
                    // Verificar si tiene elementos hijos
                    $stmt = $db->prepare("SELECT COUNT(*) FROM menu_items WHERE parent_id = ?");
                    $stmt->execute([$id]);
                    $childCount = $stmt->fetchColumn();
                    
                    if ($childCount > 0) {
                        throw new Exception('No se puede eliminar un elemento que tiene subelementos. Elimina primero los subelementos.');
                    }
                    
                    $stmt = $db->prepare("DELETE FROM menu_items WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    $success = 'Elemento de menú eliminado exitosamente';
                    redirect($_SERVER['PHP_SELF'] . '?deleted=1');
                    break;
                    
                case 'toggle_status':
                    $id = intval($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        throw new Exception('ID inválido');
                    }
                    
                    $stmt = $db->prepare("UPDATE menu_items SET is_active = NOT is_active WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    $success = 'Estado del elemento actualizado';
                    redirect($_SERVER['PHP_SELF'] . '?toggled=1');
                    break;
                    
                case 'move_up':
                case 'move_down':
                    $id = intval($_POST['id'] ?? 0);
                    $direction = $_POST['action'] == 'move_up' ? -1 : 1;
                    
                    $stmt = $db->prepare("SELECT sort_order, menu_location, parent_id FROM menu_items WHERE id = ?");
                    $stmt->execute([$id]);
                    $current = $stmt->fetch();
                    
                    if ($current) {
                        $newOrder = $current['sort_order'] + $direction;
                        
                        // Encontrar elemento con el que intercambiar
                        $stmt = $db->prepare("
                            SELECT id FROM menu_items 
                            WHERE sort_order = ? AND menu_location = ? AND parent_id " . 
                            ($current['parent_id'] ? "= " . $current['parent_id'] : "IS NULL")
                        );
                        $stmt->execute([$newOrder, $current['menu_location']]);
                        $swap = $stmt->fetch();
                        
                        if ($swap) {
                            // Intercambiar posiciones
                            $stmt = $db->prepare("UPDATE menu_items SET sort_order = ? WHERE id = ?");
                            $stmt->execute([$newOrder, $id]);
                            
                            $stmt = $db->prepare("UPDATE menu_items SET sort_order = ? WHERE id = ?");
                            $stmt->execute([$current['sort_order'], $swap['id']]);
                            
                            $success = 'Orden actualizado';
                            redirect($_SERVER['PHP_SELF'] . '?moved=1');
                        }
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        logError("Error en menús: " . $e->getMessage());
    }
}

// Obtener elementos de menú
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("
        SELECT m.*, p.title as parent_title 
        FROM menu_items m 
        LEFT JOIN menu_items p ON m.parent_id = p.id 
        ORDER BY m.menu_location ASC, m.sort_order ASC, m.title ASC
    ");
    $menuItems = $stmt->fetchAll();
} catch (Exception $e) {
    $menuItems = [];
    $error = 'Error al obtener elementos de menú: ' . $e->getMessage();
}

// Agrupar por ubicación
$menusByLocation = [];
foreach ($menuItems as $item) {
    $menusByLocation[$item['menu_location']][] = $item;
}

// Organizar en estructura jerárquica
function buildMenuTree($items, $parentId = null) {
    $tree = [];
    foreach ($items as $item) {
        if ($item['parent_id'] == $parentId) {
            $item['children'] = buildMenuTree($items, $item['id']);
            $tree[] = $item;
        }
    }
    return $tree;
}

// Ubicaciones de menú disponibles
$menuLocations = [
    'main' => 'Menú Principal',
    'footer' => 'Menú Footer',
    'sidebar' => 'Menú Lateral',
    'mobile' => 'Menú Móvil',
    'user' => 'Menú Usuario'
];

// Si viene de actualización exitosa
if (isset($_GET['updated'])) {
    $success = 'Elemento de menú actualizado exitosamente';
}
$editItem = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    try {
        $stmt = $db->prepare("SELECT * FROM menu_items WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $editItem = $stmt->fetch();
    } catch (Exception $e) {
        $error = 'Error al obtener elemento para editar';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editor de Menús | <?php echo getSetting('site_name', 'MiSistema'); ?></title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/dist/css/adminlte.min.css">
    
    <style>
        .menu-item-card {
            border-left: 4px solid #007bff;
            margin-bottom: 10px;
        }
        .menu-item-child {
            border-left-color: #28a745;
            margin-left: 30px;
        }
        .menu-item-actions {
            display: flex;
            gap: 5px;
        }
        .inactive-item {
            opacity: 0.6;
            border-left-color: #6c757d;
        }
        .location-section {
            margin-bottom: 30px;
        }
        .menu-preview {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-top: 15px;
        }
        .menu-preview ul {
            list-style: none;
            padding-left: 0;
            margin: 0;
        }
        .menu-preview ul ul {
            padding-left: 20px;
            margin-top: 5px;
        }
        .menu-preview li {
            padding: 5px 0;
        }
        .menu-preview a {
            text-decoration: none;
            color: #495057;
            display: flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 4px;
            transition: all 0.3s;
        }
        .menu-preview a:hover {
            color: #007bff;
            background-color: white;
        }
        .menu-preview .icon {
            margin-right: 8px;
            width: 16px;
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
                        <h1 class="m-0">Editor de Menús</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/index.php">Dashboard</a></li>
                            <li class="breadcrumb-item">Contenido</li>
                            <li class="breadcrumb-item active">Menús</li>
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
                    <!-- Lista de Menús -->
                    <div class="col-md-8">
                        <?php foreach ($menuLocations as $locationKey => $locationName): ?>
                            <div class="location-section">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fas fa-bars"></i> <?php echo $locationName; ?>
                                        </h3>
                                        <div class="card-tools">
                                            <span class="badge badge-info">
                                                <?php echo count($menusByLocation[$locationKey] ?? []); ?> elementos
                                            </span>
                                            <button type="button" class="btn btn-sm btn-info" onclick="showPreview('<?php echo $locationKey; ?>')">
                                                <i class="fas fa-eye"></i> Vista Previa
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <?php 
                                        $locationItems = $menusByLocation[$locationKey] ?? [];
                                        $menuTree = buildMenuTree($locationItems);
                                        
                                        if (empty($menuTree)): ?>
                                            <div class="text-center text-muted p-4">
                                                <i class="fas fa-bars fa-3x mb-3"></i>
                                                <p>No hay elementos en este menú</p>
                                                <a href="?create=<?php echo $locationKey; ?>" class="btn btn-primary">
                                                    <i class="fas fa-plus"></i> Agregar Primer Elemento
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($menuTree as $item): ?>
                                                <?php renderMenuItemCard($item); ?>
                                            <?php endforeach; ?>
                                        <?php endif; ?>

                                        <!-- Vista previa -->
                                        <div class="menu-preview" id="preview-<?php echo $locationKey; ?>" style="display: none;">
                                            <h6>Vista Previa del Menú:</h6>
                                            <ul>
                                                <?php foreach ($menuTree as $item): ?>
                                                    <?php if ($item['is_active']): ?>
                                                        <li>
                                                            <a href="<?php echo $item['url']; ?>" target="<?php echo $item['target']; ?>">
                                                                <?php if ($item['icon']): ?>
                                                                    <i class="<?php echo $item['icon']; ?> icon"></i>
                                                                <?php endif; ?>
                                                                <?php echo htmlspecialchars($item['title']); ?>
                                                            </a>
                                                            <?php if (!empty($item['children'])): ?>
                                                                <ul>
                                                                    <?php foreach ($item['children'] as $child): ?>
                                                                        <?php if ($child['is_active']): ?>
                                                                            <li>
                                                                                <a href="<?php echo $child['url']; ?>" target="<?php echo $child['target']; ?>">
                                                                                    <?php if ($child['icon']): ?>
                                                                                        <i class="<?php echo $child['icon']; ?> icon"></i>
                                                                                    <?php endif; ?>
                                                                                    <?php echo htmlspecialchars($child['title']); ?>
                                                                                </a>
                                                                            </li>
                                                                        <?php endif; ?>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Formulario -->
                    <div class="col-md-4">
                        <div class="card sticky-top">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <?php echo $editItem ? 'Editar Elemento' : 'Nuevo Elemento'; ?>
                                </h3>
                                <?php if ($editItem): ?>
                                    <div class="card-tools">
                                        <a href="?" class="btn btn-sm btn-secondary">
                                            <i class="fas fa-times"></i> Cancelar
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <form method="post">
                                <div class="card-body">
                                    <input type="hidden" name="action" value="<?php echo $editItem ? 'update' : 'create'; ?>">
                                    <?php if ($editItem): ?>
                                        <input type="hidden" name="id" value="<?php echo $editItem['id']; ?>">
                                    <?php endif; ?>

                                    <div class="form-group">
                                        <label for="menu_location">Ubicación del Menú *</label>
                                        <select class="form-control" id="menu_location" name="menu_location" required>
                                            <?php foreach ($menuLocations as $key => $name): ?>
                                                <option value="<?php echo $key; ?>" 
                                                    <?php echo ($editItem && $editItem['menu_location'] == $key) || (!$editItem && isset($_GET['create']) && $_GET['create'] == $key) ? 'selected' : ''; ?>>
                                                    <?php echo $name; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="title">Título *</label>
                                        <input type="text" class="form-control" id="title" name="title" 
                                               value="<?php echo $editItem ? htmlspecialchars($editItem['title']) : ''; ?>" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="url">URL *</label>
                                        <input type="text" class="form-control" id="url" name="url" 
                                               value="<?php echo $editItem ? htmlspecialchars($editItem['url']) : ''; ?>" required placeholder="/">
                                        <small class="text-muted">
                                            Ejemplos: /, /productos, /contacto, https://externa.com
                                        </small>
                                    </div>

                                    <div class="form-group">
                                        <label for="parent_id">Elemento Padre</label>
                                        <select class="form-control" id="parent_id" name="parent_id">
                                            <option value="">Elemento principal</option>
                                            <?php foreach ($menuItems as $item): ?>
                                                <?php if ($item['parent_id'] == null && (!$editItem || $item['id'] != $editItem['id'])): ?>
                                                    <option value="<?php echo $item['id']; ?>" 
                                                        <?php echo ($editItem && $editItem['parent_id'] == $item['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($item['title']); ?>
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="icon">Icono (Font Awesome)</label>
                                        <input type="text" class="form-control" id="icon" name="icon" 
                                               value="<?php echo $editItem ? htmlspecialchars($editItem['icon']) : ''; ?>" placeholder="fas fa-home">
                                    </div>

                                    <div class="form-group">
                                        <label for="target">Destino del Enlace</label>
                                        <select class="form-control" id="target" name="target">
                                            <option value="_self" <?php echo (!$editItem || $editItem['target'] == '_self') ? 'selected' : ''; ?>>Misma ventana</option>
                                            <option value="_blank" <?php echo ($editItem && $editItem['target'] == '_blank') ? 'selected' : ''; ?>>Nueva ventana</option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" 
                                                   <?php echo (!$editItem || $editItem['is_active']) ? 'checked' : ''; ?>>
                                            <label class="custom-control-label" for="is_active">Elemento Activo</label>
                                        </div>
                                    </div>

                                    <!-- Elementos rápidos -->
                                    <div class="form-group">
                                        <label>Elementos Rápidos</label>
                                        <div class="btn-group-vertical btn-block">
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="quickFill('Inicio', '/', 'fas fa-home')">
                                                <i class="fas fa-home"></i> Inicio
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="quickFill('Productos', '/productos', 'fas fa-box')">
                                                <i class="fas fa-box"></i> Productos
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="quickFill('Sobre Nosotros', '/sobre-nosotros', 'fas fa-info-circle')">
                                                <i class="fas fa-info-circle"></i> Sobre Nosotros
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="quickFill('Contacto', '/contacto', 'fas fa-envelope')">
                                                <i class="fas fa-envelope"></i> Contacto
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fas fa-save"></i> <?php echo $editItem ? 'Actualizar' : 'Crear'; ?> Elemento
                                    </button>
                                    <?php if ($editItem): ?>
                                        <a href="?" class="btn btn-secondary btn-block">
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

<?php
function renderMenuItemCard($item, $level = 0) {
    $childClass = $level > 0 ? 'menu-item-child' : '';
    $statusClass = $item['is_active'] ? '' : 'inactive-item';
    ?>
    <div class="card menu-item-card <?php echo $childClass; ?> <?php echo $statusClass; ?>">
        <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div class="flex-grow-1">
                    <h6 class="mb-1">
                        <?php if ($item['icon']): ?>
                            <i class="<?php echo htmlspecialchars($item['icon']); ?> mr-2"></i>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($item['title']); ?>
                        <?php if (!$item['is_active']): ?>
                            <span class="badge badge-secondary ml-2">Inactivo</span>
                        <?php endif; ?>
                    </h6>
                    <small class="text-muted">
                        <?php echo htmlspecialchars($item['url']); ?>
                        <?php if ($item['target'] == '_blank'): ?>
                            <i class="fas fa-external-link-alt ml-1"></i>
                        <?php endif; ?>
                    </small>
                </div>
                <div class="menu-item-actions">
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="move_up">
                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="Subir">
                            <i class="fas fa-arrow-up"></i>
                        </button>
                    </form>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="move_down">
                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="Bajar">
                            <i class="fas fa-arrow-down"></i>
                        </button>
                    </form>
                    <a href="?edit=<?php echo $item['id']; ?>" class="btn btn-sm btn-info" title="Editar">
                        <i class="fas fa-edit"></i>
                    </a>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                        <button type="submit" class="btn btn-sm <?php echo $item['is_active'] ? 'btn-warning' : 'btn-success'; ?>" 
                                title="<?php echo $item['is_active'] ? 'Ocultar' : 'Mostrar'; ?>">
                            <i class="fas fa-<?php echo $item['is_active'] ? 'eye-slash' : 'eye'; ?>"></i>
                        </button>
                    </form>
                    <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['title']); ?>')" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (!empty($item['children'])): ?>
        <?php foreach ($item['children'] as $child): ?>
            <?php renderMenuItemCard($child, $level + 1); ?>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php
}
?>

<script src="<?php echo ADMINLTE_URL; ?>/plugins/jquery/jquery.min.js"></script>
<script src="<?php echo ADMINLTE_URL; ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo ADMINLTE_URL; ?>/dist/js/adminlte.min.js"></script>

<script>
function quickFill(title, url, icon) {
    $('#title').val(title);
    $('#url').val(url);
    $('#icon').val(icon);
}

function showPreview(location) {
    $('#preview-' + location).toggle();
}

function confirmDelete(id, name) {
    $('#elementNameToDelete').text(name);
    $('#elementIdToDelete').val(id);
    $('#deleteModal').modal('show');
}
</script>
</body>
</html>

<!-- Modal de confirmación de eliminación -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Confirmar Eliminación</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de que deseas eliminar el elemento de menú <strong id="elementNameToDelete"></strong>?</p>
                <p class="text-info">
                    <i class="fas fa-info-circle"></i> 
                    Esto solo eliminará el enlace del menú, no afectará páginas o contenido real.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <form method="post" style="display: inline;" id="deleteForm">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="elementIdToDelete">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Eliminar Enlace
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>