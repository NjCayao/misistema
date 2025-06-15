<?php
// admin/pages/content/archivosMenu/menu_actions.php
// Archivo para todas las acciones AJAX y POST

function handleMenuActions() {
    // Solo procesar si es una petición POST con action
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
        return null;
    }

    header('Content-Type: application/json');

    try {
        $db = Database::getInstance()->getConnection();
        $action = $_POST['action'] ?? '';

        error_log("Procesando acción: " . $action); // DEBUG

        switch ($action) {
            case 'toggle_category_status':
                return toggleCategoryStatus($db);

            case 'toggle_page_status':
                return togglePageStatus($db);

            case 'update_menu_positions':
                return updateMenuPositions($db);

            case 'toggle_status':
                return toggleItemStatus($db);

            case 'delete_element':
                return deleteElement($db);

            case 'permanent_delete':
                return permanentDelete($db);

            case 'create_custom_link':
                return createCustomLink($db);

            case 'update_hierarchy':
                return updateHierarchy($db);

            case 'smart_delete':
                return smartDelete($db);

            default:
                error_log("Acción no reconocida: " . $action); // DEBUG
                throw new Exception('Acción no válida: ' . $action);
        }
    } catch (Exception $e) {
        error_log("Error en handleMenuActions: " . $e->getMessage()); // DEBUG
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function toggleCategoryStatus($db) {
    $categorySlug = sanitize($_POST['category_slug'] ?? '');

    if (empty($categorySlug)) {
        throw new Exception('Slug de categoría requerido');
    }

    $stmt = $db->prepare("UPDATE categories SET is_active = NOT is_active WHERE slug = ?");
    $stmt->execute([$categorySlug]);

    if ($stmt->rowCount() > 0) {
        return ['success' => true, 'message' => 'Estado de categoría actualizado'];
    } else {
        return ['success' => false, 'message' => 'Categoría no encontrada'];
    }
}

function togglePageStatus($db) {
    $pageSlug = sanitize($_POST['page_slug'] ?? '');

    if (empty($pageSlug)) {
        throw new Exception('Slug de página requerido');
    }

    $stmt = $db->prepare("UPDATE pages SET is_active = NOT is_active WHERE slug = ?");
    $stmt->execute([$pageSlug]);

    if ($stmt->rowCount() > 0) {
        return ['success' => true, 'message' => 'Estado de página actualizado'];
    } else {
        return ['success' => false, 'message' => 'Página no encontrada'];
    }
}

function updateMenuPositions($db) {
    $updates = $_POST['updates'] ?? [];

    error_log("updateMenuPositions recibió: " . json_encode($updates)); // DEBUG

    if (empty($updates)) {
        return ['success' => false, 'message' => 'No hay actualizaciones para procesar'];
    }

    $db->beginTransaction();

    try {
        foreach ($updates as $update) {
            $itemId = intval($update['id']);
            $newLocation = sanitize($update['location']);
            $newOrder = intval($update['order']);

            error_log("Procesando item ID: $itemId, Location: $newLocation, Order: $newOrder"); // DEBUG

            // SIEMPRE actualizar sort_order para TODAS las ubicaciones
            if (in_array($newLocation, ['available_pages', 'available_categories', 'available_custom'])) {
                // Para secciones disponibles, SOLO actualizar sort_order (mantener ubicación)
                $stmt = $db->prepare("
                    UPDATE menu_items 
                    SET sort_order = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $result = $stmt->execute([$newOrder, $itemId]);
                error_log("Update disponibles - ID: $itemId, Order: $newOrder, Result: " . ($result ? 'OK' : 'FAIL')); // DEBUG
            }
            else {
                // Para menús destino, aplicar jerarquía automática Y actualizar sort_order
                $stmt = $db->prepare("SELECT url FROM menu_items WHERE id = ?");
                $stmt->execute([$itemId]);
                $item = $stmt->fetch();
                
                if ($item) {
                    $parentId = null;
                    
                    // Determinar si es página o categoría
                    $isPage = !str_contains($item['url'], '/categoria/');
                    $isCategory = str_contains($item['url'], '/categoria/');
                    
                    if ($isCategory) {
                        // Las categorías se convierten en hijos del último padre (página) en este menú
                        $stmt = $db->prepare("
                            SELECT id FROM menu_items 
                            WHERE menu_location = ? AND parent_id IS NULL 
                            ORDER BY sort_order DESC LIMIT 1
                        ");
                        $stmt->execute([$newLocation]);
                        $lastParent = $stmt->fetch();
                        
                        if ($lastParent) {
                            $parentId = $lastParent['id'];
                        }
                    }
                    // Las páginas automáticamente son padres (parent_id = NULL)
                    
                    $stmt = $db->prepare("
                        UPDATE menu_items 
                        SET menu_location = ?, parent_id = ?, sort_order = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $result = $stmt->execute([$newLocation, $parentId, $newOrder, $itemId]);
                    error_log("Update destino - ID: $itemId, Location: $newLocation, Parent: $parentId, Order: $newOrder, Result: " . ($result ? 'OK' : 'FAIL')); // DEBUG
                }
            }
        }

        $db->commit();
        error_log("Transacción completada exitosamente"); // DEBUG
        return ['success' => true, 'message' => 'Posiciones actualizadas'];

    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error en updateMenuPositions: " . $e->getMessage()); // DEBUG
        throw $e;
    }
}

function toggleItemStatus($db) {
    $elementId = intval($_POST['element_id'] ?? 0);

    if ($elementId <= 0) {
        throw new Exception('ID de elemento inválido');
    }

    $stmt = $db->prepare("UPDATE menu_items SET is_active = NOT is_active WHERE id = ?");
    $stmt->execute([$elementId]);

    return ['success' => true, 'message' => 'Estado actualizado'];
}

function deleteElement($db) {
    $elementId = intval($_POST['element_id'] ?? 0);

    if ($elementId <= 0) {
        throw new Exception('ID de elemento inválido');
    }

    // PROTECCIÓN: No permitir eliminar IDs 1-6
    if ($elementId <= 6) {
        return ['success' => false, 'message' => 'Este elemento está protegido y no se puede eliminar.'];
    }

    // Verificar si es un elemento automático
    $stmt = $db->prepare("SELECT url, menu_location FROM menu_items WHERE id = ?");
    $stmt->execute([$elementId]);
    $element = $stmt->fetch();

    if ($element && in_array($element['menu_location'], ['available_pages', 'available_categories'])) {
        return ['success' => false, 'message' => 'No se pueden eliminar elementos automáticos. Desactívalos desde Páginas o Categorías.'];
    }

    $stmt = $db->prepare("DELETE FROM menu_items WHERE id = ?");
    $stmt->execute([$elementId]);

    return ['success' => true, 'message' => 'Elemento eliminado'];
}

function permanentDelete($db) {
    $elementId = intval($_POST['element_id'] ?? 0);

    if ($elementId <= 0) {
        throw new Exception('ID de elemento inválido');
    }

    // PROTECCIÓN: No permitir eliminar IDs 1-6
    if ($elementId <= 6) {
        return ['success' => false, 'message' => 'Este elemento está protegido y no se puede eliminar.'];
    }

    $stmt = $db->prepare("DELETE FROM menu_items WHERE id = ?");
    $stmt->execute([$elementId]);

    return ['success' => true, 'message' => 'Enlace personalizado eliminado permanentemente'];
}

function createCustomLink($db) {
    $title = sanitize($_POST['title'] ?? '');
    $url = sanitize($_POST['url'] ?? '');
    $location = 'available_custom'; // Cambiar a available_custom siempre

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

    return ['success' => true, 'message' => 'Enlace personalizado creado'];
}

function updateHierarchy($db) {
    $updates = $_POST['updates'] ?? [];

    $db->beginTransaction();

    foreach ($updates as $update) {
        $itemId = intval($update['id']);
        $newParentId = !empty($update['parent_id']) ? intval($update['parent_id']) : null;
        $newOrder = intval($update['order']);
        $newLocation = sanitize($update['location'] ?? '');

        // Actualizar tanto la ubicación como la jerarquía
        $stmt = $db->prepare("
            UPDATE menu_items 
            SET menu_location = ?, parent_id = ?, sort_order = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$newLocation, $newParentId, $newOrder, $itemId]);
    }

    $db->commit();
    return ['success' => true, 'message' => 'Jerarquía actualizada'];
}

function smartDelete($db) {
    $elementId = intval($_POST['element_id'] ?? 0);

    if ($elementId <= 0) {
        throw new Exception('ID de elemento inválido');
    }

    // PROTECCIÓN: No permitir eliminar IDs 1-6
    if ($elementId <= 6) {
        return ['success' => false, 'message' => 'Este elemento está protegido y no se puede eliminar.'];
    }

    // Obtener información del elemento
    $stmt = $db->prepare("
        SELECT m.*, 
               CASE 
                   WHEN m.url LIKE '/categoria/%' THEN 'category'
                   WHEN m.url LIKE '/%' AND m.url NOT LIKE '/categoria/%' THEN 'page'
                   ELSE 'custom'
               END as element_type
        FROM menu_items m 
        WHERE m.id = ?
    ");
    $stmt->execute([$elementId]);
    $element = $stmt->fetch();

    if (!$element) {
        throw new Exception('Elemento no encontrado');
    }

    $db->beginTransaction();

    try {
        // CASO 1: Es una PÁGINA (elemento padre)
        if ($element['element_type'] === 'page') {
            // Mover página de vuelta a 'available_pages'
            $stmt = $db->prepare("
                UPDATE menu_items 
                SET menu_location = 'available_pages', parent_id = NULL, sort_order = 0
                WHERE id = ?
            ");
            $stmt->execute([$elementId]);

            // Encontrar hijos y moverlos
            $stmt = $db->prepare("SELECT id FROM menu_items WHERE parent_id = ?");
            $stmt->execute([$elementId]);
            $children = $stmt->fetchAll();

            foreach ($children as $child) {
                $stmt = $db->prepare("
                    UPDATE menu_items 
                    SET menu_location = 'available_categories', parent_id = NULL, sort_order = 0
                    WHERE id = ?
                ");
                $stmt->execute([$child['id']]);
            }

            $message = 'Página movida a disponibles. ' . count($children) . ' categorías hijas también movidas.';
        }
        // CASO 2: Es una CATEGORÍA
        elseif ($element['element_type'] === 'category') {
            $stmt = $db->prepare("
                UPDATE menu_items 
                SET menu_location = 'available_categories', parent_id = NULL, sort_order = 0
                WHERE id = ?
            ");
            $stmt->execute([$elementId]);

            $message = 'Categoría movida de vuelta a disponibles.';
        }
        // CASO 3: Es un ENLACE PERSONALIZADO
        else {
            // CAMBIO: En lugar de eliminar, mover a available_custom
            $stmt = $db->prepare("
                UPDATE menu_items 
                SET menu_location = 'available_custom', parent_id = NULL, sort_order = 0
                WHERE id = ?
            ");
            $stmt->execute([$elementId]);

            $message = 'Enlace personalizado movido a disponibles.';
        }

        $db->commit();
        return ['success' => true, 'message' => $message];
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}
?>