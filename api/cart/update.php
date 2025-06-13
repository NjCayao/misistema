<?php
// api/cart/update.php - Actualizar cantidad en el carrito
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
    $quantity = intval($_POST['quantity'] ?? 0);
    
    // Validaciones básicas
    if ($productId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de producto inválido']);
        exit;
    }
    
    if ($quantity < 0 || $quantity > 10) {
        echo json_encode(['success' => false, 'message' => 'Cantidad debe ser entre 0 y 10']);
        exit;
    }
    
    // Verificar que el producto está en el carrito
    if (!Cart::hasItem($productId)) {
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado en el carrito']);
        exit;
    }
    
    // Actualizar cantidad
    $result = Cart::updateItem($productId, $quantity);
    
    if ($result['success']) {
        // Obtener información actualizada del carrito
        $cartItems = Cart::getItems();
        $totals = Cart::getTotals();
        
        $response = [
            'success' => true,
            'message' => $result['message'],
            'cart_count' => $result['cart_count'],
            'cart_total' => formatPrice($result['cart_total']),
            'cart_total_raw' => $result['cart_total'],
            'item_removed' => $quantity == 0,
            'totals' => [
                'subtotal' => formatPrice($totals['subtotal']),
                'subtotal_raw' => $totals['subtotal'],
                'tax' => formatPrice($totals['tax']),
                'tax_raw' => $totals['tax'],
                'total' => formatPrice($totals['total']),
                'total_raw' => $totals['total'],
                'tax_rate' => $totals['tax_rate']
            ]
        ];
        
        // Si el producto específico todavía está en el carrito, incluir su subtotal
        if (isset($cartItems[$productId])) {
            $item = $cartItems[$productId];
            $itemSubtotal = $item['is_free'] ? 0 : ($item['price'] * $item['quantity']);
            $response['item_subtotal'] = formatPrice($itemSubtotal);
            $response['item_subtotal_raw'] = $itemSubtotal;
        }
        
        echo json_encode($response);
    } else {
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    logError("Error en API cart/update: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>