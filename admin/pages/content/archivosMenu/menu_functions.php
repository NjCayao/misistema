<?php
// admin/pages/content/archivosMenu/menu_functions.php
// Funciones auxiliares para el manejo de menús

/**
 * Renderiza un enlace personalizado disponible
 * CON soporte para reordenamiento
 */
function renderAvailableCustomLink($item) {
    ?>
    <div class="menu-item" 
         data-id="<?php echo $item['id']; ?>"
         data-level="0">
        <div class="menu-item-type custom">Enlace</div>
        <div class="menu-item-content">
            <div class="menu-item-info">
                <div class="menu-item-title"><strong><?php echo htmlspecialchars($item['title']); ?></strong></div>
                <div class="menu-item-url"><?php echo htmlspecialchars($item['url']); ?></div>
            </div>
            <div class="menu-item-actions">
                <?php if ($item['id'] > 6): ?>
                    <button class="btn btn-sm btn-danger" onclick="permanentDeleteCustomLink(<?php echo $item['id']; ?>)" title="Eliminar permanentemente">
                        <i class="fas fa-trash"></i>
                    </button>
                <?php else: ?>
                    <button class="btn btn-sm btn-secondary" disabled title="Elemento protegido">
                        <i class="fas fa-lock"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Renderiza un elemento de menú disponible (página o categoría) 
 * CON soporte para reordenamiento
 */
function renderAvailableItem($item, $type = 'page') {
    $typeClass = $type === 'page' ? 'page' : 'category';
    $statusField = $type === 'page' ? 'page_is_active' : 'category_is_active';
    $toggleFunction = $type === 'page' ? 'togglePageStatus' : 'toggleCategoryStatus';
    $slug = $type === 'page' ? ltrim($item['url'], '/') : str_replace('/categoria/', '', $item['url']);
    ?>
    <div class="menu-item <?php echo !$item[$statusField] ? 'inactive-item' : ''; ?>" 
         data-id="<?php echo $item['id']; ?>"
         data-level="0">
        <div class="menu-item-type <?php echo $typeClass; ?>"><?php echo ucfirst($type); ?></div>
        <div class="menu-item-content">
            <div class="menu-item-info">
                <div class="menu-item-title"><strong><?php echo htmlspecialchars($item['title']); ?></strong></div>
                <div class="menu-item-url"><?php echo htmlspecialchars($item['url']); ?></div>
            </div>
            <div class="menu-item-actions">
                <button class="btn btn-sm <?php echo $item[$statusField] ? 'btn-warning' : 'btn-success'; ?>"
                    onclick="<?php echo $toggleFunction; ?>('<?php echo $slug; ?>')"
                    title="<?php echo $item[$statusField] ? 'Desactivar ' . $type : 'Activar ' . $type; ?>">
                    <i class="fas fa-<?php echo $item[$statusField] ? 'eye-slash' : 'eye'; ?>"></i>
                </button>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Renderiza un elemento de menú de destino (main, footer, sidebar, user, mobile)
 * TODOS los menús soportan jerarquía
 */
function renderDestinationItem($item, $location = '') {
    $typeLabels = [
        'main' => 'Principal',
        'footer' => 'Footer',
        'sidebar' => 'Sidebar',
        'user' => 'Usuario',
        'mobile' => 'Móvil'
    ];
    
    $typeLabel = $typeLabels[$location] ?? 'Custom';
    $level = $item['parent_id'] === null ? 0 : 1;
    $levelClass = "level-$level";
    ?>
    <div class="menu-item <?php echo $levelClass; ?> <?php echo !$item['is_active'] ? 'inactive-item' : ''; ?>" 
         data-id="<?php echo $item['id']; ?>"
         data-level="<?php echo $level; ?>"
         <?php if ($item['parent_id']): ?>data-parent-id="<?php echo $item['parent_id']; ?>"<?php endif; ?>>
        <div class="menu-item-type custom"><?php echo $typeLabel; ?></div>
        <div class="menu-item-content">
            <div class="menu-item-info">
                <div class="menu-item-title">
                    <?php if ($level === 0): ?>
                        <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                    <?php else: ?>
                        ↳ <?php echo htmlspecialchars($item['title']); ?>
                    <?php endif; ?>
                </div>
                <div class="menu-item-url"><?php echo htmlspecialchars($item['url']); ?></div>
            </div>
            <div class="menu-item-actions">
                <button class="btn btn-sm <?php echo $item['is_active'] ? 'btn-warning' : 'btn-success'; ?>"
                    onclick="toggleItemStatus(<?php echo $item['id']; ?>)"
                    title="<?php echo $item['is_active'] ? 'Desactivar' : 'Activar'; ?>">
                    <i class="fas fa-<?php echo $item['is_active'] ? 'eye-slash' : 'eye'; ?>"></i>
                </button>
                <?php if ($item['id'] > 6): ?>
                    <button class="btn btn-sm btn-danger" onclick="deleteItem(<?php echo $item['id']; ?>)" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                <?php else: ?>
                    <button class="btn btn-sm btn-secondary" disabled title="Elemento protegido">
                        <i class="fas fa-lock"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Renderiza elementos jerárquicos del menú principal
 */
function renderHierarchicalItem($item) {
    $level = $item['parent_id'] === null ? 0 : 1;
    $levelClass = "level-$level";
    ?>
    <div class="menu-item <?php echo $levelClass; ?> <?php echo !$item['is_active'] ? 'inactive-item' : ''; ?>"
        data-id="<?php echo $item['id']; ?>"
        data-level="<?php echo $level; ?>"
        <?php if ($item['parent_id']): ?>data-parent-id="<?php echo $item['parent_id']; ?>"<?php endif; ?>>
        
        <div class="menu-item-content">
            <div class="menu-item-info">
                <div class="menu-item-title">
                    <?php if ($level === 0): ?>
                        <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                    <?php else: ?>
                        ↳ <?php echo htmlspecialchars($item['title']); ?>
                    <?php endif; ?>
                </div>
                <div class="menu-item-url"><?php echo htmlspecialchars($item['url']); ?></div>
            </div>
            <div class="menu-item-actions">
                <button class="btn btn-sm <?php echo $item['is_active'] ? 'btn-warning' : 'btn-success'; ?>"
                    onclick="toggleItemStatus(<?php echo $item['id']; ?>)"
                    title="<?php echo $item['is_active'] ? 'Desactivar' : 'Activar'; ?>">
                    <i class="fas fa-<?php echo $item['is_active'] ? 'eye-slash' : 'eye'; ?>"></i>
                </button>
                <?php if ($item['id'] > 6): ?>
                    <button class="btn btn-sm btn-danger" onclick="deleteItem(<?php echo $item['id']; ?>)" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                <?php else: ?>
                    <button class="btn btn-sm btn-secondary" disabled title="Elemento protegido">
                        <i class="fas fa-lock"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Genera el HTML de una sección de menú vacía
 */
function renderEmptyMenu($iconClass, $title, $description) {
    ?>
    <div class="empty-menu">
        <i class="<?php echo $iconClass; ?>"></i>
        <p><?php echo $title; ?></p>
        <small><?php echo $description; ?></small>
    </div>
    <?php
}

/**
 * Genera las instrucciones de uso
 */
function renderInstructions() {
    ?>
    <div class="drag-instructions">
        <h6><i class="fas fa-info-circle"></i> Cómo usar el Editor de Menús</h6>
        <p class="mb-2">
            <strong>1.</strong> Arrastra elementos desde "Elementos Disponibles" hacia las secciones de destino<br>
            <strong>2.</strong> Reordena elementos dentro de cada sección arrastrando<br>
            <strong>3.</strong> Usa los controles para activar/desactivar elementos<br>
            <strong>4.</strong> El ojito en categorías controla si se muestra en el sitio público<br>
            <strong>5.</strong> Menú Principal soporta jerarquía: páginas como padres, categorías como hijos
        </p>
    </div>
    <?php
}

/**
 * Genera el formulario de acciones rápidas
 */
function renderQuickActions() {
    ?>
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
    <?php
}

/**
 * Procesa mensajes de éxito desde parámetros GET
 */
function processSuccessMessages() {
    $success = '';
    
    if (isset($_GET['moved'])) {
        $success = 'Elemento movido exitosamente';
    } elseif (isset($_GET['updated'])) {
        $success = 'Menú actualizado exitosamente';
    } elseif (isset($_GET['deleted'])) {
        $success = 'Elemento eliminado exitosamente';
    } elseif (isset($_GET['created'])) {
        $success = 'Elemento creado exitosamente';
    }
    
    return $success;
}

/**
 * Renderiza alertas de éxito y error
 */
function renderAlerts($success, $error) {
    if ($success): ?>
        <div class="alert alert-success alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="icon fas fa-check"></i> <?php echo $success; ?>
        </div>
    <?php endif;
    
    if ($error): ?>
        <div class="alert alert-danger alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="icon fas fa-ban"></i> <?php echo $error; ?>
        </div>
    <?php endif;
}

/**
 * Genera el modal de vista previa
 */
function renderPreviewModal() {
    ?>
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
    <?php
}

/**
 * Valida permisos de administrador
 */
function validateAdminAccess() {
    if (!isAdmin()) {
        redirect(ADMIN_URL . '/login.php');
    }
}

/**
 * Obtiene la configuración de iconos para cada tipo de menú
 */
function getMenuIcons() {
    return [
        'pages' => 'fas fa-file-alt',
        'categories' => 'fas fa-folder',
        'main' => 'fas fa-bars',
        'footer' => 'fas fa-shoe-prints',
        'sidebar' => 'fas fa-columns',
        'user' => 'fas fa-user',
        'mobile' => 'fas fa-mobile-alt'
    ];
}
?>