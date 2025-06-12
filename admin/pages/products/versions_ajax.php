<?php
// admin/pages/products/versions_ajax.php
require_once '../../../config/database.php';
require_once '../../../config/constants.php';
require_once '../../../config/functions.php';

// Verificar autenticación
if (!isAdmin()) {
    http_response_code(403);
    exit('No autorizado');
}

$product_id = intval($_GET['product_id'] ?? 0);

if ($product_id <= 0) {
    echo '<div class="alert alert-danger">ID de producto inválido</div>';
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener versiones del producto
    $stmt = $db->prepare("
        SELECT id, version, file_path, file_size, changelog, is_current, download_count, created_at
        FROM product_versions 
        WHERE product_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$product_id]);
    $versions = $stmt->fetchAll();
    
    if (empty($versions)) {
        echo '<div class="text-center p-4 text-muted">
                <i class="fas fa-inbox fa-3x mb-3"></i>
                <p>No hay versiones para este producto</p>
                <small>Agrega la primera versión usando el formulario de la izquierda</small>
              </div>';
        exit;
    }
    
    echo '<div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Versión</th>
                        <th>Tamaño</th>
                        <th>Descargas</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>';
    
    foreach ($versions as $version) {
        $fileSize = $version['file_size'] ? formatFileSize($version['file_size']) : 'N/A';
        $currentBadge = $version['is_current'] ? '<span class="badge badge-success">Actual</span>' : '';
        $changelogPreview = $version['changelog'] ? 
            '<i class="fas fa-info-circle text-info" title="' . htmlspecialchars(substr($version['changelog'], 0, 100)) . '..."></i>' : '';
        
        echo '<tr>
                <td>
                    <strong>v' . htmlspecialchars($version['version']) . '</strong>
                    ' . $changelogPreview . '
                </td>
                <td>' . $fileSize . '</td>
                <td>
                    <span class="badge badge-primary">' . number_format($version['download_count']) . '</span>
                </td>
                <td>
                    <small>' . date('d/m/Y H:i', strtotime($version['created_at'])) . '</small>
                </td>
                <td>' . $currentBadge . '</td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">';
        
        if (!$version['is_current']) {
            echo '<button class="btn btn-outline-success btn-sm" onclick="setCurrentVersion(' . $version['id'] . ')" title="Marcar como actual">
                    <i class="fas fa-star"></i>
                  </button>';
        }
        
        echo '    <button class="btn btn-outline-info btn-sm" onclick="showChangelog(' . $version['id'] . ', \'' . htmlspecialchars($version['version']) . '\', \'' . htmlspecialchars($version['changelog']) . '\')" title="Ver changelog">
                    <i class="fas fa-list"></i>
                  </button>
                  <button class="btn btn-outline-danger btn-sm" onclick="deleteVersion(' . $version['id'] . ', \'v' . htmlspecialchars($version['version']) . '\')" title="Eliminar">
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
                </td>
              </tr>';
    }
    
    echo '    </tbody>
            </table>
          </div>';
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error al cargar versiones: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// Función auxiliar para formatear tamaño de archivo
function formatFileSize($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>

<script>
function showChangelog(versionId, version, changelog) {
    $('#changelogVersion').text('v' + version);
    $('#changelogContent').html(changelog ? '<pre>' + changelog + '</pre>' : '<em class="text-muted">Sin changelog disponible</em>');
    $('#changelogModal').modal('show');
}
</script>