<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = sanitize(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            setFlashMessage('success', 'Welcome back, ' . $user['name'] . '!');
            header('Location: index.php');
            exit;
        }
        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Luminé Glow</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<section class="auth section">
    <div class="auth-card">
        <p class="eyebrow">WELCOME BACK</p>
        <h1>Log in</h1>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= sanitize($error) ?></div>
        <?php endif; ?>
        <form method="post" class="checkout-form">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <label>Email <input type="email" name="email" value="<?= isset($_POST['email']) ? sanitize($_POST['email']) : '' ?>" required></label>
            <label>Password <input type="password" name="password" required></label>
            <button class="btn" type="submit">Log In</button>
        </form>
        <p>New here? <a href="register.php">Create an account</a></p>
    </div>
</section>
<?php include 'includes/footer.php'; ?>