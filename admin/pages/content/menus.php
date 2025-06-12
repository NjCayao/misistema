<?php
// admin/pages/content/menus.php
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
                    
                    // Verificar que no se asigne como padre de sí mismo
                    if ($parent_id == $id) {
                        throw new Exception('Un elemento no puede ser padre de sí mismo');
                    }
                    
                    $stmt = $db->prepare("
                        UPDATE menu_items SET title = ?, url = ?, parent_id = ?, menu_location = ?, icon = ?, target = ?, is_active = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$title, $url, $parent_id, $menu_location, $icon, $target, $is_active, $id]);
                    
                    $success = 'Elemento de menú actualizado exitosamente';
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
                        throw new Exception('No se puede eliminar un elemento que tiene subelementos');
                    }
                    
                    $stmt = $db->prepare("DELETE FROM menu_items WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    $success = 'Elemento de menú eliminado exitosamente';
                    break;
                    
                case 'update_order':
                    $items = $_POST['items'] ?? [];
                    foreach ($items as $item) {
                        $id = intval($item['id']);
                        $parent_id = intval($item['parent_id']) ?: null;
                        $sort_order = intval($item['sort_order']);
                        
                        $stmt = $db->prepare("UPDATE menu_items SET parent_id = ?, sort_order = ? WHERE id = ?");
                        $stmt->execute([$parent_id, $sort_order, $id]);
                    }
                    $success = 'Orden del menú actualizado exitosamente';
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editor de Menús | <?php echo getSetting('site_name', 'MiSistema'); ?></title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/dist/css/adminlte.min.css">
    
    <!-- Nestable CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/nestable2/1.6.0/jquery.nestable.min.css">
    
    <style>
        .menu-item {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            margin: 5px 0;
            padding: 15px;
            cursor: move;
            min-height: 80px;
        }
        .menu-item:hover {
            background: #e9ecef;
            border-color: #007bff;
        }
        .menu-item .dd-handle {
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 20px;
            background: #007bff;
            cursor: move;
            border-radius: 5px 0 0 5px;
        }
        .menu-item .dd-handle:before {
            content: '⋮⋮';
            color: white;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 12px;
            line-height: 1;
        }
        .menu-item .item-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-left: 25px;
        }
        .menu-item .item-info {
            flex: 1;
        }
        .menu-item .item-title {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 5px;
        }
        .menu-item .item-url {
            color: #6c757d;
            font-size: 13px;
        }
        .menu-item .item-actions {
            margin-left: 15px;
            display: flex;
            gap: 5px;
        }
        .menu-item.inactive {
            opacity: 0.6;
        }
        .dd-list {
            min-height: 100px;
            padding: 10px;
        }
        .dd-empty {
            border: 2px dashed #ced4da;
            padding: 40px 20px;
            text-align: center;
            color: #6c757d;
            background: #f8f9fa;
            border-radius: 5px;
            font-size: 16px;
        }
        .dd-item {
            position: relative;
        }
        .dd3-content {
            display: block;
            height: auto;
            margin: 5px 0;
            padding: 0;
            background: none;
            border: none;
        }
        .tab-content {
            padding: 20px 0;
        }
        .nav-tabs .nav-link {
            border-bottom: 3px solid transparent;
        }
        .nav-tabs .nav-link.active {
            border-bottom-color: #007bff;
            background-color: #f8f9fa;
        }
        .menu-preview {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
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
            background-color: #f8f9fa;
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
                    <!-- Editor de Menús -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Estructura de Menús</h3>
                                <div class="card-tools">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-info dropdown-toggle" data-toggle="dropdown">
                                            <i class="fas fa-eye"></i> Ver Menú
                                        </button>
                                        <div class="dropdown-menu">
                                            <?php foreach ($menuLocations as $key => $name): ?>
                                                <a class="dropdown-item" href="#" onclick="showMenuPreview('<?php echo $key; ?>')"><?php echo $name; ?></a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Tabs por ubicación -->
                                <ul class="nav nav-tabs" id="menuTabs">
                                    <?php $first = true; foreach ($menuLocations as $key => $name): ?>
                                        <li class="nav-item">
                                            <a class="nav-link <?php echo $first ? 'active' : ''; ?>" data-toggle="tab" href="#menu-<?php echo $key; ?>">
                                                <?php echo $name; ?>
                                                <span class="badge badge-info ml-1">
                                                    <?php echo count($menusByLocation[$key] ?? []); ?>
                                                </span>
                                            </a>
                                        </li>
                                    <?php $first = false; endforeach; ?>
                                </ul>

                                <div class="tab-content" id="menuTabContent">
                                    <?php $first = true; foreach ($menuLocations as $locationKey => $locationName): ?>
                                        <div class="tab-pane fade <?php echo $first ? 'show active' : ''; ?>" id="menu-<?php echo $locationKey; ?>">
                                            <div class="mt-3">
                                                <div class="dd" id="nestable-<?php echo $locationKey; ?>" data-location="<?php echo $locationKey; ?>">
                                                    <ol class="dd-list">
                                                        <?php
                                                        $locationItems = $menusByLocation[$locationKey] ?? [];
                                                        $menuTree = buildMenuTree($locationItems);
                                                        
                                                        function renderMenuItem($item, $level = 0) {
                                                            echo '<li class="dd-item menu-item ' . ($item['is_active'] ? '' : 'inactive') . '" data-id="' . $item['id'] . '">';
                                                            echo '<div class="dd-handle"></div>';
                                                            echo '<div class="dd3-content">';
                                                            echo '<div class="item-content">';
                                                            echo '<div class="item-info">';
                                                            echo '<div class="item-title">' . htmlspecialchars($item['title']);
                                                            if ($item['icon']) {
                                                                echo ' <i class="' . htmlspecialchars($item['icon']) . ' ml-2"></i>';
                                                            }
                                                            echo '</div>';
                                                            echo '<div class="item-url">' . htmlspecialchars($item['url']);
                                                            if ($item['target'] == '_blank') {
                                                                echo ' <i class="fas fa-external-link-alt text-info ml-1"></i>';
                                                            }
                                                            echo '</div>';
                                                            echo '</div>';
                                                            echo '<div class="item-actions">';
                                                            echo '<button class="btn btn-sm btn-info btn-edit-menu" data-item=\'' . htmlspecialchars(json_encode($item)) . '\'>';
                                                            echo '<i class="fas fa-edit"></i>';
                                                            echo '</button>';
                                                            echo '<button class="btn btn-sm btn-danger btn-delete-menu" data-id="' . $item['id'] . '" data-title="' . htmlspecialchars($item['title']) . '">';
                                                            echo '<i class="fas fa-trash"></i>';
                                                            echo '</button>';
                                                            echo '</div>';
                                                            echo '</div>';
                                                            echo '</div>';
                                                            
                                                            if (!empty($item['children'])) {
                                                                echo '<ol class="dd-list">';
                                                                foreach ($item['children'] as $child) {
                                                                    renderMenuItem($child, $level + 1);
                                                                }
                                                                echo '</ol>';
                                                            }
                                                            echo '</li>';
                                                        }
                                                        
                                                        if (empty($menuTree)) {
                                                            echo '<div class="dd-empty">No hay elementos en este menú. Arrastra elementos aquí o agrega nuevos.</div>';
                                                        } else {
                                                            foreach ($menuTree as $item) {
                                                                renderMenuItem($item);
                                                            }
                                                        }
                                                        ?>
                                                    </ol>
                                                </div>
                                            </div>
                                        </div>
                                    <?php $first = false; endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Vista previa del menú -->
                        <div class="card" id="menuPreviewCard" style="display: none;">
                            <div class="card-header">
                                <h3 class="card-title">Vista Previa del Menú</h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-tool" onclick="hideMenuPreview()">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="menu-preview" id="menuPreviewContent">
                                    <!-- Contenido generado por JavaScript -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Formulario -->
                    <div class="col-md-4">
                        <div class="card sticky-top">
                            <div class="card-header">
                                <h3 class="card-title" id="formTitle">Nuevo Elemento de Menú</h3>
                            </div>
                            <form method="post" id="menuForm">
                                <div class="card-body">
                                    <input type="hidden" name="action" id="formAction" value="create">
                                    <input type="hidden" name="id" id="menuItemId">

                                    <div class="form-group">
                                        <label for="menu_location">Ubicación del Menú *</label>
                                        <select class="form-control" id="menu_location" name="menu_location" required>
                                            <?php foreach ($menuLocations as $key => $name): ?>
                                                <option value="<?php echo $key; ?>"><?php echo $name; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="title">Título *</label>
                                        <input type="text" class="form-control" id="title" name="title" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="url">URL *</label>
                                        <input type="text" class="form-control" id="url" name="url" required placeholder="/">
                                        <small class="text-muted">
                                            Ejemplos: /, /productos, /contacto, https://externa.com
                                        </small>
                                    </div>

                                    <div class="form-group">
                                        <label for="parent_id">Elemento Padre</label>
                                        <select class="form-control" id="parent_id" name="parent_id">
                                            <option value="">Elemento principal</option>
                                            <!-- Se llenará dinámicamente -->
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="icon">Icono (Font Awesome)</label>
                                        <input type="text" class="form-control" id="icon" name="icon" placeholder="fas fa-home">
                                        <small class="text-muted">
                                            Opcional. Ej: fas fa-home, fas fa-user, fas fa-cog
                                        </small>
                                    </div>

                                    <div class="form-group">
                                        <label for="target">Destino del Enlace</label>
                                        <select class="form-control" id="target" name="target">
                                            <option value="_self">Misma ventana</option>
                                            <option value="_blank">Nueva ventana</option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" checked>
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
                                    <button type="submit" class="btn btn-primary btn-block" id="submitBtn">
                                        <i class="fas fa-save"></i> Crear Elemento
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
                <p>¿Estás seguro de que deseas eliminar el elemento <strong id="menuItemNameToDelete"></strong>?</p>
                <p class="text-danger">Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="menuItemIdToDelete">
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
<!-- Nestable2 -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/nestable2/1.6.0/jquery.nestable.min.js"></script>
<!-- AdminLTE App -->
<script src="<?php echo ADMINLTE_URL; ?>/dist/js/adminlte.min.js"></script>

<script>
$(document).ready(function() {
    console.log('Iniciando editor de menús...');
    
    // Verificar si jQuery y Nestable están disponibles
    if (typeof $.fn.nestable === 'undefined') {
        console.error('Nestable no está cargado!');
        alert('Error: La librería Nestable no se cargó correctamente. Algunas funciones pueden no funcionar.');
    } else {
        console.log('Nestable cargado correctamente');
        
        // Inicializar Nestable para cada ubicación de menú
        $('.dd').nestable({
            maxDepth: 3,
            group: 1
        }).on('change', function() {
            console.log('Orden cambiado');
            updateMenuOrder($(this));
        });
    }
    
    // Actualizar opciones de padre cuando cambia la ubicación
    $('#menu_location').change(function() {
        updateParentOptions();
    });
    
    // Inicializar opciones de padre
    updateParentOptions();
    
    // Event listeners para botones dinámicos
    $(document).on('click', '.btn-edit-menu', function(e) {
        e.preventDefault();
        console.log('Botón editar clickeado');
        const item = $(this).data('item');
        console.log('Item a editar:', item);
        editMenuItem(item);
    });
    
    $(document).on('click', '.btn-delete-menu', function(e) {
        e.preventDefault();
        console.log('Botón eliminar clickeado');
        const id = $(this).data('id');
        const title = $(this).data('title');
        deleteMenuItem(id, title);
    });
    
    // Manejar cambio de tabs
    $('#menuTabs a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        console.log('Tab cambiado');
        const target = $(e.target).attr('href');
        const location = target.replace('#menu-', '');
        $('#menu_location').val(location);
        updateParentOptions();
    });
    
    // Test para verificar que los botones están presentes
    console.log('Botones de editar encontrados:', $('.btn-edit-menu').length);
    console.log('Botones de eliminar encontrados:', $('.btn-delete-menu').length);
});

function updateParentOptions() {
    const location = $('#menu_location').val();
    const currentId = $('#menuItemId').val();
    
    // Limpiar opciones
    $('#parent_id').html('<option value="">Elemento principal</option>');
    
    // Agregar elementos de la ubicación seleccionada
    <?php foreach ($menusByLocation as $loc => $items): ?>
        if (location === '<?php echo $loc; ?>') {
            <?php foreach ($items as $item): ?>
                if (<?php echo $item['id']; ?> != currentId && !<?php echo $item['parent_id']; ?>) {
                    $('#parent_id').append('<option value="<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['title']); ?></option>');
                }
            <?php endforeach; ?>
        }
    <?php endforeach; ?>
}

function editMenuItem(item) {
    console.log('Editando item:', item);
    $('#formTitle').text('Editar Elemento de Menú');
    $('#formAction').val('update');
    $('#menuItemId').val(item.id);
    $('#menu_location').val(item.menu_location);
    $('#title').val(item.title);
    $('#url').val(item.url);
    $('#parent_id').val(item.parent_id || '');
    $('#icon').val(item.icon || '');
    $('#target').val(item.target);
    $('#is_active').prop('checked', item.is_active == 1);
    $('#submitBtn').html('<i class="fas fa-save"></i> Actualizar Elemento');
    $('#cancelBtn').show();
    
    updateParentOptions();
    
    // Scroll al formulario
    $('html, body').animate({
        scrollTop: $("#menuForm").offset().top - 100
    }, 500);
}

function resetForm() {
    $('#formTitle').text('Nuevo Elemento de Menú');
    $('#formAction').val('create');
    $('#menuItemId').val('');
    $('#menuForm')[0].reset();
    $('#is_active').prop('checked', true);
    $('#submitBtn').html('<i class="fas fa-save"></i> Crear Elemento');
    $('#cancelBtn').hide();
    
    updateParentOptions();
}

function deleteMenuItem(id, name) {
    console.log('Eliminando item:', id, name);
    $('#menuItemNameToDelete').text(name);
    $('#menuItemIdToDelete').val(id);
    $('#deleteModal').modal('show');
}

function quickFill(title, url, icon) {
    $('#title').val(title);
    $('#url').val(url);
    $('#icon').val(icon);
}

function updateMenuOrder(container) {
    if (typeof container.nestable !== 'function') {
        console.error('Nestable no está disponible');
        return;
    }
    
    const location = container.data('location');
    const serializedData = container.nestable('serialize');
    const items = [];
    
    function processItems(data, parentId = null, level = 0) {
        data.forEach((item, index) => {
            items.push({
                id: item.id,
                parent_id: parentId,
                sort_order: index + 1
            });
            
            if (item.children && item.children.length > 0) {
                processItems(item.children, item.id, level + 1);
            }
        });
    }
    
    processItems(serializedData);
    
    // Enviar actualización por AJAX
    $.post(window.location.href, {
        action: 'update_order',
        items: items
    }).done(function() {
        console.log('Orden actualizado');
        // Mostrar mensaje de éxito temporal
        const alert = $('<div class="alert alert-success alert-dismissible fade show" role="alert">Orden actualizado correctamente <button type="button" class="close" data-dismiss="alert">&times;</button></div>');
        $('.content').prepend(alert);
        setTimeout(() => alert.alert('close'), 3000);
    }).fail(function() {
        console.error('Error al actualizar orden');
    });
}

function showMenuPreview(location) {
    const menuData = <?php echo json_encode($menusByLocation); ?>;
    const items = menuData[location] || [];
    
    // Construir HTML del menú
    let html = '<ul>';
    
    function buildMenuHTML(items, parentId = null) {
        let menuHtml = '';
        items.filter(item => item.parent_id == parentId && item.is_active == 1)
             .sort((a, b) => a.sort_order - b.sort_order)
             .forEach(item => {
                 menuHtml += '<li>';
                 menuHtml += '<a href="' + item.url + '" target="' + item.target + '">';
                 if (item.icon) {
                     menuHtml += '<i class="' + item.icon + ' icon"></i>';
                 }
                 menuHtml += item.title;
                 menuHtml += '</a>';
                 
                 const children = items.filter(child => child.parent_id == item.id && child.is_active == 1);
                 if (children.length > 0) {
                     menuHtml += '<ul>';
                     menuHtml += buildMenuHTML(items, item.id);
                     menuHtml += '</ul>';
                 }
                 menuHtml += '</li>';
             });
        return menuHtml;
    }
    
    html += buildMenuHTML(items);
    html += '</ul>';
    
    $('#menuPreviewContent').html(html);
    $('#menuPreviewCard').show();
    
    // Scroll a la vista previa
    $('html, body').animate({
        scrollTop: $("#menuPreviewCard").offset().top - 100
    }, 500);
}

function hideMenuPreview() {
    $('#menuPreviewCard').hide();
}

// Funciones de debugging
function debugMenus() {
    console.log('=== DEBUG MENÚS ===');
    console.log('jQuery:', typeof $);
    console.log('Nestable:', typeof $.fn.nestable);
    console.log('Botones edit:', $('.btn-edit-menu').length);
    console.log('Botones delete:', $('.btn-delete-menu').length);
    console.log('Contenedores dd:', $('.dd').length);
}

// Ejecutar debug después de cargar todo
$(window).on('load', function() {
    setTimeout(debugMenus, 1000);
});
</script>
</body>
</html>