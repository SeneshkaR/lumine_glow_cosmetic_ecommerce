<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/functions.php';
$cartCount = getCartCount();
$flash = displayFlashMessage();
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="site-header">
    <a class="logo" href="index.php">Luminé <span>Glow</span></a>
    <nav>
        <a href="index.php">Home</a>
        <a href="products.php">Shop</a>
        <a href="quiz.php">Beauty Quiz</a>
        <?php if (isLoggedIn()): ?>
            <span class="welcome">Hi, <?= sanitize($_SESSION['user_name']) ?></span>
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
        <?php endif; ?>
        <a class="bag" href="cart.php">Bag <span><?= $cartCount ?></span></a>
    </nav>
    <button class="menu-toggle" aria-label="Toggle menu">☰</button>
</header>
<?php if ($flash): ?>
    <?= $flash ?>
<?php endif; ?>