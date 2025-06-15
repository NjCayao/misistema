<?php
// admin/pages/content/archivosMenu/menu_data.php
// Archivo para todas las consultas y obtención de datos

// Obtener todos los elementos de menú organizados por ubicación
function getMenuData() {
    try {
        $db = Database::getInstance()->getConnection();

        // Obtener elementos disponibles - páginas (FORZAR orden por sort_order)
        $stmt = $db->query("
            SELECT m.*, p.is_active as page_is_active
            FROM menu_items m
            JOIN pages p ON p.slug = SUBSTRING(m.url, 2)  
            WHERE m.menu_location = 'available_pages'
            ORDER BY m.sort_order ASC
        ");
        $availablePages = $stmt->fetchAll();

        // Obtener elementos disponibles - categorías (FORZAR orden por sort_order)
        $stmt = $db->query("
            SELECT m.*, c.is_active as category_is_active
            FROM menu_items m
            JOIN categories c ON c.slug = SUBSTRING(m.url, 12)  
            WHERE m.menu_location = 'available_categories'
            ORDER BY m.sort_order ASC
        ");
        $availableCategories = $stmt->fetchAll();

        // Obtener elementos en menú principal CON JERARQUÍA (FORZAR ORDER BY)
        $stmt = $db->query("
            SELECT m.*, 
                   p_parent.title as parent_title,
                   CASE 
                       WHEN m.parent_id IS NULL THEN 0 
                       ELSE 1 
                   END as hierarchy_level
            FROM menu_items m 
            LEFT JOIN menu_items p_parent ON m.parent_id = p_parent.id
            WHERE m.menu_location = 'main' 
            ORDER BY m.sort_order ASC
        ");
        $mainMenuItems = $stmt->fetchAll();

        // Obtener elementos en footer CON JERARQUÍA (FORZAR ORDER BY)
        $stmt = $db->query("
            SELECT m.*, 
                   p_parent.title as parent_title,
                   CASE 
                       WHEN m.parent_id IS NULL THEN 0 
                       ELSE 1 
                   END as hierarchy_level
            FROM menu_items m 
            LEFT JOIN menu_items p_parent ON m.parent_id = p_parent.id
            WHERE m.menu_location = 'footer' 
            ORDER BY m.sort_order ASC
        ");
        $footerMenuItems = $stmt->fetchAll();

        // Obtener elementos en sidebar CON JERARQUÍA (FORZAR ORDER BY)
        $stmt = $db->query("
            SELECT m.*, 
                   p_parent.title as parent_title,
                   CASE 
                       WHEN m.parent_id IS NULL THEN 0 
                       ELSE 1 
                   END as hierarchy_level
            FROM menu_items m 
            LEFT JOIN menu_items p_parent ON m.parent_id = p_parent.id
            WHERE m.menu_location = 'sidebar' 
            ORDER BY m.sort_order ASC
        ");
        $sidebarMenuItems = $stmt->fetchAll();

        // Obtener elementos en menú usuario CON JERARQUÍA (FORZAR ORDER BY)
        $stmt = $db->query("
            SELECT m.*, 
                   p_parent.title as parent_title,
                   CASE 
                       WHEN m.parent_id IS NULL THEN 0 
                       ELSE 1 
                   END as hierarchy_level
            FROM menu_items m 
            LEFT JOIN menu_items p_parent ON m.parent_id = p_parent.id
            WHERE m.menu_location = 'user' 
            ORDER BY m.sort_order ASC
        ");
        $userMenuItems = $stmt->fetchAll();

        // Obtener elementos en menú móvil CON JERARQUÍA (FORZAR ORDER BY)
        $stmt = $db->query("
            SELECT m.*, 
                   p_parent.title as parent_title,
                   CASE 
                       WHEN m.parent_id IS NULL THEN 0 
                       ELSE 1 
                   END as hierarchy_level
            FROM menu_items m 
            LEFT JOIN menu_items p_parent ON m.parent_id = p_parent.id
            WHERE m.menu_location = 'mobile' 
            ORDER BY m.sort_order ASC
        ");
        $mobileMenuItems = $stmt->fetchAll();

        // Obtener enlaces personalizados disponibles (FORZAR orden por sort_order)
        $stmt = $db->query("
            SELECT * FROM menu_items 
            WHERE menu_location = 'available_custom' 
            ORDER BY sort_order ASC
        ");
        $availableCustomLinks = $stmt->fetchAll();

        return [
            'availablePages' => $availablePages,
            'availableCategories' => $availableCategories,
            'availableCustomLinks' => $availableCustomLinks,
            'mainMenuItems' => $mainMenuItems,
            'footerMenuItems' => $footerMenuItems,
            'sidebarMenuItems' => $sidebarMenuItems,
            'userMenuItems' => $userMenuItems,
            'mobileMenuItems' => $mobileMenuItems
        ];

    } catch (Exception $e) {
        return [
            'availablePages' => [],
            'availableCategories' => [],
            'availableCustomLinks' => [],
            'mainMenuItems' => [],
            'footerMenuItems' => [],
            'sidebarMenuItems' => [],
            'userMenuItems' => [],
            'mobileMenuItems' => [],
            'error' => 'Error al obtener elementos de menú: ' . $e->getMessage()
        ];
    }
}

// Función para obtener estadísticas de menús
function getMenuStats() {
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->query("
            SELECT 
                menu_location,
                COUNT(*) as total_items,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_items
            FROM menu_items 
            WHERE menu_location NOT IN ('available_pages', 'available_categories')
            GROUP BY menu_location
        ");
        
        $stats = [];
        while ($row = $stmt->fetch()) {
            $stats[$row['menu_location']] = [
                'total' => $row['total_items'],
                'active' => $row['active_items']
            ];
        }
        
        return $stats;
    } catch (Exception $e) {
        return [];
    }
}
?>