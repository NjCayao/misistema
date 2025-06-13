<?php
// pages/maintenance.php - Página de mantenimiento
$siteName = Settings::get('site_name', 'MiSistema');
$maintenanceMessage = Settings::get('maintenance_message', 'Sitio en mantenimiento');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mantenimiento - <?php echo htmlspecialchars($siteName); ?></title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
        }
        
        .maintenance-card {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 500px;
            margin: 20px;
        }
        
        .maintenance-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            font-size: 3rem;
            color: white;
        }
        
        .maintenance-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #333;
        }
        
        .maintenance-message {
            font-size: 1.1rem;
            color: #666;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .back-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            color: #764ba2;
        }
    </style>
</head>
<body>
    <div class="maintenance-card">
        <div class="maintenance-icon">
            <i class="fas fa-tools"></i>
        </div>
        
        <h1 class="maintenance-title">Sitio en Mantenimiento</h1>
        
        <p class="maintenance-message">
            <?php echo htmlspecialchars($maintenanceMessage); ?>
        </p>
        
        <p class="text-muted">
            Volveremos pronto. Gracias por tu paciencia.
        </p>
        
        <hr class="my-4">
        
        <a href="<?php echo SITE_URL; ?>" class="back-link">
            <i class="fas fa-arrow-left me-2"></i>Intentar nuevamente
        </a>
    </div>
</body>
</html>