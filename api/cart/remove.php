<?php
// api/cart/remove.php - Eliminar producto del carrito
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
    
    // Validaciones básicas
    if ($productId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de producto inválido']);
        exit;
    }
    
    // Verificar que el producto está en el carrito
    if (!Cart::hasItem($productId)) {
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado en el carrito']);
        exit;
    }
    
    // Obtener nombre del producto antes de eliminarlo
    $cartItems = Cart::getItems();
    $productName = $cartItems[$productId]['name'] ?? 'Producto';
    
    // Eliminar del carrito
    $result = Cart::removeItem($productId);
    
    if ($result['success']) {
        // Obtener totales actualizados
        $totals = Cart::getTotals();
        
        echo json_encode([
            'success' => true,
            'message' => "'{$productName}' eliminado del carrito",
            'cart_count' => $result['cart_count'],
            'cart_total' => formatPrice($result['cart_total']),
            'cart_total_raw' => $result['cart_total'],
            'product_name' => $productName,
            'cart_empty' => Cart::isEmpty(),
            'totals' => [
                'subtotal' => formatPrice($totals['subtotal']),
                'subtotal_raw' => $totals['subtotal'],
                'tax' => formatPrice($totals['tax']),
                'tax_raw' => $totals['tax'],
                'total' => formatPrice($totals['total']),
                'total_raw' => $totals['total'],
                'tax_rate' => $totals['tax_rate']
            ]
        ]);
    } else {
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    logError("Error en API cart/remove: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>