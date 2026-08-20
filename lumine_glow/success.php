<?php
session_start();
require_once 'includes/functions.php';

$order = (int)($_GET['order'] ?? 0);
if ($order <= 0) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmed | Luminé Glow</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<section class="success section">
    <div class="success-icon">✓</div>
    <p class="eyebrow">ORDER CONFIRMED</p>
    <h1>Thank you for your order!</h1>
    <p>Your order #<?= $order ?> has been received.</p>
    <p class="order-details">We'll send you a confirmation email shortly.</p>
    <a href="products.php" class="btn">Continue Shopping</a>
</section>
<?php include 'includes/footer.php'; ?>
</body>
</html>
