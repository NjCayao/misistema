<?php
// config/cart.php - Sistema de carrito de compras

/**
 * Clase para manejar el carrito de compras
 */
class Cart {
    
    /**
     * Inicializar carrito en sesión
     */
    public static function init() {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        if (!isset($_SESSION['cart_totals'])) {
            $_SESSION['cart_totals'] = [
                'subtotal' => 0,
                'tax' => 0,
                'total' => 0,
                'items_count' => 0
            ];
        }
    }
    
    /**
     * Agregar producto al carrito
     */
    public static function addItem($productId, $quantity = 1) {
        try {
            self::init();
            
            // Obtener información del producto
            $product = self::getProductInfo($productId);
            if (!$product) {
                return ['success' => false, 'message' => 'Producto no encontrado'];
            }
            
            // Verificar si ya existe en el carrito
            if (isset($_SESSION['cart'][$productId])) {
                $_SESSION['cart'][$productId]['quantity'] += $quantity;
            } else {
                $_SESSION['cart'][$productId] = [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'slug' => $product['slug'],
                    'price' => $product['price'],
                    'is_free' => $product['is_free'],
                    'image' => $product['image'],
                    'category_name' => $product['category_name'],
                    'quantity' => $quantity,
                    'added_at' => time()
                ];
            }
            
            // Actualizar totales
            self::updateTotals();
            
            return [
                'success' => true, 
                'message' => 'Producto agregado al carrito',
                'cart_count' => self::getItemsCount(),
                'cart_total' => self::getTotal()
            ];
            
        } catch (Exception $e) {
            logError("Error agregando al carrito: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error interno del sistema'];
        }
    }
    
    /**
     * Actualizar cantidad de un producto
     */
    public static function updateItem($productId, $quantity) {
        try {
            self::init();
            
            if (!isset($_SESSION['cart'][$productId])) {
                return ['success' => false, 'message' => 'Producto no encontrado en el carrito'];
            }
            
            if ($quantity <= 0) {
                return self::removeItem($productId);
            }
            
            $_SESSION['cart'][$productId]['quantity'] = $quantity;
            self::updateTotals();
            
            return [
                'success' => true,
                'message' => 'Cantidad actualizada',
                'cart_count' => self::getItemsCount(),
                'cart_total' => self::getTotal()
            ];
            
        } catch (Exception $e) {
            logError("Error actualizando carrito: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error interno del sistema'];
        }
    }
    
    /**
     * Eliminar producto del carrito
     */
    public static function removeItem($productId) {
        try {
            self::init();
            
            if (isset($_SESSION['cart'][$productId])) {
                unset($_SESSION['cart'][$productId]);
                self::updateTotals();
                
                return [
                    'success' => true,
                    'message' => 'Producto eliminado del carrito',
                    'cart_count' => self::getItemsCount(),
                    'cart_total' => self::getTotal()
                ];
            }
            
            return ['success' => false, 'message' => 'Producto no encontrado'];
            
        } catch (Exception $e) {
            logError("Error eliminando del carrito: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error interno del sistema'];
        }
    }
    
    /**
     * Limpiar todo el carrito
     */
    public static function clear() {
        $_SESSION['cart'] = [];
        self::updateTotals();
        
        return [
            'success' => true,
            'message' => 'Carrito vaciado',
            'cart_count' => 0,
            'cart_total' => 0
        ];
    }
    
    /**
     * Obtener contenido del carrito
     */
    public static function getItems() {
        self::init();
        return $_SESSION['cart'] ?? [];
    }
    
    /**
     * Obtener número total de items
     */
    public static function getItemsCount() {
        self::init();
        return $_SESSION['cart_totals']['items_count'];
    }
    
    /**
     * Obtener subtotal
     */
    public static function getSubtotal() {
        self::init();
        return $_SESSION['cart_totals']['subtotal'];
    }
    
    /**
     * Obtener impuestos
     */
    public static function getTax() {
        self::init();
        return $_SESSION['cart_totals']['tax'];
    }
    
    /**
     * Obtener total final
     */
    public static function getTotal() {
        self::init();
        return $_SESSION['cart_totals']['total'];
    }
    
    /**
     * Obtener todos los totales
     */
    public static function getTotals() {
        self::init();
        return $_SESSION['cart_totals'];
    }
    
    /**
     * Verificar si el carrito está vacío
     */
    public static function isEmpty() {
        self::init();
        return empty($_SESSION['cart']);
    }
    
    /**
     * Verificar si un producto está en el carrito
     */
    public static function hasItem($productId) {
        self::init();
        return isset($_SESSION['cart'][$productId]);
    }
    
    /**
     * Obtener cantidad de un producto específico
     */
    public static function getItemQuantity($productId) {
        self::init();
        return $_SESSION['cart'][$productId]['quantity'] ?? 0;
    }
    
    /**
     * Actualizar totales del carrito
     */
    private static function updateTotals() {
        self::init();
        
        $subtotal = 0;
        $itemsCount = 0;
        
        foreach ($_SESSION['cart'] as $item) {
            if (!$item['is_free']) {
                $subtotal += ($item['price'] * $item['quantity']);
            }
            $itemsCount += $item['quantity'];
        }
        
        // Calcular impuestos (configurable)
        $taxRate = floatval(Settings::get('tax_rate', '0')) / 100;
        $tax = $subtotal * $taxRate;
        $total = $subtotal + $tax;
        
        $_SESSION['cart_totals'] = [
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
            'items_count' => $itemsCount,
            'tax_rate' => $taxRate * 100
        ];
    }
    
    /**
     * Obtener información del producto desde la BD
     */
    private static function getProductInfo($productId) {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT p.*, c.name as category_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.id = ? AND p.is_active = 1
            ");
            $stmt->execute([$productId]);
            return $stmt->fetch();
        } catch (Exception $e) {
            logError("Error obteniendo producto para carrito: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validar carrito antes del checkout
     */
    public static function validate() {
        self::init();
        $errors = [];
        
        if (self::isEmpty()) {
            $errors[] = 'El carrito está vacío';
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Verificar que todos los productos sigan activos
        foreach ($_SESSION['cart'] as $productId => $item) {
            $product = self::getProductInfo($productId);
            if (!$product) {
                $errors[] = "El producto '{$item['name']}' ya no está disponible";
                unset($_SESSION['cart'][$productId]);
            } elseif ($product['price'] != $item['price']) {
                // Actualizar precio si cambió
                $_SESSION['cart'][$productId]['price'] = $product['price'];
                $errors[] = "El precio del producto '{$item['name']}' ha cambiado";
            }
        }
        
        if (!empty($errors)) {
            self::updateTotals();
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'updated_totals' => self::getTotals()
        ];
    }
    
    /**
     * Preparar datos para el checkout
     */
    public static function prepareCheckoutData() {
        $validation = self::validate();
        if (!$validation['valid']) {
            return $validation;
        }
        
        $items = self::getItems();
        $totals = self::getTotals();
        
        // Separar items gratuitos de los pagados
        $freeItems = [];
        $paidItems = [];
        
        foreach ($items as $item) {
            if ($item['is_free']) {
                $freeItems[] = $item;
            } else {
                $paidItems[] = $item;
            }
        }
        
        return [
            'valid' => true,
            'items' => $items,
            'free_items' => $freeItems,
            'paid_items' => $paidItems,
            'totals' => $totals,
            'has_paid_items' => !empty($paidItems),
            'has_free_items' => !empty($freeItems),
            'requires_payment' => !empty($paidItems) && $totals['total'] > 0
        ];
    }
    
    /**
     * Convertir carrito en items de orden
     */
    public static function convertToOrderItems() {
        $items = self::getItems();
        $orderItems = [];
        
        foreach ($items as $item) {
            $orderItems[] = [
                'product_id' => $item['id'],
                'product_name' => $item['name'],
                'price' => $item['is_free'] ? 0 : $item['price'],
                'quantity' => $item['quantity']
            ];
        }
        
        return $orderItems;
    }
}

// Funciones helper para mantener compatibilidad
function addToCart($productId, $quantity = 1) {
    return Cart::addItem($productId, $quantity);
}

function updateCartItem($productId, $quantity) {
    return Cart::updateItem($productId, $quantity);
}

function removeFromCart($productId) {
    return Cart::removeItem($productId);
}

function getCartCount() {
    return Cart::getItemsCount();
}

function getCartTotal() {
    return Cart::getTotal();
}

function clearCart() {
    return Cart::clear();
}
?>