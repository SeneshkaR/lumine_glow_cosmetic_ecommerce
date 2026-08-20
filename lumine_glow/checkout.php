<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if cart is empty
if (empty($_SESSION['cart'])) {
    setFlashMessage('error', 'Your cart is empty. Please add items before checkout.');
    header('Location: cart.php');
    exit;
}

// Get cart items - FIXED: using 'product' table with 'product_id'
$ids = array_keys($_SESSION['cart']);
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT * FROM product WHERE product_id IN ($placeholders)");
$stmt->execute($ids);
$products = $stmt->fetchAll();

$total = 0;
foreach ($products as $p) {
    $total += $p['price'] * $_SESSION['cart'][$p['product_id']];
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $name = sanitize(trim($_POST['name'] ?? ''));
        $email = sanitize(trim($_POST['email'] ?? ''));
        $address = sanitize(trim($_POST['address'] ?? ''));
        $city = sanitize(trim($_POST['city'] ?? ''));
        $phone = sanitize(trim($_POST['phone'] ?? ''));

        // Validate
        if (!$name || !$email || !$address || !$city) {
            $error = 'Please fill in all required fields.';
        } elseif (!validateEmail($email)) {
            $error = 'Please enter a valid email address.';
        } elseif (!empty($phone) && !validatePhone($phone)) {
            $error = 'Please enter a valid phone number.';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Generate order number
                $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
                
                // Create order - FIXED: using 'order' table with backticks
                $stmt = $pdo->prepare("
                    INSERT INTO `order` (user_id, address_id, order_number, total_amount, subtotal, order_status) 
                    VALUES (?, NULL, ?, ?, ?, 'pending')
                ");
                $stmt->execute([$_SESSION['user_id'] ?? 1, $orderNumber, $total, $total]);
                $orderId = $pdo->lastInsertId();

                // Add order items and update stock
                $itemStmt = $pdo->prepare("
                    INSERT INTO order_item (order_id, product_id, quantity, unit_price, total_price) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stockStmt = $pdo->prepare("UPDATE product SET stock = stock - ? WHERE product_id = ? AND stock >= ?");
                
                foreach ($products as $p) {
                    $qty = $_SESSION['cart'][$p['product_id']];
                    $totalPrice = $p['price'] * $qty;
                    $itemStmt->execute([$orderId, $p['product_id'], $qty, $p['price'], $totalPrice]);
                    
                    // Update stock
                    $stockStmt->execute([$qty, $p['product_id'], $qty]);
                    if ($stockStmt->rowCount() === 0) {
                        throw new Exception("Not enough stock for product: " . $p['product_name']);
                    }
                }

                // Add order status history
                $historyStmt = $pdo->prepare("
                    INSERT INTO order_status_history (order_id, status_from, status_to, changed_by)
                    VALUES (?, NULL, 'pending', ?)
                ");
                $historyStmt->execute([$orderId, $_SESSION['user_id'] ?? 1]);

                $pdo->commit();
                
                // Clear cart
                $_SESSION['cart'] = [];
                setFlashMessage('success', 'Order placed successfully!');
                header("Location: success.php?order=" . $orderId);
                exit;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Unable to place order. ' . $e->getMessage();
                error_log('Order failed: ' . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | Luminé Glow</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>

<section class="section checkout">
    <div>
        <p class="eyebrow">CHECKOUT</p>
        <h1>Complete your order</h1>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= sanitize($error) ?></div>
        <?php endif; ?>

        <form method="post" class="checkout-form">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <label>Full name <span class="required">*</span>
                <input type="text" name="name" value="<?= isset($_POST['name']) ? sanitize($_POST['name']) : (isset($_SESSION['user_name']) ? sanitize($_SESSION['user_name']) : '') ?>" required>
            </label>
            <label>Email <span class="required">*</span>
                <input type="email" name="email" value="<?= isset($_POST['email']) ? sanitize($_POST['email']) : '' ?>" required>
            </label>
            <label>Phone (optional)
                <input type="tel" name="phone" value="<?= isset($_POST['phone']) ? sanitize($_POST['phone']) : '' ?>" placeholder="+94 XX XXX XXXX">
            </label>
            <label>Delivery address <span class="required">*</span>
                <textarea name="address" rows="4" required><?= isset($_POST['address']) ? sanitize($_POST['address']) : '' ?></textarea>
            </label>
            <label>City <span class="required">*</span>
                <input type="text" name="city" value="<?= isset($_POST['city']) ? sanitize($_POST['city']) : '' ?>" required>
            </label>
            <label>Payment method
                <select name="payment">
                    <option value="cod">Cash on Delivery</option>
                    <option value="card">Card Payment (Demo)</option>
                </select>
            </label>
            <button class="btn" type="submit">Place Order</button>
        </form>
    </div>

    <aside class="checkout-summary">
        <h2>Order summary</h2>
        <?php foreach ($products as $p): ?>
            <div class="summary-row">
                <span class="item-name"><?= sanitize($p['product_name']) ?> × <?= $_SESSION['cart'][$p['product_id']] ?></span>
                <span class="item-price"><?= formatPrice($p['price'] * $_SESSION['cart'][$p['product_id']]) ?></span>
            </div>
        <?php endforeach; ?>
        <div class="summary-total">
            <span>Total</span>
            <strong><?= formatPrice($total) ?></strong>
        </div>
        <p style="margin-top: 16px; font-size: 13px; color: var(--muted);">
            <small>Your payment is secure. No card details are stored.</small>
        </p>
    </aside>
</section>

<?php include 'includes/footer.php'; ?>