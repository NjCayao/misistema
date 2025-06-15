// admin/pages/content/archivosMenu/menu_scripts.js
// JavaScript para el Editor de Menús

$(document).ready(function() {
    initializeDragAndDrop();
    updateContainerStates();
});

function initializeDragAndDrop() {
    // Hacer TODOS los contenedores sortables y connectables, incluyendo disponibles
    $('.menu-container').sortable({
        connectWith: '.menu-container',
        placeholder: 'menu-item ui-sortable-placeholder',
        tolerance: 'pointer',
        cursor: 'move',
        opacity: 0.8,
        // PERMITIR ARRASTRAR TODOS LOS ELEMENTOS (incluidos IDs 1-6)
        items: '.menu-item',

        start: function(event, ui) {
            ui.placeholder.height(ui.item.height());
            $('.menu-container').addClass('drag-active');
        },

        stop: function(event, ui) {
            $('.menu-container').removeClass('drag-active drag-over');
            
            // APLICAR JERARQUÍA AUTOMÁTICA EN MENÚS DESTINO
            const targetLocation = ui.item.closest('.menu-container').data('location');
            if (targetLocation && !['available_pages', 'available_categories', 'available_custom'].includes(targetLocation)) {
                applyAutomaticHierarchy(targetLocation);
            }
            
            updateContainerStates();
            // Auto-guardar cambios después de cada movimiento
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

function applyAutomaticHierarchy(menuLocation) {
    let currentParent = null;
    
    $(`#${menuLocation}-menu .menu-item`).each(function(index) {
        const $item = $(this);
        const url = $item.find('.menu-item-url').text().trim();
        
        // Determinar si es página o categoría por la URL
        const isPage = !url.includes('/categoria/');
        const isCategory = url.includes('/categoria/');
        
        if (isPage) {
            // Las páginas son padres automáticamente
            $item.removeClass('level-1').addClass('level-0');
            $item.removeAttr('data-parent-id');
            $item.attr('data-level', '0');
            currentParent = $item.data('id');
            
            // Actualizar visual
            const title = $item.find('.menu-item-title');
            const titleText = title.text().replace('↳ ', '');
            title.html(`<strong>${titleText}</strong>`);
            
        } else if (isCategory && currentParent) {
            // Las categorías son hijos del último padre
            $item.removeClass('level-0').addClass('level-1');
            $item.attr('data-parent-id', currentParent);
            $item.attr('data-level', '1');
            
            // Actualizar visual
            const title = $item.find('.menu-item-title');
            const titleText = title.text().replace(/^<strong>|<\/strong>$/g, '').replace('↳ ', '');
            title.html(`↳ ${titleText}`);
        }
    });
}

// Mantener la función de jerarquía original pero sin controles adicionales
function initializeHierarchicalDragAndDrop() {
    // Sortable básico para elementos disponibles
    $('#available-pages, #available-categories, #available-custom').sortable({
        connectWith: '.menu-container[data-location="main"], .menu-container[data-location="footer"], .menu-container[data-location="sidebar"], .menu-container[data-location="user"], .menu-container[data-location="mobile"]',
        helper: 'clone',
        tolerance: 'pointer'
    });

    // Sortable jerárquico para todos los menús
    $('.menu-container[data-location="main"], .menu-container[data-location="footer"], .menu-container[data-location="sidebar"], .menu-container[data-location="user"], .menu-container[data-location="mobile"]').sortable({
        items: '.menu-item',
        tolerance: 'pointer',
        cursor: 'move',
        placeholder: 'ui-sortable-placeholder',

        start: function(event, ui) {
            ui.item.addClass('dragging');
        },

        stop: function(event, ui) {
            ui.item.removeClass('dragging');
            updateHierarchy();
        },

        update: function(event, ui) {
            updateHierarchy();
        }
    });
}

function updateHierarchy() {
    const updates = [];
    
    // Procesar todos los menús
    $('.menu-container[data-location]').each(function() {
        const location = $(this).data('location');
        
        // Solo procesar menús destino, no disponibles
        if (!['available_pages', 'available_categories', 'available_custom'].includes(location)) {
            let parentId = null;
            let orderCount = 0;

            $(this).find('.menu-item').each(function(index) {
                const $item = $(this);
                const itemId = $item.data('id');
                const level = $item.hasClass('level-0') ? 0 : 1;

                if (level === 0) {
                    parentId = itemId;
                    orderCount = 0;
                    updates.push({
                        id: itemId,
                        location: location,
                        parent_id: null,
                        order: index
                    });
                } else {
                    updates.push({
                        id: itemId,
                        location: location,
                        parent_id: parentId,
                        order: orderCount++
                    });
                }
            });
        }
    });

    // Enviar actualización
    if (updates.length > 0) {
        $.post(window.location.href, {
                action: 'update_hierarchy',
                updates: updates
            })
            .done(function(response) {
                if (response.success) {
                    showNotification('Jerarquía actualizada', 'success');
                } else {
                    showNotification('Error: ' + response.message, 'error');
                }
            });
    }
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
    const updates = [];

    // Recorrer cada contenedor de menú
    $('.menu-container').each(function() {
        const location = $(this).data('location');

        $(this).find('.menu-item').each(function(index) {
            const itemId = $(this).data('id');
            const parentId = $(this).attr('data-parent-id') || null;
            
            updates.push({
                id: itemId,
                location: location,
                order: index,
                parent_id: parentId
            });
        });
    });

    console.log('Guardando cambios:', updates); // DEBUG

    // Detectar si es solo reordenamiento en disponibles o cambio de ubicación
    const hasLocationChanges = updates.some(update => 
        !['available_pages', 'available_categories', 'available_custom'].includes(update.location) && 
        update.parent_id !== null
    );

    const action = hasLocationChanges ? 'update_hierarchy' : 'update_menu_positions';

    $.post(window.location.href, {
            action: action,
            updates: updates
        })
        .done(function(response) {
            console.log('Respuesta del servidor:', response); // DEBUG
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

function toggleCategoryStatus(categorySlug) {
    $.post(window.location.href, {
            action: 'toggle_category_status',
            category_slug: categorySlug
        })
        .done(function(response) {
            if (response.success) {
                location.reload();
            } else {
                showNotification('Error: ' + response.message, 'error');
            }
        });
}

function togglePageStatus(pageSlug) {
    $.post(window.location.href, {
            action: 'toggle_page_status',
            page_slug: pageSlug
        })
        .done(function(response) {
            if (response.success) {
                location.reload();
            } else {
                showNotification('Error: ' + response.message, 'error');
            }
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
    // PROTECCIÓN: No permitir eliminar IDs 1-6
    if (itemId <= 6) {
        showNotification('Este elemento está protegido y no se puede eliminar.', 'error');
        return;
    }

    // Obtener información del elemento para detectar tipo correctamente
    const $item = $(`[data-id="${itemId}"]`);
    const url = $item.find('.menu-item-url').text().trim();
    const hasChildren = $(`[data-parent-id="${itemId}"]`).length > 0;

    // Detectar tipo por URL
    let confirmMessage = '';
    let isPage = false;
    let isCategory = false;

    if (url.includes('/categoria/')) {
        isCategory = true;
        confirmMessage = '¿Mover esta categoría a "Categorías Disponibles"?';
    } else if (url.startsWith('/') && !url.includes('/categoria/')) {
        isPage = true;
        if (hasChildren) {
            confirmMessage = '¿Mover esta página a "Páginas Disponibles"?\n\nTambién se moverán todas sus categorías hijas a "Categorías Disponibles".';
        } else {
            confirmMessage = '¿Mover esta página a "Páginas Disponibles"?';
        }
    } else {
        confirmMessage = '¿Mover este enlace a "Enlaces Personalizados Disponibles"?';
    }

    if (confirm(confirmMessage)) {
        $.post(window.location.href, {
                action: 'smart_delete',
                element_id: itemId
            })
            .done(function(response) {
                if (response.success) {
                    // Animación de salida
                    $item.fadeOut(300, function() {
                        // Si es padre, también ocultar sus hijos
                        if (isPage && hasChildren) {
                            $(`[data-parent-id="${itemId}"]`).fadeOut(300);
                        }
                    });

                    showNotification(response.message, 'success');

                    // Recargar después de 1 segundo para ver los cambios
                    setTimeout(() => location.reload(), 1000);

                } else {
                    showNotification('Error: ' + response.message, 'error');
                }
            })
            .fail(function() {
                showNotification('Error de conexión', 'error');
            });
    }
}

function permanentDeleteCustomLink(itemId) {
    // PROTECCIÓN: No permitir eliminar IDs 1-6
    if (itemId <= 6) {
        showNotification('Este elemento está protegido y no se puede eliminar.', 'error');
        return;
    }

    const $item = $(`[data-id="${itemId}"]`);
    const linkTitle = $item.find('.menu-item-title').text();

    if (confirm(`¿Eliminar permanentemente el enlace "${linkTitle}"?\n\nEsta acción no se puede deshacer.`)) {
        $.post(window.location.href, {
                action: 'permanent_delete',
                element_id: itemId
            })
            .done(function(response) {
                if (response.success) {
                    $item.fadeOut(300, function() {
                        $(this).remove();
                        updateContainerStates();
                    });
                    showNotification(response.message, 'success');
                } else {
                    showNotification('Error: ' + response.message, 'error');
                }
            })
            .fail(function() {
                showNotification('Error de conexión', 'error');
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
            url: url
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

    // Preview Sidebar
    const sidebarItems = $('#sidebar-menu .menu-item:not(.ui-sortable-placeholder)');
    previewHTML += '<div class="col-md-6 mt-3"><h6>Menú Sidebar</h6><ul class="list-group">';
    if (sidebarItems.length > 0) {
        sidebarItems.each(function() {
            const title = $(this).find('.menu-item-title').text();
            const url = $(this).find('.menu-item-url').text();
            const isActive = !$(this).hasClass('inactive-item');
            previewHTML += `<li class="list-group-item ${!isActive ? 'text-muted' : ''}">${title} <small>(${url})</small></li>`;
        });
    } else {
        previewHTML += '<li class="list-group-item text-muted">Sin elementos</li>';
    }
    previewHTML += '</ul></div>';

    // Preview Menú Usuario
    const userItems = $('#user-menu .menu-item:not(.ui-sortable-placeholder)');
    previewHTML += '<div class="col-md-6 mt-3"><h6>Menú Usuario</h6><ul class="list-group">';
    if (userItems.length > 0) {
        userItems.each(function() {
            const title = $(this).find('.menu-item-title').text();
            const url = $(this).find('.menu-item-url').text();
            const isActive = !$(this).hasClass('inactive-item');
            previewHTML += `<li class="list-group-item ${!isActive ? 'text-muted' : ''}">${title} <small>(${url})</small></li>`;
        });
    } else {
        previewHTML += '<li class="list-group-item text-muted">Sin elementos</li>';
    }
    previewHTML += '</ul></div>';

    // Preview Menú Móvil
    const mobileItems = $('#mobile-menu .menu-item:not(.ui-sortable-placeholder)');
    previewHTML += '<div class="col-md-6 mt-3"><h6>Menú Móvil</h6><ul class="list-group">';
    if (mobileItems.length > 0) {
        mobileItems.each(function() {
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

function initializeHierarchicalDragAndDrop() {
    // Sortable básico para elementos disponibles
    $('#available-pages, #available-categories').sortable({
        connectWith: '#main-menu',
        helper: 'clone',
        tolerance: 'pointer'
    });

    // Sortable jerárquico para menú principal
    $('#main-menu').sortable({
        items: '.menu-item, .drop-zone-parent, .drop-zone-child',
        tolerance: 'pointer',
        cursor: 'move',
        placeholder: 'ui-sortable-placeholder',

        start: function(event, ui) {
            ui.item.addClass('dragging');
        },

        stop: function(event, ui) {
            ui.item.removeClass('dragging');
            updateHierarchy();
        },

        update: function(event, ui) {
            updateHierarchy();
        }
    });
}

function updateHierarchy() {
    const updates = [];
    let parentId = null;
    let orderCount = 0;

    $('#main-menu .menu-item').each(function(index) {
        const $item = $(this);
        const itemId = $item.data('id');
        const level = $item.hasClass('level-0') ? 0 : 1;

        if (level === 0) {
            parentId = itemId;
            orderCount = 0;
            updates.push({
                id: itemId,
                parent_id: null,
                order: index
            });
        } else {
            updates.push({
                id: itemId,
                parent_id: parentId,
                order: orderCount++
            });
        }
    });

    // Enviar actualización
    $.post(window.location.href, {
            action: 'update_hierarchy',
            updates: updates
        })
        .done(function(response) {
            if (response.success) {
                showNotification('Jerarquía actualizada', 'success');
            } else {
                showNotification('Error: ' + response.message, 'error');
            }
        });
}

function makeParent(itemId) {
    // Convertir un hijo en padre
    $.post(window.location.href, {
            action: 'update_hierarchy',
            updates: [{
                id: itemId,
                parent_id: null,
                order: 999
            }]
        })
        .done(function(response) {
            if (response.success) {
                location.reload();
            } else {
                showNotification('Error: ' + response.message, 'error');
            }
        });
}