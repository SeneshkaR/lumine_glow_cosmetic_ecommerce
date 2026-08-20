<?php
/**
 * Luminé Glow - Core Functions
 * Complete implementation based on project proposal
 */

// ============================================
// SECURITY FUNCTIONS
// ============================================

function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePhone($phone) {
    return preg_match('/^(?:\+94|0)[0-9]{9,10}$/', $phone);
}

function validatePassword($password) {
    return strlen($password) >= 6;
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function passwordHash($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function passwordVerify($password, $hash) {
    return password_verify($password, $hash);
}

// ============================================
// FLASH MESSAGES
// ============================================

function setFlashMessage($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function displayFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $type = sanitize($_SESSION['flash']['type']);
        $message = sanitize($_SESSION['flash']['message']);
        unset($_SESSION['flash']);
        $class = $type === 'error' ? 'alert-error' : 'alert-success';
        return "<div class='alert $class'>$message</div>";
    }
    return '';
}

// ============================================
// USER MANAGEMENT FUNCTIONS
// ============================================

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getUserRole() {
    return $_SESSION['user_role'] ?? 'guest';
}

function hasPermission($requiredRole) {
    $roles = ['guest' => 0, 'customer' => 1, 'editor' => 2, 'admin' => 3];
    $userRole = getUserRole();
    return $roles[$userRole] >= $roles[$requiredRole];
}

function requireLogin() {
    if (!isLoggedIn()) {
        setFlashMessage('error', 'Please login to access this feature.');
        header('Location: login.php');
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    if (!hasPermission($role)) {
        setFlashMessage('error', 'You do not have permission to access this page.');
        header('Location: index.php');
        exit;
    }
}

function getCurrentUser($pdo) {
    if (!isLoggedIn()) return null;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function getUserById($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

// ============================================
// CART FUNCTIONS
// ============================================

function getCartCount() {
    return isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
}

function getCartItems($pdo) {
    if (empty($_SESSION['cart'])) return [];
    
    $ids = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM vw_product_details WHERE product_id IN ($placeholders)");
    $stmt->execute($ids);
    $items = $stmt->fetchAll();
    
    foreach ($items as &$item) {
        $item['quantity'] = $_SESSION['cart'][$item['product_id']];
        $item['subtotal'] = $item['price'] * $item['quantity'];
    }
    return $items;
}

function getCartTotal($pdo) {
    $items = getCartItems($pdo);
    $total = 0;
    foreach ($items as $item) {
        $total += $item['subtotal'];
    }
    return $total;
}

function addToCart($productId, $quantity = 1) {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    $currentQty = $_SESSION['cart'][$productId] ?? 0;
    $_SESSION['cart'][$productId] = $currentQty + $quantity;
}

function removeFromCart($productId) {
    if (isset($_SESSION['cart'][$productId])) {
        unset($_SESSION['cart'][$productId]);
    }
}

function updateCartQuantity($productId, $quantity) {
    if ($quantity <= 0) {
        removeFromCart($productId);
    } else {
        $_SESSION['cart'][$productId] = $quantity;
    }
}

function clearCart() {
    $_SESSION['cart'] = [];
}

// ============================================
// ORDER FUNCTIONS
// ============================================

function createOrder($pdo, $userId, $addressId, $cartItems, $total, $discount = 0) {
    try {
        $pdo->beginTransaction();
        
        // Generate order number
        $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        
        // Insert order
        $stmt = $pdo->prepare("
            INSERT INTO `order` (user_id, address_id, order_number, total_amount, subtotal, discount_amount)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $addressId, $orderNumber, $total, $total + $discount, $discount]);
        $orderId = $pdo->lastInsertId();
        
        // Insert order items
        foreach ($cartItems as $item) {
            $stmt = $pdo->prepare("
                INSERT INTO order_item (order_id, product_id, quantity, unit_price, total_price)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $orderId,
                $item['product_id'],
                $item['quantity'],
                $item['price'],
                $item['subtotal']
            ]);
            
            // Update stock
            $stmt = $pdo->prepare("UPDATE product SET stock = stock - ? WHERE product_id = ?");
            $stmt->execute([$item['quantity'], $item['product_id']]);
        }
        
        // Add status history
        $stmt = $pdo->prepare("
            INSERT INTO order_status_history (order_id, status_from, status_to, changed_by)
            VALUES (?, NULL, 'pending', ?)
        ");
        $stmt->execute([$orderId, $userId]);
        
        $pdo->commit();
        return $orderId;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Order creation failed: ' . $e->getMessage());
        return false;
    }
}

function getOrderStatus($pdo, $orderId) {
    $stmt = $pdo->prepare("SELECT order_status FROM `order` WHERE order_id = ?");
    $stmt->execute([$orderId]);
    return $stmt->fetchColumn();
}

function updateOrderStatus($pdo, $orderId, $newStatus, $userId = null) {
    $oldStatus = getOrderStatus($pdo, $orderId);
    if ($oldStatus === $newStatus) return true;
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE `order` SET order_status = ? WHERE order_id = ?");
        $stmt->execute([$newStatus, $orderId]);
        
        $stmt = $pdo->prepare("
            INSERT INTO order_status_history (order_id, status_from, status_to, changed_by)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$orderId, $oldStatus, $newStatus, $userId]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Status update failed: ' . $e->getMessage());
        return false;
    }
}

function getOrdersByUser($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT * FROM vw_order_summary 
        WHERE customer_email = (SELECT email FROM users WHERE user_id = ?)
        ORDER BY order_date DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getOrderDetails($pdo, $orderId) {
    $stmt = $pdo->prepare("
        SELECT * FROM vw_order_summary WHERE order_id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    
    if ($order) {
        $stmt = $pdo->prepare("
            SELECT oi.*, p.product_name, p.image_url
            FROM order_item oi
            LEFT JOIN product p ON oi.product_id = p.product_id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$orderId]);
        $order['items'] = $stmt->fetchAll();
    }
    
    return $order;
}

// ============================================
// PRODUCT FUNCTIONS
// ============================================

function getProductById($pdo, $productId) {
    $stmt = $pdo->prepare("SELECT * FROM vw_product_details WHERE product_id = ?");
    $stmt->execute([$productId]);
    return $stmt->fetch();
}

function getProductsByCategory($pdo, $categoryId, $limit = null) {
    $sql = "SELECT * FROM vw_product_details WHERE category_id = ? ORDER BY product_id DESC";
    if ($limit) {
        $sql .= " LIMIT " . intval($limit);
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$categoryId]);
    return $stmt->fetchAll();
}

function getProductsBySkinType($pdo, $skinType) {
    $stmt = $pdo->prepare("
        SELECT * FROM vw_product_details 
        WHERE skin_type = ? OR skin_type = 'all'
        ORDER BY product_id DESC
    ");
    $stmt->execute([$skinType]);
    return $stmt->fetchAll();
}

function getProductsBySkinTone($pdo, $skinTone) {
    $stmt = $pdo->prepare("
        SELECT * FROM vw_product_details 
        WHERE skin_tone = ? OR skin_tone = 'all'
        ORDER BY product_id DESC
    ");
    $stmt->execute([$skinTone]);
    return $stmt->fetchAll();
}

function getFeaturedProducts($pdo, $limit = 8) {
    $stmt = $pdo->prepare("
        SELECT * FROM vw_product_details 
        WHERE is_featured = 1 
        ORDER BY product_id DESC 
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function getBestsellers($pdo, $limit = 8) {
    $stmt = $pdo->prepare("
        SELECT * FROM vw_product_details 
        WHERE is_bestseller = 1 
        ORDER BY product_id DESC 
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function searchProducts($pdo, $query) {
    $stmt = $pdo->prepare("
        SELECT * FROM vw_product_details 
        WHERE product_name LIKE ? OR description LIKE ?
        ORDER BY product_id DESC
    ");
    $searchTerm = "%$query%";
    $stmt->execute([$searchTerm, $searchTerm]);
    return $stmt->fetchAll();
}

function getProductVariants($pdo, $productId) {
    $stmt = $pdo->prepare("SELECT * FROM product_variant WHERE product_id = ?");
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

function getProductStock($pdo, $productId) {
    $stmt = $pdo->prepare("SELECT stock FROM product WHERE product_id = ?");
    $stmt->execute([$productId]);
    return $stmt->fetchColumn();
}

function updateProductStock($pdo, $productId, $quantity) {
    $stmt = $pdo->prepare("UPDATE product SET stock = stock - ? WHERE product_id = ? AND stock >= ?");
    return $stmt->execute([$quantity, $productId, $quantity]) && $stmt->rowCount() > 0;
}

// ============================================
// BEAUTY PROFILE FUNCTIONS
// ============================================

function getBeautyProfile($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT * FROM beauty_profile WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

function saveBeautyProfile($pdo, $userId, $data) {
    $existing = getBeautyProfile($pdo, $userId);
    
    if ($existing) {
        $sql = "UPDATE beauty_profile SET 
            skin_type = ?, 
            skin_tone = ?, 
            undertone = ?, 
            concern = ?, 
            foundation_shade = ?,
            concealer_shade = ?,
            preferred_brands = ?,
            allergy_notes = ?,
            updated_at = CURRENT_TIMESTAMP
            WHERE user_id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $data['skin_type'] ?? null,
            $data['skin_tone'] ?? null,
            $data['undertone'] ?? null,
            $data['concern'] ?? null,
            $data['foundation_shade'] ?? null,
            $data['concealer_shade'] ?? null,
            $data['preferred_brands'] ?? null,
            $data['allergy_notes'] ?? null,
            $userId
        ]);
    } else {
        $sql = "INSERT INTO beauty_profile (
            user_id, skin_type, skin_tone, undertone, concern, 
            foundation_shade, concealer_shade, preferred_brands, allergy_notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $userId,
            $data['skin_type'] ?? null,
            $data['skin_tone'] ?? null,
            $data['undertone'] ?? null,
            $data['concern'] ?? null,
            $data['foundation_shade'] ?? null,
            $data['concealer_shade'] ?? null,
            $data['preferred_brands'] ?? null,
            $data['allergy_notes'] ?? null
        ]);
    }
}

function getProductRecommendations($pdo, $userId) {
    $stmt = $pdo->prepare("CALL GetProductRecommendations(?)");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

// ============================================
// WISHLIST FUNCTIONS
// ============================================

function getWishlist($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT w.*, p.product_name, p.price, p.image_url, c.category_name
        FROM wishlist w
        LEFT JOIN product p ON w.product_id = p.product_id
        LEFT JOIN category c ON p.category_id = c.category_id
        WHERE w.user_id = ?
        ORDER BY w.added_date DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function addToWishlist($pdo, $userId, $productId) {
    $stmt = $pdo->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
    return $stmt->execute([$userId, $productId]);
}

function removeFromWishlist($pdo, $userId, $productId) {
    $stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
    return $stmt->execute([$userId, $productId]);
}

function isInWishlist($pdo, $userId, $productId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$userId, $productId]);
    return $stmt->fetchColumn() > 0;
}

// ============================================
// REVIEW FUNCTIONS
// ============================================

function getProductReviews($pdo, $productId, $limit = null) {
    $sql = "
        SELECT r.*, u.name as user_name
        FROM review r
        LEFT JOIN users u ON r.user_id = u.user_id
        WHERE r.product_id = ? AND r.status = 'approved'
        ORDER BY r.review_date DESC
    ";
    if ($limit) {
        $sql .= " LIMIT " . intval($limit);
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

function addReview($pdo, $userId, $productId, $orderId, $rating, $comment) {
    $stmt = $pdo->prepare("
        INSERT INTO review (user_id, product_id, order_id, rating, comment, status)
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");
    return $stmt->execute([$userId, $productId, $orderId, $rating, $comment]);
}

function getUserCanReview($pdo, $userId, $productId) {
    // Check if user has purchased this product
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM order_item oi
        LEFT JOIN `order` o ON oi.order_id = o.order_id
        WHERE o.user_id = ? AND oi.product_id = ? AND o.order_status = 'delivered'
    ");
    $stmt->execute([$userId, $productId]);
    return $stmt->fetchColumn() > 0;
}

// ============================================
// PROMO CODE FUNCTIONS
// ============================================

function validatePromoCode($pdo, $code, $subtotal) {
    $stmt = $pdo->prepare("
        SELECT * FROM promo_code 
        WHERE code = ? 
        AND is_active = 1 
        AND valid_from <= CURDATE() 
        AND valid_until >= CURDATE()
        AND (usage_limit IS NULL OR used_count < usage_limit)
    ");
    $stmt->execute([$code]);
    $promo = $stmt->fetch();
    
    if (!$promo) {
        return ['valid' => false, 'message' => 'Invalid or expired promo code.'];
    }
    
    if ($subtotal < $promo['minimum_order_amount']) {
        return [
            'valid' => false, 
            'message' => 'Minimum order amount of ' . formatPrice($promo['minimum_order_amount']) . ' required.'
        ];
    }
    
    $discount = 0;
    if ($promo['discount_type'] === 'percentage') {
        $discount = ($promo['discount_value'] / 100) * $subtotal;
        if ($promo['maximum_discount_amount'] && $discount > $promo['maximum_discount_amount']) {
            $discount = $promo['maximum_discount_amount'];
        }
    } else {
        $discount = $promo['discount_value'];
    }
    
    return [
        'valid' => true,
        'discount' => $discount,
        'promo_id' => $promo['promo_id'],
        'code' => $promo['code']
    ];
}

function applyPromoCode($pdo, $promoId) {
    $stmt = $pdo->prepare("UPDATE promo_code SET used_count = used_count + 1 WHERE promo_id = ?");
    return $stmt->execute([$promoId]);
}

// ============================================
// ADDRESS FUNCTIONS
// ============================================

function getUserAddresses($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT * FROM address WHERE user_id = ? ORDER BY is_default DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getDefaultAddress($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT * FROM address WHERE user_id = ? AND is_default = 1");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

function addAddress($pdo, $userId, $data) {
    // If this is the first address or set as default, unset other defaults
    if ($data['is_default'] ?? false) {
        $stmt = $pdo->prepare("UPDATE address SET is_default = 0 WHERE user_id = ?");
        $stmt->execute([$userId]);
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO address (user_id, street, city, state, postal_code, country, is_default)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    return $stmt->execute([
        $userId,
        $data['street'],
        $data['city'],
        $data['state'] ?? null,
        $data['postal_code'] ?? null,
        $data['country'] ?? 'Sri Lanka',
        $data['is_default'] ?? false
    ]);
}

// ============================================
// FORMATTING FUNCTIONS
// ============================================

function formatPrice($amount) {
    return 'Rs. ' . number_format($amount, 2);
}

function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

function formatDateTime($date) {
    return date('M d, Y h:i A', strtotime($date));
}

function getStatusBadge($status) {
    $badges = [
        'pending' => 'warning',
        'processing' => 'info',
        'shipped' => 'primary',
        'delivered' => 'success',
        'cancelled' => 'danger',
        'approved' => 'success',
        'rejected' => 'danger',
        'completed' => 'success',
        'failed' => 'danger',
        'refunded' => 'warning'
    ];
    $class = $badges[$status] ?? 'secondary';
    return "<span class='badge badge-$class'>" . ucfirst($status) . "</span>";
}

// ============================================
// CATEGORY FUNCTIONS
// ============================================

function getAllCategories($pdo) {
    $stmt = $pdo->query("SELECT * FROM category ORDER BY category_name");
    return $stmt->fetchAll();
}

function getCategoryById($pdo, $categoryId) {
    $stmt = $pdo->prepare("SELECT * FROM category WHERE category_id = ?");
    $stmt->execute([$categoryId]);
    return $stmt->fetch();
}

// ============================================
// BRAND FUNCTIONS
// ============================================

function getAllBrands($pdo) {
    $stmt = $pdo->query("SELECT * FROM brand ORDER BY brand_name");
    return $stmt->fetchAll();
}

function getBrandById($pdo, $brandId) {
    $stmt = $pdo->prepare("SELECT * FROM brand WHERE brand_id = ?");
    $stmt->execute([$brandId]);
    return $stmt->fetch();
}

// ============================================
// SHIPMENT FUNCTIONS
// ============================================

function createShipment($pdo, $orderId, $courierId, $trackingNumber, $estimateDelivery = null) {
    $stmt = $pdo->prepare("
        INSERT INTO shipment (order_id, courier_id, tracking_number, estimate_delivery)
        VALUES (?, ?, ?, ?)
    ");
    return $stmt->execute([$orderId, $courierId, $trackingNumber, $estimateDelivery]);
}

function getShipmentByOrder($pdo, $orderId) {
    $stmt = $pdo->prepare("
        SELECT s.*, c.company_name, c.contact_number
        FROM shipment s
        LEFT JOIN courier c ON s.courier_id = c.courier_id
        WHERE s.order_id = ?
    ");
    $stmt->execute([$orderId]);
    return $stmt->fetch();
}

function updateShipmentStatus($pdo, $shipmentId, $status) {
    $stmt = $pdo->prepare("UPDATE shipment SET delivery_status = ? WHERE shipment_id = ?");
    return $stmt->execute([$status, $shipmentId]);
}

// ============================================
// STATISTICS & ANALYTICS FUNCTIONS
// ============================================

function getDashboardStats($pdo) {
    $stats = [];
    
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'");
    $stats['total_customers'] = $stmt->fetchColumn();
    
    // Total orders
    $stmt = $pdo->query("SELECT COUNT(*) FROM `order`");
    $stats['total_orders'] = $stmt->fetchColumn();
    
    // Total revenue
    $stmt = $pdo->query("SELECT SUM(total_amount) FROM `order` WHERE order_status = 'delivered'");
    $stats['total_revenue'] = $stmt->fetchColumn() ?? 0;
    
    // Pending orders
    $stmt = $pdo->query("SELECT COUNT(*) FROM `order` WHERE order_status = 'pending'");
    $stats['pending_orders'] = $stmt->fetchColumn();
    
    // Total products
    $stmt = $pdo->query("SELECT COUNT(*) FROM product");
    $stats['total_products'] = $stmt->fetchColumn();
    
    return $stats;
}

function getSalesData($pdo, $days = 30) {
    $stmt = $pdo->prepare("
        SELECT 
            DATE(order_date) as date,
            COUNT(*) as orders,
            SUM(total_amount) as revenue
        FROM `order`
        WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        GROUP BY DATE(order_date)
        ORDER BY date DESC
    ");
    $stmt->execute([$days]);
    return $stmt->fetchAll();
}

function getTopProducts($pdo, $limit = 10) {
    $stmt = $pdo->prepare("
        SELECT 
            p.product_id,
            p.product_name,
            p.price,
            COUNT(oi.order_item_id) as total_sold,
            SUM(oi.quantity) as total_quantity,
            SUM(oi.total_price) as total_revenue
        FROM product p
        LEFT JOIN order_item oi ON p.product_id = oi.product_id
        LEFT JOIN `order` o ON oi.order_id = o.order_id
        WHERE o.order_status = 'delivered' OR o.order_status IS NULL
        GROUP BY p.product_id
        ORDER BY total_quantity DESC, total_revenue DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}
?>