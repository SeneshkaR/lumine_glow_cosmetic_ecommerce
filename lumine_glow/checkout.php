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

// Get cart items
$ids = array_keys($_SESSION['cart']);
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
$stmt->execute($ids);
$products = $stmt->fetchAll();

$total = 0;
foreach ($products as $p) {
    $total += $p['price'] * $_SESSION['cart'][$p['id']];
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
                
                // Create order
                $stmt = $pdo->prepare("INSERT INTO orders (customer_name, email, address, city, total_amount, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
                $stmt->execute([$name, $email, $address, $city, $total]);
                $orderId = $pdo->lastInsertId();

                // Add order items and update stock
                $itemStmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
                $stockStmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
                
                foreach ($products as $p) {
                    $qty = $_SESSION['cart'][$p['id']];
                    $itemStmt->execute([$orderId, $p['id'], $qty, $p['price']]);
                    
                    // Update stock
                    $stockStmt->execute([$qty, $p['id'], $qty]);
                    if ($stockStmt->rowCount() === 0) {
                        throw new Exception("Not enough stock for product: " . $p['name']);
                    }
                }

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
                <input type="text" name="name" value="<?= isset($_POST['name']) ? sanitize($_POST['name']) : '' ?>" required>
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
                <span><?= sanitize($p['name']) ?> × <?= $_SESSION['cart'][$p['id']] ?></span>
                <strong><?= formatPrice($p['price'] * $_SESSION['cart'][$p['id']]) ?></strong>
            </div>
        <?php endforeach; ?>
        <div class="summary-total">
            <span>Total</span>
            <strong><?= formatPrice($total) ?></strong>
        </div>
    </aside>
</section>

<?php include 'includes/footer.php'; ?>