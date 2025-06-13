<?php
// test_page_direct.php - Crear en la raíz del proyecto
// Acceder como: http://localhost/misistema/test_page_direct.php?slug=sobre-nosotros

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html><html><head><title>Test Direct</title></head><body>";
echo "<h1>Prueba Directa de page.php</h1>";

try {
    echo "<p>1. Incluyendo archivos...</p>";
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/config/constants.php';
    require_once __DIR__ . '/config/functions.php';
    require_once __DIR__ . '/config/settings.php';
    echo "<p style='color:green'>✓ Archivos incluidos correctamente</p>";

    // Verificar modo mantenimiento
    if (Settings::get('maintenance_mode', '0') == '1' && !isAdmin()) {
        echo "<p style='color:orange'>Sistema en mantenimiento</p>";
        exit;
    }

    // Obtener slug
    $slug = $_GET['slug'] ?? '';
    echo "<p>2. Slug recibido: '{$slug}'</p>";

    if (empty($slug)) {
        echo "<p style='color:red'>Slug vacío</p>";
        exit;
    }

    try {
        $db = Database::getInstance()->getConnection();
        echo "<p>3. Conexión a BD establecida</p>";
        
        // Obtener página
        $stmt = $db->prepare("SELECT * FROM pages WHERE slug = ? AND is_active = 1");
        $stmt->execute([$slug]);
        $page = $stmt->fetch();
        
        if (!$page) {
            echo "<p style='color:red'>Página no encontrada</p>";
            exit;
        }
        
        echo "<p style='color:green'>4. Página encontrada: {$page['title']}</p>";
        
        // Incrementar contador de vistas
        $stmt = $db->prepare("UPDATE pages SET view_count = COALESCE(view_count, 0) + 1 WHERE id = ?");
        $stmt->execute([$page['id']]);
        echo "<p>5. Contador actualizado</p>";
        
    } catch (Exception $e) {
        echo "<p style='color:red'>Error BD: " . $e->getMessage() . "</p>";
        exit;
    }

    $siteName = Settings::get('site_name', 'MiSistema');
    $pageTitle = $page['meta_title'] ?: $page['title'];
    $pageDescription = $page['meta_description'] ?: substr(strip_tags($page['content']), 0, 160);
    
    echo "<p>6. Variables preparadas</p>";
    echo "<p>Título: {$pageTitle}</p>";
    echo "<p>Descripción: {$pageDescription}</p>";
    
    echo "<h2>Contenido de la página:</h2>";
    echo "<div style='border:1px solid #ccc; padding:20px; margin:20px 0;'>";
    echo $page['content'];
    echo "</div>";
    
    echo "<p style='color:green'><strong>✓ TODO FUNCIONA CORRECTAMENTE</strong></p>";
    
} catch (Exception $e) {
    echo "<p style='color:red'><strong>✗ ERROR: " . $e->getMessage() . "</strong></p>";
    echo "<p>Archivo: " . $e->getFile() . "</p>";
    echo "<p>Línea: " . $e->getLine() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
} catch (Error $e) {
    echo "<p style='color:red'><strong>✗ ERROR FATAL: " . $e->getMessage() . "</strong></p>";
    echo "<p>Archivo: " . $e->getFile() . "</p>";
    echo "<p>Línea: " . $e->getLine() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "</body></html>";
?>