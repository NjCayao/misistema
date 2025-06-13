<?php
// api/cart/add.php - Agregar producto al carrito
header('Content-Type: application/json');

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Incluir configuraciones
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/settings.php';
require_once __DIR__ . '/../../config/cart.php';

try {
    // Obtener datos del POST
    $productId = intval($_POST['product_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 1);
    
    // Validaciones básicas
    if ($productId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de producto inválido']);
        exit;
    }
    
    if ($quantity <= 0 || $quantity > 10) {
        echo json_encode(['success' => false, 'message' => 'Cantidad debe ser entre 1 y 10']);
        exit;
    }
    
    // Verificar que el producto existe y está activo
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id, name, is_active FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
        exit;
    }
    
    if (!$product['is_active']) {
        echo json_encode(['success' => false, 'message' => 'Producto no disponible']);
        exit;
    }
    
    // Verificar si ya está en el carrito (limitar cantidad total)
    if (Cart::hasItem($productId)) {
        $currentQuantity = Cart::getItemQuantity($productId);
        if (($currentQuantity + $quantity) > 10) {
            echo json_encode([
                'success' => false, 
                'message' => 'No puedes agregar más de 10 unidades del mismo producto'
            ]);
            exit;
        }
    }
    
    // Agregar al carrito
    $result = Cart::addItem($productId, $quantity);
    
    if ($result['success']) {
        // Log de actividad (opcional)
        logError("Producto agregado al carrito - ID: $productId, Cantidad: $quantity", 'cart.log');
        
        // Respuesta exitosa con datos del carrito
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'cart_count' => $result['cart_count'],
            'cart_total' => formatPrice($result['cart_total']),
            'cart_total_raw' => $result['cart_total'],
            'product_name' => $product['name']
        ]);
    } else {
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    logError("Error en API cart/add: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>