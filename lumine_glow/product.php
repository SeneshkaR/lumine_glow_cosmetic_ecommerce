<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: products.php');
    exit;
}

$product = getProductById($pdo, $id);

if (!$product) {
    http_response_code(404);
    exit('<h1>Product not found</h1><p><a href="products.php">Return to shop</a></p>');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($product['name']) ?> | Luminé Glow</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>

<section class="product-detail section">
    <div class="detail-image">
        <div class="product-placeholder large"><?= sanitize($product['category']) ?></div>
    </div>
    <div class="detail-content">
        <p class="eyebrow"><?= sanitize($product['category']) ?></p>
        <h1><?= sanitize($product['name']) ?></h1>
        <p class="detail-price"><?= formatPrice($product['price']) ?></p>
        <p class="description"><?= sanitize($product['description']) ?></p>
        <p class="stock-info <?= $product['stock'] <= 0 ? 'out-of-stock' : '' ?>">
            <?= $product['stock'] > 0 ? '✓ In Stock' : '✗ Out of Stock' ?>
        </p>

        <?php if ($product['category'] === 'Makeup' || $product['category'] === 'Lips'): ?>
        <label class="label">Choose shade</label>
        <select id="shade" class="select">
            <option>Rose Nude</option>
            <option>Soft Peach</option>
            <option>Warm Beige</option>
            <option>Deep Berry</option>
        </select>
        <?php endif; ?>

        <form action="cart.php" method="post" class="add-form">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <label class="label">Quantity</label>
            <input class="quantity" type="number" name="quantity" value="1" min="1" max="<?= min(10, $product['stock']) ?>" <?= $product['stock'] <= 0 ? 'disabled' : '' ?>>
            <button class="btn" type="submit" <?= $product['stock'] <= 0 ? 'disabled' : '' ?>>
                <?= $product['stock'] > 0 ? 'Add to Bag' : 'Out of Stock' ?>
            </button>
        </form>

        <div class="feature-list">
            <p>✓ Cruelty-free</p>
            <p>✓ Carefully selected ingredients</p>
            <p>✓ Secure checkout</p>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>