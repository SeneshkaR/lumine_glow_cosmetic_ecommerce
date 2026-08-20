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
        
        // Check stock
        $stock = getProductStock($pdo, $id);
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
                $stock = getProductStock($pdo, $id);
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
        unset($_SESSION['cart'][$id]);
        setFlashMessage('success', 'Item removed from cart.');
        header('Location: cart.php');
        exit;
    }
}

$items = [];
$total = 0;

if ($_SESSION['cart']) {
    $ids = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
    $stmt->execute($ids);

    foreach ($stmt->fetchAll() as $product) {
        $qty = $_SESSION['cart'][$product['id']];
        $subtotal = $product['price'] * $qty;
        $product['qty'] = $qty;
        $product['subtotal'] = $subtotal;
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
<form method="post">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
    <div class="cart-list">
    <?php foreach ($items as $item): ?>
        <div class="cart-item">
            <div class="cart-thumb"><?= sanitize($item['category']) ?></div>
            <div class="cart-name">
                <h3><?= sanitize($item['name']) ?></h3>
                <p><?= formatPrice($item['price']) ?></p>
            </div>
            <input class="quantity" type="number" name="qty[<?= $item['id'] ?>]" value="<?= $item['qty'] ?>" min="1" max="<?= min(20, $item['stock']) ?>">
            <strong><?= formatPrice($item['subtotal']) ?></strong>
            <button class="remove-btn" type="submit" formaction="cart.php" name="action" value="remove" onclick="document.getElementById('product_id_<?= $item['id'] ?>').value='<?= $item['id'] ?>'">×</button>
            <input type="hidden" id="product_id_<?= $item['id'] ?>" name="product_id" value="">
        </div>
    <?php endforeach; ?>
    </div>
    <div class="cart-summary">
        <div><span>Subtotal</span><strong><?= formatPrice($total) ?></strong></div>
        <div><span>Delivery</span><span>Calculated at checkout</span></div>
        <div class="total"><span>Total</span><strong><?= formatPrice($total) ?></strong></div>
        <button class="btn" type="submit">Update Bag</button>
        <a href="checkout.php" class="btn btn-dark">Checkout</a>
    </div>
</form>
<?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>