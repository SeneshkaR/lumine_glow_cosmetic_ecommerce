<?php
/**
 * Luminé Glow - Core Functions
 * Complete implementation based on project proposal
 */

// ============================================
// SECURITY FUNCTIONS
// ============================================

function sanitize($input) {
    // Handle null values
    if ($input === null) {
        return '';
    }
    
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    
    // Convert to string if needed
    $string = (string)$input;
    return htmlspecialchars(trim($string), ENT_QUOTES, 'UTF-8');
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

function getUserByEmail($pdo, $email) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
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
    
    $stmt = $pdo->prepare("
        SELECT p.*, c.category_name 
        FROM product p
        LEFT JOIN category c ON p.category_id = c.category_id
        WHERE p.product_id IN ($placeholders)
    ");
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
        SELECT o.*, u.name as customer_name
        FROM `order` o
        LEFT JOIN users u ON o.user_id = u.user_id
        WHERE o.user_id = ?
        ORDER BY o.order_date DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getOrderDetails($pdo, $orderId) {
    $stmt = $pdo->prepare("
        SELECT o.*, u.name as customer_name, u.email as customer_email
        FROM `order` o
        LEFT JOIN users u ON o.user_id = u.user_id
        WHERE o.order_id = ?
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
    $stmt = $pdo->prepare("
        SELECT p.*, c.category_name, b.brand_name
        FROM product p
        LEFT JOIN category c ON p.category_id = c.category_id
        LEFT JOIN brand b ON p.brand_id = b.brand_id
        WHERE p.product_id = ?
    ");
    $stmt->execute([$productId]);
    return $stmt->fetch();
}

function getAllProducts($pdo, $limit = null) {
    $sql = "
        SELECT p.*, c.category_name, b.brand_name
        FROM product p
        LEFT JOIN category c ON p.category_id = c.category_id
        LEFT JOIN brand b ON p.brand_id = b.brand_id
        ORDER BY p.product_id DESC
    ";
    if ($limit) {
        $sql .= " LIMIT " . intval($limit);
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getProductsByCategory($pdo, $categoryId, $limit = null) {
    $sql = "
        SELECT p.*, c.category_name, b.brand_name
        FROM product p
        LEFT JOIN category c ON p.category_id = c.category_id
        LEFT JOIN brand b ON p.brand_id = b.brand_id
        WHERE p.category_id = ? 
        ORDER BY p.product_id DESC
    ";
    if ($limit) {
        $sql .= " LIMIT " . intval($limit);
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$categoryId]);
    return $stmt->fetchAll();
}

function getProductsByCategoryName($pdo, $categoryName) {
    $stmt = $pdo->prepare("
        SELECT p.*, c.category_name, b.brand_name
        FROM product p
        LEFT JOIN category c ON p.category_id = c.category_id
        LEFT JOIN brand b ON p.brand_id = b.brand_id
        WHERE c.category_name = ?
        ORDER BY p.product_id DESC
    ");
    $stmt->execute([$categoryName]);
    return $stmt->fetchAll();
}

function getProductsBySkinType($pdo, $skinType) {
    $stmt = $pdo->prepare("
        SELECT p.*, c.category_name, b.brand_name
        FROM product p
        LEFT JOIN category c ON p.category_id = c.category_id
        LEFT JOIN brand b ON p.brand_id = b.brand_id
        WHERE p.skin_type = ? OR p.skin_type = 'all'
        ORDER BY p.product_id DESC
    ");
    $stmt->execute([$skinType]);
    return $stmt->fetchAll();
}

function getProductsBySkinTone($pdo, $skinTone) {
    $stmt = $pdo->prepare("
        SELECT p.*, c.category_name, b.brand_name
        FROM product p
        LEFT JOIN category c ON p.category_id = c.category_id
        LEFT JOIN brand b ON p.brand_id = b.brand_id
        WHERE p.skin_tone = ? OR p.skin_tone = 'all'
        ORDER BY p.product_id DESC
    ");
    $stmt->execute([$skinTone]);
    return $stmt->fetchAll();
}

function getFeaturedProducts($pdo, $limit = 8) {
    $stmt = $pdo->prepare("
        SELECT p.*, c.category_name, b.brand_name
        FROM product p
        LEFT JOIN category c ON p.category_id = c.category_id
        LEFT JOIN brand b ON p.brand_id = b.brand_id
        WHERE p.is_featured = 1 
        ORDER BY p.product_id DESC 
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function getBestsellers($pdo, $limit = 8) {
    $stmt = $pdo->prepare("
        SELECT p.*, c.category_name, b.brand_name
        FROM product p
        LEFT JOIN category c ON p.category_id = c.category_id
        LEFT JOIN brand b ON p.brand_id = b.brand_id
        WHERE p.is_bestseller = 1 
        ORDER BY p.product_id DESC 
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function searchProducts($pdo, $query) {
    $stmt = $pdo->prepare("
        SELECT p.*, c.category_name, b.brand_name
        FROM product p
        LEFT JOIN category c ON p.category_id = c.category_id
        LEFT JOIN brand b ON p.brand_id = b.brand_id
        WHERE p.product_name LIKE ? OR p.description LIKE ?
        ORDER BY p.product_id DESC
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
    $profile = getBeautyProfile($pdo, $userId);
    if (!$profile) {
        return getFeaturedProducts($pdo, 8);
    }
    
    $stmt = $pdo->prepare("
        SELECT p.*, c.category_name, b.brand_name
        FROM product p
        LEFT JOIN category c ON p.category_id = c.category_id
        LEFT JOIN brand b ON p.brand_id = b.brand_id
        WHERE (p.skin_type = ? OR p.skin_type = 'all')
        AND (p.skin_tone = ? OR p.skin_tone = 'all')
        AND (p.concern = ? OR p.concern = 'all')
        ORDER BY p.is_featured DESC, p.is_bestseller DESC
        LIMIT 8
    ");
    $stmt->execute([
        $profile['skin_type'] ?? 'all',
        $profile['skin_tone'] ?? 'all',
        $profile['concern'] ?? 'all'
    ]);
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
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM order_item oi
        LEFT JOIN `order` o ON oi.order_id = o.order_id
        WHERE o.user_id = ? AND oi.product_id = ? AND o.order_status = 'delivered'
    ");
    $stmt->execute([$userId, $productId]);
    return $stmt->fetchColumn() > 0;
}

function getProductAverageRating($pdo, $productId) {
    $stmt = $pdo->prepare("
        SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews
        FROM review
        WHERE product_id = ? AND status = 'approved'
    ");
    $stmt->execute([$productId]);
    return $stmt->fetch();
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

function getAllPromoCodes($pdo) {
    $stmt = $pdo->query("SELECT * FROM promo_code ORDER BY promo_id DESC");
    return $stmt->fetchAll();
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

function updateAddress($pdo, $addressId, $data) {
    $stmt = $pdo->prepare("
        UPDATE address 
        SET street = ?, city = ?, state = ?, postal_code = ?, country = ?, is_default = ?
        WHERE address_id = ?
    ");
    return $stmt->execute([
        $data['street'],
        $data['city'],
        $data['state'] ?? null,
        $data['postal_code'] ?? null,
        $data['country'] ?? 'Sri Lanka',
        $data['is_default'] ?? false,
        $addressId
    ]);
}

function deleteAddress($pdo, $addressId, $userId) {
    $stmt = $pdo->prepare("DELETE FROM address WHERE address_id = ? AND user_id = ?");
    return $stmt->execute([$addressId, $userId]);
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
        'refunded' => 'warning',
        'active' => 'success',
        'inactive' => 'secondary',
        'suspended' => 'danger',
        'customer' => 'primary',
        'admin' => 'danger',
        'editor' => 'info',
        'guest' => 'secondary'
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

function getCategoryByName($pdo, $categoryName) {
    $stmt = $pdo->prepare("SELECT * FROM category WHERE category_name = ?");
    $stmt->execute([$categoryName]);
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

function getAllCouriers($pdo) {
    $stmt = $pdo->query("SELECT * FROM courier WHERE is_active = 1");
    return $stmt->fetchAll();
}

// ============================================
// ADMIN / STATISTICS FUNCTIONS
// ============================================

function getDashboardStats($pdo) {
    $stats = [];
    
    // Total customers
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'");
    $stats['total_customers'] = $stmt->fetchColumn();
    
    // Total orders
    $stmt = $pdo->query("SELECT COUNT(*) FROM `order`");
    $stats['total_orders'] = $stmt->fetchColumn();
    
    // Total revenue
    $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM `order` WHERE order_status = 'delivered'");
    $stats['total_revenue'] = $stmt->fetchColumn();
    
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
            COALESCE(SUM(total_amount), 0) as revenue
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
            COALESCE(SUM(oi.quantity), 0) as total_quantity,
            COALESCE(SUM(oi.total_price), 0) as total_revenue
        FROM product p
        LEFT JOIN order_item oi ON p.product_id = oi.product_id
        LEFT JOIN `order` o ON oi.order_id = o.order_id AND o.order_status = 'delivered'
        GROUP BY p.product_id
        ORDER BY total_quantity DESC, total_revenue DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function getRecentOrders($pdo, $limit = 10) {
    $stmt = $pdo->prepare("
        SELECT o.*, u.name as customer_name, u.email as customer_email
        FROM `order` o
        LEFT JOIN users u ON o.user_id = u.user_id
        ORDER BY o.order_date DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function getUserRegistrations($pdo, $days = 7) {
    $stmt = $pdo->prepare("
        SELECT user_id, name, email, role, status, created_at
        FROM users
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        ORDER BY created_at DESC
    ");
    $stmt->execute([$days]);
    return $stmt->fetchAll();
}

function getCategorySales($pdo) {
    $stmt = $pdo->prepare("
        SELECT 
            c.category_name,
            COALESCE(SUM(oi.quantity), 0) as total_quantity,
            COALESCE(SUM(oi.total_price), 0) as total_sales
        FROM category c
        LEFT JOIN product p ON c.category_id = p.category_id
        LEFT JOIN order_item oi ON p.product_id = oi.product_id
        LEFT JOIN `order` o ON oi.order_id = o.order_id AND o.order_status = 'delivered'
        GROUP BY c.category_id
        ORDER BY total_sales DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

function getMonthlySales($pdo, $year = null) {
    if (!$year) {
        $year = date('Y');
    }
    $stmt = $pdo->prepare("
        SELECT 
            MONTH(order_date) as month,
            COUNT(*) as orders,
            COALESCE(SUM(total_amount), 0) as revenue
        FROM `order`
        WHERE YEAR(order_date) = ? AND order_status = 'delivered'
        GROUP BY MONTH(order_date)
        ORDER BY month ASC
    ");
    $stmt->execute([$year]);
    return $stmt->fetchAll();
}

function getOrderStatusDistribution($pdo) {
    $stmt = $pdo->query("
        SELECT 
            order_status,
            COUNT(*) as count
        FROM `order`
        GROUP BY order_status
    ");
    return $stmt->fetchAll();
}

// ============================================
// UTILITY FUNCTIONS
// ============================================

function generateOrderNumber() {
    return 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

function generateTrackingNumber() {
    return 'TRK-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

function getTimeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $difference = time() - $timestamp;
    
    if ($difference < 60) {
        return $difference . ' seconds ago';
    } elseif ($difference < 3600) {
        return round($difference / 60) . ' minutes ago';
    } elseif ($difference < 86400) {
        return round($difference / 3600) . ' hours ago';
    } elseif ($difference < 604800) {
        return round($difference / 86400) . ' days ago';
    } else {
        return formatDate($datetime);
    }
}

function getStatusColor($status) {
    $colors = [
        'pending' => '#ed6c02',
        'processing' => '#0288d1',
        'shipped' => '#2e7d32',
        'delivered' => '#1b5e20',
        'cancelled' => '#d32f2f',
        'approved' => '#2e7d32',
        'rejected' => '#d32f2f',
        'completed' => '#2e7d32',
        'failed' => '#d32f2f',
        'refunded' => '#ed6c02',
        'active' => '#2e7d32',
        'inactive' => '#616161',
        'suspended' => '#d32f2f'
    ];
    return $colors[$status] ?? '#616161';
}

function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

function getCurrencySymbol($currency = 'LKR') {
    $symbols = [
        'LKR' => 'Rs.',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£'
    ];
    return $symbols[$currency] ?? 'Rs.';
}

// ============================================
// ERROR LOGGING FUNCTIONS
// ============================================

function logError($message, $context = []) {
    $logEntry = date('Y-m-d H:i:s') . ' - ' . $message;
    if (!empty($context)) {
        $logEntry .= ' - ' . json_encode($context);
    }
    error_log($logEntry);
}

function logUserAction($pdo, $userId, $action, $details = null) {
    // This function would log user actions for audit purposes
    // You can create a user_activity_logs table for this
    $stmt = $pdo->prepare("
        INSERT INTO user_activity_logs (user_id, action, details, ip_address)
        VALUES (?, ?, ?, ?)
    ");
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return $stmt->execute([$userId, $action, $details, $ip]);
}

// ============================================
// SESSION MANAGEMENT FUNCTIONS
// ============================================

function regenerateSession() {
    session_regenerate_id(true);
}

function destroySession() {
    $_SESSION = array();
    session_destroy();
}

function setUserSession($user) {
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
}

function isAdmin() {
    return isLoggedIn() && $_SESSION['user_role'] === 'admin';
}

function isEditor() {
    return isLoggedIn() && ($_SESSION['user_role'] === 'editor' || $_SESSION['user_role'] === 'admin');
}

function isCustomer() {
    return isLoggedIn() && $_SESSION['user_role'] === 'customer';
}

// ============================================
// REDIRECT FUNCTIONS
// ============================================

function redirectTo($url) {
    header('Location: ' . $url);
    exit;
}

function redirectBack() {
    $referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    header('Location: ' . $referer);
    exit;
}

function redirectWithMessage($url, $type, $message) {
    setFlashMessage($type, $message);
    redirectTo($url);
}

// ============================================
// PRODUCT FILTER FUNCTIONS
// ============================================

function getFilteredProducts($pdo, $filters = []) {
    $sql = "
        SELECT p.*, c.category_name, b.brand_name
        FROM product p
        LEFT JOIN category c ON p.category_id = c.category_id
        LEFT JOIN brand b ON p.brand_id = b.brand_id
        WHERE 1=1
    ";
    $params = [];
    
    if (!empty($filters['category'])) {
        $sql .= " AND c.category_name = ?";
        $params[] = $filters['category'];
    }
    
    if (!empty($filters['brand'])) {
        $sql .= " AND b.brand_name = ?";
        $params[] = $filters['brand'];
    }
    
    if (!empty($filters['skin_type'])) {
        $sql .= " AND (p.skin_type = ? OR p.skin_type = 'all')";
        $params[] = $filters['skin_type'];
    }
    
    if (!empty($filters['skin_tone'])) {
        $sql .= " AND (p.skin_tone = ? OR p.skin_tone = 'all')";
        $params[] = $filters['skin_tone'];
    }
    
    if (!empty($filters['min_price'])) {
        $sql .= " AND p.price >= ?";
        $params[] = $filters['min_price'];
    }
    
    if (!empty($filters['max_price'])) {
        $sql .= " AND p.price <= ?";
        $params[] = $filters['max_price'];
    }
    
    if (!empty($filters['search'])) {
        $sql .= " AND (p.product_name LIKE ? OR p.description LIKE ?)";
        $params[] = "%{$filters['search']}%";
        $params[] = "%{$filters['search']}%";
    }
    
    $sql .= " ORDER BY p.product_id DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
?>