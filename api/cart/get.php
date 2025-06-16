<?php
// api/cart/get.php - Obtener contenido del carrito
header('Content-Type: application/json');

// Solo permitir GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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
    // Validar carrito (verificar que productos sigan activos)
    $validation = Cart::validate();

    // Obtener datos del carrito
    $items = Cart::getItems();
    $totals = Cart::getTotals();
    $isEmpty = Cart::isEmpty();

    // Formatear items para el frontend
    $formattedItems = [];
    foreach ($items as $productId => $item) {
        $itemSubtotal = $item['is_free'] ? 0 : ($item['price'] * $item['quantity']);

        $formattedItems[] = [
            'id' => $item['id'],
            'name' => $item['name'],
            'slug' => $item['slug'],
            'price' => $item['is_free'] ? 'GRATIS' : formatPrice($item['price']),
            'price_raw' => $item['price'],
            'is_free' => $item['is_free'],
            'image' => $item['image'],
            'category_name' => $item['category_name'],
            'quantity' => $item['quantity'],
            'subtotal' => $item['is_free'] ? 'GRATIS' : formatPrice($itemSubtotal),
            'subtotal_raw' => $itemSubtotal,
            'product_url' => SITE_URL . '/producto/' . $item['slug'],
            'image_url' => $item['image'] ? UPLOADS_URL . '/products/' . $item['image'] : null,
            'added_at' => $item['added_at'],
            'added_ago' => timeAgo(date('Y-m-d H:i:s', $item['added_at']))
        ];
    }

    // Respuesta
    $response = [
        'success' => true,
        'cart_empty' => $isEmpty,
        'items' => $formattedItems,
        'items_count' => $totals['items_count'],
        'totals' => [
            'subtotal' => formatPrice($totals['subtotal']),
            'subtotal_raw' => $totals['subtotal'],
            'tax' => formatPrice($totals['tax']),
            'tax_raw' => $totals['tax'],
            'total' => formatPrice($totals['total']),
            'total_raw' => $totals['total'],
            'tax_rate' => $totals['tax_rate'] ?? 0,
            'currency_symbol' => Settings::get('currency_symbol', '$')
        ],
        'validation' => $validation
    ];

    // Si hay errores de validación, incluirlos
    if (!$validation['valid']) {
        $response['validation_errors'] = $validation['errors'];
        $response['cart_updated'] = true;
    }

    echo json_encode($response);
} catch (Exception $e) {
    logError("Error en API cart/get: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
