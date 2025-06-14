<?php
// admin/pages/content/menus.php - Sistema drag & drop reestructurado
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
if (isset($_GET['moved'])) {
    $success = 'Elemento movido exitosamente';
} elseif (isset($_GET['updated'])) {
    $success = 'Menú actualizado exitosamente';
} elseif (isset($_GET['deleted'])) {
    $success = 'Elemento eliminado exitosamente';
}

// Procesar acciones AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $db = Database::getInstance()->getConnection();
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'move_element':
                $elementId = intval($_POST['element_id'] ?? 0);
                $targetLocation = sanitize($_POST['target_location'] ?? '');
                $newOrder = intval($_POST['new_order'] ?? 1);
                
                if ($elementId <= 0) {
                    throw new Exception('ID de elemento inválido');
                }
                
                // Verificar que la ubicación de destino sea válida
                $validLocations = ['main', 'footer', 'sidebar', 'available_pages', 'available_categories'];
                if (!in_array($targetLocation, $validLocations)) {
                    throw new Exception('Ubicación de destino inválida');
                }
                
                // Actualizar el elemento
                $stmt = $db->prepare("
                    UPDATE menu_items 
                    SET menu_location = ?, sort_order = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$targetLocation, $newOrder, $elementId]);
                
                // Reordenar otros elementos en la ubicación de destino
                $stmt = $db->prepare("
                    UPDATE menu_items 
                    SET sort_order = sort_order + 1 
                    WHERE menu_location = ? AND id != ? AND sort_order >= ?
                ");
                $stmt->execute([$targetLocation, $elementId, $newOrder]);
                
                echo json_encode(['success' => true, 'message' => 'Elemento movido exitosamente']);
                exit;
                
            case 'update_order':
                $locationItems = $_POST['items'] ?? [];
                
                foreach ($locationItems as $location => $items) {
                    foreach ($items as $order => $itemId) {
                        $stmt = $db->prepare("
                            UPDATE menu_items 
                            SET sort_order = ? 
                            WHERE id = ?
                        ");
                        $stmt->execute([$order + 1, intval($itemId)]);
                    }
                }
                
                echo json_encode(['success' => true, 'message' => 'Orden actualizado exitosamente']);
                exit;
                
            case 'toggle_status':
                $elementId = intval($_POST['element_id'] ?? 0);
                
                if ($elementId <= 0) {
                    throw new Exception('ID de elemento inválido');
                }
                
                $stmt = $db->prepare("UPDATE menu_items SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$elementId]);
                
                echo json_encode(['success' => true, 'message' => 'Estado actualizado']);
                exit;
                
            case 'delete_element':
                $elementId = intval($_POST['element_id'] ?? 0);
                
                if ($elementId <= 0) {
                    throw new Exception('ID de elemento inválido');
                }
                
                // Verificar si es un elemento automático (página o categoría)
                $stmt = $db->prepare("SELECT url, menu_location FROM menu_items WHERE id = ?");
                $stmt->execute([$elementId]);
                $element = $stmt->fetch();
                
                if ($element && in_array($element['menu_location'], ['available_pages', 'available_categories'])) {
                    echo json_encode(['success' => false, 'message' => 'No se pueden eliminar elementos automáticos. Desactívalos desde Páginas o Categorías.']);
                    exit;
                }
                
                $stmt = $db->prepare("DELETE FROM menu_items WHERE id = ?");
                $stmt->execute([$elementId]);
                
                echo json_encode(['success' => true, 'message' => 'Elemento eliminado']);
                exit;
                
            case 'create_custom_link':
                $title = sanitize($_POST['title'] ?? '');
                $url = sanitize($_POST['url'] ?? '');
                $location = sanitize($_POST['location'] ?? 'available_pages');
                
                if (empty($title) || empty($url)) {
                    throw new Exception('Título y URL son obligatorios');
                }
                
                // Obtener próximo orden
                $stmt = $db->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order FROM menu_items WHERE menu_location = ?");
                $stmt->execute([$location]);
                $nextOrder = $stmt->fetch()['next_order'];
                
                $stmt = $db->prepare("
                    INSERT INTO menu_items (title, url, menu_location, is_active, sort_order, created_at) 
                    VALUES (?, ?, ?, 1, ?, NOW())
                ");
                $stmt->execute([$title, $url, $location, $nextOrder]);
                
                echo json_encode(['success' => true, 'message' => 'Enlace personalizado creado']);
                exit;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Obtener todos los elementos de menú organizados por ubicación
try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener elementos disponibles - páginas (solo activas)
    $stmt = $db->query("
        SELECT m.* FROM menu_items m
        JOIN pages p ON p.slug = SUBSTRING(m.url, 2)  
        WHERE m.menu_location = 'available_pages' AND p.is_active = 1
        ORDER BY m.sort_order ASC, m.title ASC
    ");
    $availablePages = $stmt->fetchAll();
    
    // Obtener elementos disponibles - categorías (solo activas)
    $stmt = $db->query("
        SELECT m.* FROM menu_items m
        JOIN categories c ON c.slug = SUBSTRING(m.url, 12)  
        WHERE m.menu_location = 'available_categories' AND c.is_active = 1
        ORDER BY m.sort_order ASC, m.title ASC
    ");
    $availableCategories = $stmt->fetchAll();
    
    // Obtener elementos en menú principal
    $stmt = $db->query("
        SELECT * FROM menu_items 
        WHERE menu_location = 'main' 
        ORDER BY sort_order ASC, title ASC
    ");
    $mainMenuItems = $stmt->fetchAll();
    
    // Obtener elementos en footer
    $stmt = $db->query("
        SELECT * FROM menu_items 
        WHERE menu_location = 'footer' 
        ORDER BY sort_order ASC, title ASC
    ");
    $footerMenuItems = $stmt->fetchAll();
    
    // Obtener elementos en sidebar
    $stmt = $db->query("
        SELECT * FROM menu_items 
        WHERE menu_location = 'sidebar' 
        ORDER BY sort_order ASC, title ASC
    ");
    $sidebarMenuItems = $stmt->fetchAll();
    
} catch (Exception $e) {
    $availablePages = [];
    $availableCategories = [];
    $mainMenuItems = [];
    $footerMenuItems = [];
    $sidebarMenuItems = [];
    $error = 'Error al obtener elementos de menú: ' . $e->getMessage();
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
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/dist/css/adminlte.min.css">
    
    <!-- jQuery UI para drag & drop -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/ui-lightness/jquery-ui.css">
    
    <style>
        .menu-container {
            min-height: 400px;
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 20px;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .menu-container.drag-over {
            background: #e3f2fd;
            border-color: #2196f3;
            transform: scale(1.02);
        }
        
        .menu-container.has-items {
            border-style: solid;
            background: white;
        }
        
        .menu-item {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 10px;
            cursor: move;
            transition: all 0.3s ease;
            position: relative;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .menu-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        
        .menu-item.ui-sortable-helper {
            transform: rotate(5deg);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            z-index: 1000;
        }
        
        .menu-item.ui-sortable-placeholder {
            background: #e3f2fd;
            border: 2px dashed #2196f3;
            height: 50px;
            visibility: visible !important;
        }
        
        .menu-item-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .menu-item-info {
            flex-grow: 1;
        }
        
        .menu-item-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 2px;
        }
        
        .menu-item-url {
            font-size: 0.85em;
            color: #666;
            font-family: monospace;
        }
        
        .menu-item-actions {
            display: flex;
            gap: 5px;
        }
        
        .menu-item-type {
            position: absolute;
            top: -8px;
            left: 15px;
            background: #007bff;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: 500;
        }
        
        .menu-item-type.page {
            background: #28a745;
        }
        
        .menu-item-type.category {
            background: #ffc107;
            color: #333;
        }
        
        .menu-item-type.custom {
            background: #6f42c1;
        }
        
        .menu-section {
            margin-bottom: 30px;
        }
        
        .section-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0;
            margin-bottom: 0;
        }
        
        .section-header h5 {
            margin: 0;
            font-weight: 600;
        }
        
        .section-count {
            background: rgba(255,255,255,0.2);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            margin-left: 10px;
        }
        
        .empty-menu {
            text-align: center;
            color: #999;
            padding: 40px 20px;
        }
        
        .empty-menu i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .available-section {
            background: #e8f5e8;
        }
        
        .available-section .section-header {
            background: linear-gradient(135deg, #4caf50 0%, #8bc34a 100%);
        }
        
        .destination-section .section-header {
            background: linear-gradient(135deg, #2196f3 0%, #21cbf3 100%);
        }
        
        .quick-actions {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .drag-instructions {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .menu-preview {
            position: sticky;
            top: 20px;
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .inactive-item {
            opacity: 0.6;
        }
        
        .inactive-item .menu-item {
            background: #f8f9fa;
            border-color: #e9ecef;
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

                <!-- Instrucciones -->
                <div class="drag-instructions">
                    <h6><i class="fas fa-info-circle"></i> Cómo usar el Editor de Menús</h6>
                    <p class="mb-2">
                        <strong>1.</strong> Arrastra elementos desde "Elementos Disponibles" hacia las secciones de destino<br>
                        <strong>2.</strong> Reordena elementos dentro de cada sección arrastrando<br>
                        <strong>3.</strong> Usa los controles para activar/desactivar o eliminar elementos<br>
                        <strong>4.</strong> Crea enlaces personalizados con el botón "Agregar Enlace"
                    </p>
                </div>

                <!-- Acciones Rápidas -->
                <div class="quick-actions">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-plus"></i> Agregar Enlace Personalizado</h6>
                            <form id="customLinkForm" class="form-inline">
                                <input type="text" class="form-control mr-2" placeholder="Título" id="customTitle" required>
                                <input type="text" class="form-control mr-2" placeholder="URL" id="customUrl" required>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Agregar
                                </button>
                            </form>
                        </div>
                        <div class="col-md-6 text-md-right">
                            <button class="btn btn-info" onclick="previewMenus()">
                                <i class="fas fa-eye"></i> Vista Previa
                            </button>
                            <button class="btn btn-success" onclick="saveAllChanges()">
                                <i class="fas fa-save"></i> Guardar Cambios
                            </button>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- ELEMENTOS DISPONIBLES -->
                    <div class="col-lg-4">
                        <!-- Páginas Disponibles -->
                        <div class="menu-section available-section">
                            <div class="section-header">
                                <h5>
                                    <i class="fas fa-file-alt"></i> Páginas Disponibles
                                    <span class="section-count"><?php echo count($availablePages); ?></span>
                                </h5>
                            </div>
                            <div class="menu-container" id="available-pages" data-location="available_pages">
                                <?php if (empty($availablePages)): ?>
                                    <div class="empty-menu">
                                        <i class="fas fa-file-alt"></i>
                                        <p>No hay páginas disponibles</p>
                                        <small>Crea páginas y márcalas como "Disponible en menús"</small>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($availablePages as $item): ?>
                                        <div class="menu-item <?php echo !$item['is_active'] ? 'inactive-item' : ''; ?>" data-id="<?php echo $item['id']; ?>">
                                            <div class="menu-item-type page">Página</div>
                                            <div class="menu-item-content">
                                                <div class="menu-item-info">
                                                    <div class="menu-item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                                    <div class="menu-item-url"><?php echo htmlspecialchars($item['url']); ?></div>
                                                </div>
                                                <div class="menu-item-actions">
                                                    <button class="btn btn-sm <?php echo $item['is_active'] ? 'btn-warning' : 'btn-success'; ?>" 
                                                            onclick="toggleItemStatus(<?php echo $item['id']; ?>)"
                                                            title="<?php echo $item['is_active'] ? 'Desactivar' : 'Activar'; ?>">
                                                        <i class="fas fa-<?php echo $item['is_active'] ? 'eye-slash' : 'eye'; ?>"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Categorías Disponibles -->
                        <div class="menu-section available-section">
                            <div class="section-header">
                                <h5>
                                    <i class="fas fa-folder"></i> Categorías Disponibles
                                    <span class="section-count"><?php echo count($availableCategories); ?></span>
                                </h5>
                            </div>
                            <div class="menu-container" id="available-categories" data-location="available_categories">
                                <?php if (empty($availableCategories)): ?>
                                    <div class="empty-menu">
                                        <i class="fas fa-folder"></i>
                                        <p>No hay categorías disponibles</p>
                                        <small>Crea categorías y márcalas como "Disponible en menús"</small>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($availableCategories as $item): ?>
                                        <div class="menu-item <?php echo !$item['is_active'] ? 'inactive-item' : ''; ?>" data-id="<?php echo $item['id']; ?>">
                                            <div class="menu-item-type category">Categoría</div>
                                            <div class="menu-item-content">
                                                <div class="menu-item-info">
                                                    <div class="menu-item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                                    <div class="menu-item-url"><?php echo htmlspecialchars($item['url']); ?></div>
                                                </div>
                                                <div class="menu-item-actions">
                                                    <button class="btn btn-sm <?php echo $item['is_active'] ? 'btn-warning' : 'btn-success'; ?>" 
                                                            onclick="toggleItemStatus(<?php echo $item['id']; ?>)"
                                                            title="<?php echo $item['is_active'] ? 'Desactivar' : 'Activar'; ?>">
                                                        <i class="fas fa-<?php echo $item['is_active'] ? 'eye-slash' : 'eye'; ?>"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIONES DE DESTINO -->
                    <div class="col-lg-8">
                        <div class="row">
                            <!-- Menú Principal -->
                            <div class="col-md-6">
                                <div class="menu-section destination-section">
                                    <div class="section-header">
                                        <h5>
                                            <i class="fas fa-bars"></i> Menú Principal (Header)
                                            <span class="section-count"><?php echo count($mainMenuItems); ?></span>
                                        </h5>
                                    </div>
                                    <div class="menu-container" id="main-menu" data-location="main">
                                        <?php if (empty($mainMenuItems)): ?>
                                            <div class="empty-menu">
                                                <i class="fas fa-bars"></i>
                                                <p>Arrastra elementos aquí</p>
                                                <small>Aparecerán en el header del sitio</small>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($mainMenuItems as $item): ?>
                                                <div class="menu-item <?php echo !$item['is_active'] ? 'inactive-item' : ''; ?>" data-id="<?php echo $item['id']; ?>">
                                                    <div class="menu-item-type custom">Principal</div>
                                                    <div class="menu-item-content">
                                                        <div class="menu-item-info">
                                                            <div class="menu-item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                                            <div class="menu-item-url"><?php echo htmlspecialchars($item['url']); ?></div>
                                                        </div>
                                                        <div class="menu-item-actions">
                                                            <button class="btn btn-sm <?php echo $item['is_active'] ? 'btn-warning' : 'btn-success'; ?>" 
                                                                    onclick="toggleItemStatus(<?php echo $item['id']; ?>)"
                                                                    title="<?php echo $item['is_active'] ? 'Desactivar' : 'Activar'; ?>">
                                                                <i class="fas fa-<?php echo $item['is_active'] ? 'eye-slash' : 'eye'; ?>"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-danger" onclick="deleteItem(<?php echo $item['id']; ?>)" title="Eliminar">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="col-md-6">
                                <div class="menu-section destination-section">
                                    <div class="section-header">
                                        <h5>
                                            <i class="fas fa-shoe-prints"></i> Menú Footer
                                            <span class="section-count"><?php echo count($footerMenuItems); ?></span>
                                        </h5>
                                    </div>
                                    <div class="menu-container" id="footer-menu" data-location="footer">
                                        <?php if (empty($footerMenuItems)): ?>
                                            <div class="empty-menu">
                                                <i class="fas fa-shoe-prints"></i>
                                                <p>Arrastra elementos aquí</p>
                                                <small>Aparecerán en el footer del sitio</small>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($footerMenuItems as $item): ?>
                                                <div class="menu-item <?php echo !$item['is_active'] ? 'inactive-item' : ''; ?>" data-id="<?php echo $item['id']; ?>">
                                                    <div class="menu-item-type custom">Footer</div>
                                                    <div class="menu-item-content">
                                                        <div class="menu-item-info">
                                                            <div class="menu-item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                                            <div class="menu-item-url"><?php echo htmlspecialchars($item['url']); ?></div>
                                                        </div>
                                                        <div class="menu-item-actions">
                                                            <button class="btn btn-sm <?php echo $item['is_active'] ? 'btn-warning' : 'btn-success'; ?>" 
                                                                    onclick="toggleItemStatus(<?php echo $item['id']; ?>)"
                                                                    title="<?php echo $item['is_active'] ? 'Desactivar' : 'Activar'; ?>">
                                                                <i class="fas fa-<?php echo $item['is_active'] ? 'eye-slash' : 'eye'; ?>"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-danger" onclick="deleteItem(<?php echo $item['id']; ?>)" title="Eliminar">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Sidebar -->
                            <div class="col-md-12">
                                <div class="menu-section destination-section">
                                    <div class="section-header">
                                        <h5>
                                            <i class="fas fa-columns"></i> Menú Sidebar
                                            <span class="section-count"><?php echo count($sidebarMenuItems); ?></span>
                                        </h5>
                                    </div>
                                    <div class="menu-container" id="sidebar-menu" data-location="sidebar">
                                        <?php if (empty($sidebarMenuItems)): ?>
                                            <div class="empty-menu">
                                                <i class="fas fa-columns"></i>
                                                <p>Arrastra elementos aquí</p>
                                                <small>Para menús laterales o widgets</small>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($sidebarMenuItems as $item): ?>
                                                <div class="menu-item <?php echo !$item['is_active'] ? 'inactive-item' : ''; ?>" data-id="<?php echo $item['id']; ?>">
                                                    <div class="menu-item-type custom">Sidebar</div>
                                                    <div class="menu-item-content">
                                                        <div class="menu-item-info">
                                                            <div class="menu-item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                                            <div class="menu-item-url"><?php echo htmlspecialchars($item['url']); ?></div>
                                                        </div>
                                                        <div class="menu-item-actions">
                                                            <button class="btn btn-sm <?php echo $item['is_active'] ? 'btn-warning' : 'btn-success'; ?>" 
                                                                    onclick="toggleItemStatus(<?php echo $item['id']; ?>)"
                                                                    title="<?php echo $item['is_active'] ? 'Desactivar' : 'Activar'; ?>">
                                                                <i class="fas fa-<?php echo $item['is_active'] ? 'eye-slash' : 'eye'; ?>"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-danger" onclick="deleteItem(<?php echo $item['id']; ?>)" title="Eliminar">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </div>

    <!-- Footer -->
    <?php include '../../includes/footer.php'; ?>
</div>

<!-- Modal de Vista Previa -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Vista Previa de Menús</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="previewContent">
                <!-- Contenido generado por JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                <a href="<?php echo SITE_URL; ?>" target="_blank" class="btn btn-primary">Ver Sitio Real</a>
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
    initializeDragAndDrop();
    updateContainerStates();
});

function initializeDragAndDrop() {
    // Hacer todos los contenedores sortables y connectables
    $('.menu-container').sortable({
        connectWith: '.menu-container',
        placeholder: 'menu-item ui-sortable-placeholder',
        tolerance: 'pointer',
        cursor: 'move',
        opacity: 0.8,
        
        start: function(event, ui) {
            ui.placeholder.height(ui.item.height());
            $('.menu-container').addClass('drag-active');
        },
        
        stop: function(event, ui) {
            $('.menu-container').removeClass('drag-active drag-over');
            updateContainerStates();
            // Auto-guardar cambios
            setTimeout(saveAllChanges, 500);
        },
        
        over: function(event, ui) {
            $(this).addClass('drag-over');
        },
        
        out: function(event, ui) {
            $(this).removeClass('drag-over');
        }
    });
}

function updateContainerStates() {
    $('.menu-container').each(function() {
        const $container = $(this);
        const itemCount = $container.find('.menu-item').length;
        const emptyMenu = $container.find('.empty-menu');
        
        if (itemCount > 0) {
            $container.addClass('has-items');
            emptyMenu.hide();
        } else {
            $container.removeClass('has-items');
            emptyMenu.show();
        }
        
        // Actualizar contador en el header
        const sectionHeader = $container.closest('.menu-section').find('.section-count');
        sectionHeader.text(itemCount);
    });
}

function saveAllChanges() {
    const locationData = {};
    
    $('.menu-container').each(function() {
        const location = $(this).data('location');
        const items = [];
        
        $(this).find('.menu-item').each(function(index) {
            items.push($(this).data('id'));
        });
        
        locationData[location] = items;
    });
    
    $.post(window.location.href, {
        action: 'update_order',
        items: locationData
    })
    .done(function(response) {
        if (response.success) {
            showNotification('Cambios guardados exitosamente', 'success');
        } else {
            showNotification('Error: ' + response.message, 'error');
        }
    })
    .fail(function() {
        showNotification('Error de conexión', 'error');
    });
}

function toggleItemStatus(itemId) {
    $.post(window.location.href, {
        action: 'toggle_status',
        element_id: itemId
    })
    .done(function(response) {
        if (response.success) {
            location.reload();
        } else {
            showNotification('Error: ' + response.message, 'error');
        }
    });
}

function deleteItem(itemId) {
    if (confirm('¿Estás seguro de que deseas eliminar este elemento?')) {
        $.post(window.location.href, {
            action: 'delete_element',
            element_id: itemId
        })
        .done(function(response) {
            if (response.success) {
                $(`[data-id="${itemId}"]`).fadeOut(300, function() {
                    $(this).remove();
                    updateContainerStates();
                });
                showNotification(response.message, 'success');
            } else {
                showNotification('Error: ' + response.message, 'error');
            }
        });
    }
}

$('#customLinkForm').on('submit', function(e) {
    e.preventDefault();
    
    const title = $('#customTitle').val().trim();
    const url = $('#customUrl').val().trim();
    
    if (!title || !url) {
        showNotification('Título y URL son obligatorios', 'error');
        return;
    }
    
    $.post(window.location.href, {
        action: 'create_custom_link',
        title: title,
        url: url,
        location: 'available_pages'
    })
    .done(function(response) {
        if (response.success) {
            location.reload();
        } else {
            showNotification('Error: ' + response.message, 'error');
        }
    });
});

function previewMenus() {
    let previewHTML = '<div class="row">';
    
    // Preview Menú Principal
    const mainItems = $('#main-menu .menu-item:not(.ui-sortable-placeholder)');
    previewHTML += '<div class="col-md-6"><h6>Menú Principal (Header)</h6><ul class="list-group">';
    if (mainItems.length > 0) {
        mainItems.each(function() {
            const title = $(this).find('.menu-item-title').text();
            const url = $(this).find('.menu-item-url').text();
            const isActive = !$(this).hasClass('inactive-item');
            previewHTML += `<li class="list-group-item ${!isActive ? 'text-muted' : ''}">${title} <small>(${url})</small></li>`;
        });
    } else {
        previewHTML += '<li class="list-group-item text-muted">Sin elementos</li>';
    }
    previewHTML += '</ul></div>';
    
    // Preview Footer
    const footerItems = $('#footer-menu .menu-item:not(.ui-sortable-placeholder)');
    previewHTML += '<div class="col-md-6"><h6>Menú Footer</h6><ul class="list-group">';
    if (footerItems.length > 0) {
        footerItems.each(function() {
            const title = $(this).find('.menu-item-title').text();
            const url = $(this).find('.menu-item-url').text();
            const isActive = !$(this).hasClass('inactive-item');
            previewHTML += `<li class="list-group-item ${!isActive ? 'text-muted' : ''}">${title} <small>(${url})</small></li>`;
        });
    } else {
        previewHTML += '<li class="list-group-item text-muted">Sin elementos</li>';
    }
    previewHTML += '</ul></div>';
    
    previewHTML += '</div>';
    
    $('#previewContent').html(previewHTML);
    $('#previewModal').modal('show');
}

function showNotification(message, type) {
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const icon = type === 'success' ? 'fa-check' : 'fa-exclamation-triangle';
    
    const notification = $(`
        <div class="alert ${alertClass} alert-dismissible fade show" style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
            <i class="fas ${icon}"></i> ${message}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    `);
    
    $('body').append(notification);
    
    setTimeout(function() {
        notification.alert('close');
    }, 5000);
}
</script>
</body>
</html>