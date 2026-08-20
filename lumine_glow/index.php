<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

$products = [];
$error = null;

try {
    // Check if products table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'product'");
    if ($stmt->rowCount() == 0) {
        $error = 'Products table does not exist. Please run the SQL file in MySQL Workbench.';
    } else {
        // Check if products exist
        $stmt = $pdo->query("SELECT COUNT(*) FROM product");
        $count = $stmt->fetchColumn();
        
        if ($count == 0) {
            $error = 'No products found. Please insert sample data from the SQL file.';
        } else {
            // Get featured products
            $stmt = $pdo->query("SELECT * FROM vw_product_details ORDER BY product_id DESC LIMIT 8");
            $products = $stmt->fetchAll();
            
            if (empty($products)) {
                // Fallback: try direct query without view
                $stmt = $pdo->query("SELECT * FROM product ORDER BY product_id DESC LIMIT 8");
                $products = $stmt->fetchAll();
            }
        }
    }
} catch (PDOException $e) {
    error_log('Error fetching products: ' . $e->getMessage());
    $error = 'Unable to load products. Please check database connection. Error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Luminé Glow | Beauty & Cosmetics</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>

<?php if ($error): ?>
    <div class="alert alert-error" style="margin: 20px 7%; padding: 20px; background: #fbe9e7; border-radius: 5px;">
        <h3>⚠️ Database Error</h3>
        <p><?= sanitize($error) ?></p>
        <p style="margin-top: 10px;">
            <a href="test_db.php" class="btn btn-small">Run Database Test</a>
        </p>
    </div>
<?php endif; ?>

<section class="hero">
    <div class="hero-content">
        <p class="eyebrow">BEAUTY, SIMPLIFIED</p>
        <h1>Glow in your<br><em>own shade.</em></h1>
        <p>Discover skincare and makeup selected to help you feel confident in your natural beauty.</p>
        <div class="hero-buttons">
            <a href="products.php" class="btn">Shop Now</a>
            <a href="quiz.php" class="btn btn-outline">Find My Routine</a>
        </div>
    </div>
</section>

<section class="section">
    <div class="section-heading">
        <div>
            <p class="eyebrow">EXPLORE</p>
            <h2>Shop by category</h2>
        </div>
        <a href="products.php">View all →</a>
    </div>
    <div class="category-grid">
        <a href="products.php?category=Skincare" class="category-card"><span>01</span><h3>Skincare</h3><p>Cleanse, hydrate & protect</p></a>
        <a href="products.php?category=Makeup" class="category-card"><span>02</span><h3>Makeup</h3><p>Everyday looks & essentials</p></a>
        <a href="products.php?category=Lips" class="category-card"><span>03</span><h3>Lips</h3><p>Colour made for you</p></a>
        <a href="products.php?category=Fragrance" class="category-card"><span>04</span><h3>Fragrance</h3><p>Find your signature scent</p></a>
    </div>
</section>

<section class="section soft">
    <div class="section-heading">
        <div>
            <p class="eyebrow">BESTSELLERS</p>
            <h2>Glow favourites</h2>
        </div>
        <a href="products.php">Shop all →</a>
    </div>
    <?php if (empty($products) && !$error): ?>
        <div class="empty-state">
            <h2>No products available</h2>
            <p>Please import the sample products from the SQL file using MySQL Workbench.</p>
            <a href="test_db.php" class="btn">Check Database</a>
        </div>
    <?php elseif (!empty($products)): ?>
    <div class="product-grid">
        <?php foreach ($products as $product): ?>
            <article class="product-card">
                <div class="product-image">
                    <div class="product-placeholder"><?= sanitize($product['category_name'] ?? $product['category'] ?? 'Product') ?></div>
                </div>
                <div class="product-info">
                    <p class="product-category"><?= sanitize($product['category_name'] ?? $product['category'] ?? '') ?></p>
                    <h3><?= sanitize($product['product_name'] ?? $product['name'] ?? '') ?></h3>
                    <p class="price"><?= formatPrice($product['price'] ?? 0) ?></p>
                    <a href="product.php?id=<?= $product['product_id'] ?? $product['id'] ?? 0 ?>" class="text-link">View product →</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<section class="quiz-banner">
    <div>
        <p class="eyebrow">NOT SURE WHAT YOU NEED?</p>
        <h2>Let us help you build your beauty routine.</h2>
    </div>
    <a href="quiz.php" class="btn">Take the Beauty Quiz</a>
</section>

<?php include 'includes/footer.php'; ?>