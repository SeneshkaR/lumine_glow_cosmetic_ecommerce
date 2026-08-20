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
        $name = sanitize(trim($_POST['name'] ?? ''));
        $email = sanitize(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        if (!$name || !validateEmail($email)) {
            $error = 'Please enter a valid name and email address.';
        } elseif (!validatePassword($password)) {
            $error = 'Password must be at least 6 characters long.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
                $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                setFlashMessage('success', 'Account created successfully! Please login.');
                header('Location: login.php');
                exit;
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
                    $error = 'This email is already registered. Please login.';
                } else {
                    $error = 'Unable to create account. Please try again.';
                    error_log('Registration failed: ' . $e->getMessage());
                }
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
    <title>Create Account | Luminé Glow</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<section class="auth section">
    <div class="auth-card">
        <p class="eyebrow">JOIN LUMINÉ GLOW</p>
        <h1>Create account</h1>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= sanitize($error) ?></div>
        <?php endif; ?>
        <form method="post" class="checkout-form">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <label>Name <input type="text" name="name" value="<?= isset($_POST['name']) ? sanitize($_POST['name']) : '' ?>" required></label>
            <label>Email <input type="email" name="email" value="<?= isset($_POST['email']) ? sanitize($_POST['email']) : '' ?>" required></label>
            <label>Password <input type="password" name="password" minlength="6" required></label>
            <button class="btn" type="submit">Create Account</button>
        </form>
        <p>Already have an account? <a href="login.php">Log in</a></p>
    </div>
</section>
<?php include 'includes/footer.php'; ?>