<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Invalid request. Please try again.');
        header('Location: cart.php');
        exit;
    }
    
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $id = (int)$_POST['product_id'];
        $qty = max(1, (int)$_POST['quantity']);
        
        $stockStmt = $pdo->prepare("SELECT stock FROM product WHERE product_id = ?");
        $stockStmt->execute([$id]);
        $stock = $stockStmt->fetchColumn();
        
        if ($stock === false) {
            setFlashMessage('error', 'Product not found.');
            header('Location: products.php');
            exit;
        }
        
        $currentQty = $_SESSION['cart'][$id] ?? 0;
        if ($currentQty + $qty > $stock) {
            setFlashMessage('error', 'Not enough stock available. Maximum: ' . $stock);
            header('Location: product.php?id=' . $id);
            exit;
        }
        
        $_SESSION['cart'][$id] = $currentQty + $qty;
        setFlashMessage('success', 'Item added to cart!');
        header('Location: cart.php');
        exit;
    }

    if ($action === 'update') {
        foreach ($_POST['qty'] as $id => $qty) {
            $id = (int)$id;
            $qty = (int)$qty;
            
            if ($qty <= 0) {
                unset($_SESSION['cart'][$id]);
            } else {
                $stockStmt = $pdo->prepare("SELECT stock FROM product WHERE product_id = ?");
                $stockStmt->execute([$id]);
                $stock = $stockStmt->fetchColumn();
                if ($stock !== false) {
                    $_SESSION['cart'][$id] = min($qty, $stock, 20);
                }
            }
        }
        setFlashMessage('success', 'Cart updated successfully!');
        header('Location: cart.php');
        exit;
    }

    if ($action === 'remove') {
        $id = (int)$_POST['product_id'];
        if (isset($_SESSION['cart'][$id])) {
            unset($_SESSION['cart'][$id]);
            setFlashMessage('success', 'Item removed from cart.');
        } else {
            setFlashMessage('error', 'Item not found in cart.');
        }
        header('Location: cart.php');
        exit;
    }
}

$items = [];
$total = 0;

if ($_SESSION['cart']) {
    $ids = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    // Use JOIN to get category name
    $stmt = $pdo->prepare("
        SELECT p.*, c.category_name 
        FROM product p
        LEFT JOIN category c ON p.category_id = c.category_id
        WHERE p.product_id IN ($placeholders)
    ");
    $stmt->execute($ids);
    $products = $stmt->fetchAll();

    foreach ($products as $product) {
        $id = $product['product_id'];
        $qty = $_SESSION['cart'][$id];
        $subtotal = $product['price'] * $qty;
        $product['qty'] = $qty;
        $product['subtotal'] = $subtotal;
        $product['cart_id'] = $id;
        $items[] = $product;
        $total += $subtotal;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Bag | Luminé Glow</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>

<section class="page-header compact">
    <p class="eyebrow">YOUR BAG</p>
    <h1>Shopping bag</h1>
</section>

<section class="section cart-section">
<?php if (!$items): ?>
    <div class="empty-state">
        <h2>Your bag is empty.</h2>
        <p>Looks like your glow-up is waiting.</p>
        <a href="products.php" class="btn">Continue Shopping</a>
    </div>
<?php else: ?>
<form method="post" id="cart-form">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
    <div class="cart-list">
    <?php foreach ($items as $item): ?>
        <?php $itemId = $item['cart_id']; ?>
        <div class="cart-item" id="cart-item-<?= $itemId ?>">
            <div class="cart-thumb"><?= sanitize($item['category_name'] ?? 'Product') ?></div>
            <div class="cart-name">
                <h3><?= sanitize($item['product_name']) ?></h3>
                <p><?= formatPrice($item['price']) ?></p>
            </div>
            <input class="quantity" type="number" name="qty[<?= $itemId ?>]" value="<?= $item['qty'] ?>" min="1" max="<?= min(20, $item['stock']) ?>">
            <strong><?= formatPrice($item['subtotal']) ?></strong>
            <!-- FIXED: Remove button using separate form or JavaScript -->
            <button type="button" class="remove-btn" onclick="removeItem(<?= $itemId ?>)">×</button>
        </div>
    <?php endforeach; ?>
    </div>
    <div class="cart-summary">
        <div><span>Subtotal</span><strong><?= formatPrice($total) ?></strong></div>
        <div><span>Delivery</span><span>Calculated at checkout</span></div>
        <div class="total"><span>Total</span><strong><?= formatPrice($total) ?></strong></div>
        <button class="btn" type="submit" name="action" value="update">Update Bag</button>
        <a href="checkout.php" class="btn btn-dark">Checkout</a>
    </div>
</form>

<!-- Separate form for remove action -->
<form method="post" id="remove-form" style="display: none;">
    <input type="hidden" name="action" value="remove">
    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
    <input type="hidden" name="product_id" id="remove-product-id" value="">
</form>

<?php endif; ?>
</section>

<script>
function removeItem(productId) {
    if (confirm('Are you sure you want to remove this item from your cart?')) {
        document.getElementById('remove-product-id').value = productId;
        document.getElementById('remove-form').submit();
    }
}
</script>

<?php include 'includes/footer.php'; ?>