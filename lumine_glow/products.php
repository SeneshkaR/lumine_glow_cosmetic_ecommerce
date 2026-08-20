<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

$category = $_GET['category'] ?? '';
$search = trim($_GET['search'] ?? '');

$sql = "SELECT * FROM products WHERE 1=1";
$params = [];

if ($category !== '') {
    $sql .= " AND category = ?";
    $params[] = $category;
}
if ($search !== '') {
    $sql .= " AND (name LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql .= " ORDER BY id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop | Luminé Glow</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>

<section class="page-header">
    <p class="eyebrow">LUMINÉ GLOW</p>
    <h1>Shop beauty</h1>
    <p>Find your new everyday favourites.</p>
</section>

<section class="section">
    <div class="shop-toolbar">
        <div class="filters">
            <a class="<?= $category==='' ? 'active' : '' ?>" href="products.php">All</a>
            <a class="<?= $category==='Skincare' ? 'active' : '' ?>" href="products.php?category=Skincare">Skincare</a>
            <a class="<?= $category==='Makeup' ? 'active' : '' ?>" href="products.php?category=Makeup">Makeup</a>
            <a class="<?= $category==='Lips' ? 'active' : '' ?>" href="products.php?category=Lips">Lips</a>
            <a class="<?= $category==='Fragrance' ? 'active' : '' ?>" href="products.php?category=Fragrance">Fragrance</a>
        </div>
        <form class="search-form" method="get">
            <input type="text" name="search" placeholder="Search products..." value="<?= sanitize($search) ?>">
            <button class="btn small" type="submit">Search</button>
        </form>
    </div>

    <?php if (!$products): ?>
        <div class="empty-state"><h2>No products found</h2><p>Try another search or category.</p></div>
    <?php else: ?>
    <div class="product-grid">
        <?php foreach ($products as $product): ?>
        <article class="product-card">
            <div class="product-image"><div class="product-placeholder"><?= sanitize($product['category']) ?></div></div>
            <div class="product-info">
                <p class="product-category"><?= sanitize($product['category']) ?></p>
                <h3><?= sanitize($product['name']) ?></h3>
                <p class="price"><?= formatPrice($product['price']) ?></p>
                <a href="product.php?id=<?= $product['id'] ?>" class="text-link">View product →</a>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>