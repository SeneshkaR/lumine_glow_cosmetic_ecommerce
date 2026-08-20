<?php
require_once 'includes/functions.php';

$result = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $skin = $_POST['skin'] ?? '';
        $goal = $_POST['goal'] ?? '';

        if (!$skin || !$goal) {
            $error = 'Please answer both questions.';
        } else {
            if ($skin === 'dry') $recommendation = 'Hydrating Cleanser + Hyaluronic Serum + Rich Moisturizer';
            elseif ($skin === 'oily') $recommendation = 'Gentle Gel Cleanser + Niacinamide Serum + Lightweight Moisturizer';
            elseif ($skin === 'combination') $recommendation = 'Balancing Cleanser + Niacinamide Serum + Lightweight Moisturizer';
            else $recommendation = 'Gentle Cleanser + Vitamin C Serum + Daily Moisturizer';

            if ($goal === 'glow') $recommendation .= ' + Vitamin C Glow Booster';
            if ($goal === 'calm') $recommendation .= ' + Soothing Barrier Cream';

            $result = $recommendation;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beauty Quiz | Luminé Glow</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<section class="section quiz">
    <div class="quiz-intro">
        <p class="eyebrow">FIND YOUR ROUTINE</p>
        <h1>What's your skin telling you?</h1>
        <p>Answer two quick questions and we'll suggest a simple routine.</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= sanitize($error) ?></div>
    <?php endif; ?>

    <?php if ($result): ?>
        <div class="quiz-result">
            <p class="eyebrow">YOUR RECOMMENDATION</p>
            <h2><?= sanitize($result) ?></h2>
            <a class="btn" href="products.php">Shop Products</a>
            <a class="text-link" href="quiz.php">Retake quiz</a>
        </div>
    <?php else: ?>
    <form method="post" class="quiz-form">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        <h2>1. How does your skin usually feel?</h2>
        <label><input type="radio" name="skin" value="dry" required> Dry or tight</label>
        <label><input type="radio" name="skin" value="oily"> Oily or shiny</label>
        <label><input type="radio" name="skin" value="combination"> A mix of both</label>
        <label><input type="radio" name="skin" value="normal"> Mostly balanced</label>

        <h2>2. What's your main goal?</h2>
        <label><input type="radio" name="goal" value="glow" required> More glow</label>
        <label><input type="radio" name="goal" value="calm"> Calm & comfort</label>
        <label><input type="radio" name="goal" value="hydrate"> Hydration</label>

        <button class="btn" type="submit">Show My Routine</button>
    </form>
    <?php endif; ?>
</section>
<?php include 'includes/footer.php'; ?>