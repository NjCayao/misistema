<?php
// admin/pages/content/menus.php - ARCHIVO PRINCIPAL LIMPIO
require_once '../../../config/database.php';
require_once '../../../config/constants.php';
require_once '../../../config/functions.php';
require_once '../../../config/settings.php';

// Incluir archivos específicos de menús
require_once 'archivosMenu/menu_functions.php';
require_once 'archivosMenu/menu_data.php';
require_once 'archivosMenu/menu_actions.php';

// Validar acceso de administrador
validateAdminAccess();

// Procesar acciones AJAX/POST ANTES de cualquier HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actionResult = handleMenuActions();
    if ($actionResult !== null) {
        echo json_encode($actionResult);
        exit;
    }
}

// Obtener datos de menús
$menuData = getMenuData();
extract($menuData); // Extrae todas las variables: $availablePages, $availableCategories, etc.

// Procesar mensajes de éxito
$success = processSuccessMessages();
$error = $menuData['error'] ?? '';
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
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/ui-lightness/jquery-ui.css">
    <link rel="stylesheet" href="archivosMenu/menu_styles.css">
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
                    
                    <?php renderAlerts($success, $error); ?>

                    <?php renderInstructions(); ?>

                    <?php renderQuickActions(); ?>

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
                                        <?php renderEmptyMenu('fas fa-file-alt', 'No hay páginas disponibles', 'Crea páginas y márcalas como "Disponible en menús"'); ?>
                                    <?php else: ?>
                                        <?php foreach ($availablePages as $item): ?>
                                            <?php renderAvailableItem($item, 'page'); ?>
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
                                        <?php renderEmptyMenu('fas fa-folder', 'No hay categorías disponibles', 'Crea categorías para que aparezcan aquí automáticamente'); ?>
                                    <?php else: ?>
                                        <?php foreach ($availableCategories as $item): ?>
                                            <?php renderAvailableItem($item, 'category'); ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Enlaces Personalizados Disponibles -->
                            <div class="menu-section available-section">
                                <div class="section-header">
                                    <h5>
                                        <i class="fas fa-link"></i> Enlaces Personalizados Disponibles
                                        <span class="section-count"><?php echo count($availableCustomLinks); ?></span>
                                    </h5>
                                </div>
                                <div class="menu-container" id="available-custom" data-location="available_custom">
                                    <?php if (empty($availableCustomLinks)): ?>
                                        <?php renderEmptyMenu('fas fa-link', 'No hay enlaces personalizados', 'Usa el formulario "Agregar Enlace Personalizado" para crear enlaces'); ?>
                                    <?php else: ?>
                                        <?php foreach ($availableCustomLinks as $item): ?>
                                            <?php renderAvailableCustomLink($item); ?>
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
                                        <div class="menu-container hierarchical" id="main-menu" data-location="main">
                                            <?php if (empty($mainMenuItems)): ?>
                                                <?php renderEmptyMenu('fas fa-bars', 'Arrastra elementos aquí', 'Páginas serán elementos padre, categorías serán subpáginas'); ?>
                                            <?php else: ?>
                                                <?php
                                                foreach ($mainMenuItems as $item):
                                                    if ($item['parent_id'] === null):
                                                        renderHierarchicalItem($item);
                                                        
                                                        foreach ($mainMenuItems as $child):
                                                            if ($child['parent_id'] == $item['id']):
                                                                renderHierarchicalItem($child);
                                                            endif;
                                                        endforeach;
                                                    endif;
                                                endforeach;
                                                ?>
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
                                        <div class="menu-container hierarchical" id="footer-menu" data-location="footer">
                                            <?php if (empty($footerMenuItems)): ?>
                                                <?php renderEmptyMenu('fas fa-shoe-prints', 'Arrastra elementos aquí', 'Aparecerán en el footer del sitio'); ?>
                                            <?php else: ?>
                                                <?php
                                                foreach ($footerMenuItems as $item):
                                                    if ($item['parent_id'] === null):
                                                        renderDestinationItem($item, 'footer');
                                                        
                                                        foreach ($footerMenuItems as $child):
                                                            if ($child['parent_id'] == $item['id']):
                                                                renderDestinationItem($child, 'footer');
                                                            endif;
                                                        endforeach;
                                                    endif;
                                                endforeach;
                                                ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Sidebar -->
                                <div class="col-md-6">
                                    <div class="menu-section destination-section">
                                        <div class="section-header">
                                            <h5>
                                                <i class="fas fa-columns"></i> Menú Sidebar
                                                <span class="section-count"><?php echo count($sidebarMenuItems); ?></span>
                                            </h5>
                                        </div>
                                        <div class="menu-container hierarchical" id="sidebar-menu" data-location="sidebar">
                                            <?php if (empty($sidebarMenuItems)): ?>
                                                <?php renderEmptyMenu('fas fa-columns', 'Arrastra elementos aquí', 'Para menús laterales o widgets'); ?>
                                            <?php else: ?>
                                                <?php
                                                foreach ($sidebarMenuItems as $item):
                                                    if ($item['parent_id'] === null):
                                                        renderDestinationItem($item, 'sidebar');
                                                        
                                                        foreach ($sidebarMenuItems as $child):
                                                            if ($child['parent_id'] == $item['id']):
                                                                renderDestinationItem($child, 'sidebar');
                                                            endif;
                                                        endforeach;
                                                    endif;
                                                endforeach;
                                                ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Menú Usuario -->
                                <!-- <div class="col-md-6">
                                    <div class="menu-section user-section">
                                        <div class="section-header">
                                            <h5>
                                                <i class="fas fa-user"></i> Menú Usuario
                                                <span class="section-count"><?php echo count($userMenuItems); ?></span>
                                            </h5>
                                        </div>
                                        <div class="menu-container hierarchical" id="user-menu" data-location="user">
                                            <?php if (empty($userMenuItems)): ?>
                                                <?php renderEmptyMenu('fas fa-user', 'Arrastra elementos aquí', 'Para menú de perfil de usuario'); ?>
                                            <?php else: ?>
                                                <?php
                                                foreach ($userMenuItems as $item):
                                                    if ($item['parent_id'] === null):
                                                        renderDestinationItem($item, 'user');
                                                        
                                                        foreach ($userMenuItems as $child):
                                                            if ($child['parent_id'] == $item['id']):
                                                                renderDestinationItem($child, 'user');
                                                            endif;
                                                        endforeach;
                                                    endif;
                                                endforeach;
                                                ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div> -->

                                <!-- Menú Móvil -->
                                <!-- <div class="col-md-12">
                                    <div class="menu-section mobile-section">
                                        <div class="section-header">
                                            <h5>
                                                <i class="fas fa-mobile-alt"></i> Menú Móvil
                                                <span class="section-count"><?php echo count($mobileMenuItems); ?></span>
                                            </h5>
                                        </div>
                                        <div class="menu-container hierarchical" id="mobile-menu" data-location="mobile">
                                            <?php if (empty($mobileMenuItems)): ?>
                                                <?php renderEmptyMenu('fas fa-mobile-alt', 'Arrastra elementos aquí', 'Para navegación en dispositivos móviles'); ?>
                                            <?php else: ?>
                                                <?php
                                                foreach ($mobileMenuItems as $item):
                                                    if ($item['parent_id'] === null):
                                                        renderDestinationItem($item, 'mobile');
                                                        
                                                        foreach ($mobileMenuItems as $child):
                                                            if ($child['parent_id'] == $item['id']):
                                                                renderDestinationItem($child, 'mobile');
                                                            endif;
                                                        endforeach;
                                                    endif;
                                                endforeach;
                                                ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div> -->
                            </div>
                        </div>
                    </div>

                </div>
            </section>
        </div>

        <!-- Footer -->
        <?php include '../../includes/footer.php'; ?>
    </div>

    <?php renderPreviewModal(); ?>

    <!-- Scripts -->
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/jquery/jquery.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/dist/js/adminlte.min.js"></script>
    <script src="archivosMenu/menu_scripts.js"></script>

</body>
</html>