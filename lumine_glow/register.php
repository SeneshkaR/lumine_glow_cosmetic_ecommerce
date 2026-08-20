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
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $name = sanitize(trim($_POST['name'] ?? ''));
        $email = sanitize(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $phone = sanitize(trim($_POST['phone'] ?? ''));

        if (!$name) {
            $error = 'Please enter your full name.';
        } elseif (!validateEmail($email)) {
            $error = 'Please enter a valid email address.';
        } elseif (!validatePassword($password)) {
            $error = 'Password must be at least 6 characters long.';
        } elseif (!empty($phone) && !validatePhone($phone)) {
            $error = 'Please enter a valid phone number.';
        } else {
            try {
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                $checkStmt->execute([$email]);
                if ($checkStmt->fetchColumn() > 0) {
                    $error = 'This email is already registered. Please login.';
                } else {
                    $hashedPassword = passwordHash($password);
                    $stmt = $pdo->prepare("
                        INSERT INTO users (name, email, phone, password, role, status) 
                        VALUES (?, ?, ?, ?, 'customer', 'active')
                    ");
                    $stmt->execute([$name, $email, $phone, $hashedPassword]);
                    
                    setFlashMessage('success', 'Account created successfully! Please login.');
                    header('Location: login.php');
                    exit;
                }
            } catch (PDOException $e) {
                error_log('Registration failed: ' . $e->getMessage());
                $error = 'Unable to create account. Please try again.';
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
    <style>
        .auth-page {
            min-height: calc(100vh - 200px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            background: linear-gradient(135deg, #f3dfdd 0%, #fffaf6 100%);
        }

        .auth-container {
            width: 100%;
            max-width: 460px;
            margin: 0 auto;
        }

        .auth-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 48px 40px;
            box-shadow: 0 20px 60px rgba(44, 37, 39, 0.12);
            border: 1px solid rgba(183, 109, 120, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .auth-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 24px 70px rgba(44, 37, 39, 0.16);
        }

        .auth-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .auth-header .logo-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #f2d9d9, #b76d78);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 28px;
            color: #fff;
        }

        .auth-header .eyebrow {
            font-size: 12px;
            letter-spacing: 3px;
            font-weight: 700;
            color: #b76d78;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .auth-header h1 {
            font-size: 32px;
            font-weight: 600;
            color: #2c2527;
            margin: 0;
            font-family: "Playfair Display", serif;
        }

        .auth-header p {
            color: #766c6f;
            font-size: 14px;
            margin-top: 8px;
        }

        .auth-form .form-group {
            margin-bottom: 20px;
        }

        .auth-form label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #2c2527;
            margin-bottom: 6px;
        }

        .auth-form label .required {
            color: #d32f2f;
            margin-left: 2px;
        }

        .auth-form .input-wrapper {
            position: relative;
        }

        .auth-form .input-wrapper .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #766c6f;
            font-size: 16px;
        }

        .auth-form input[type="email"],
        .auth-form input[type="password"],
        .auth-form input[type="text"],
        .auth-form input[type="tel"] {
            width: 100%;
            padding: 12px 16px 12px 44px;
            border: 2px solid #eadfdb;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            background: #faf8f7;
            color: #2c2527;
        }

        .auth-form input:focus {
            outline: none;
            border-color: #b76d78;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(183, 109, 120, 0.1);
        }

        .auth-form input::placeholder {
            color: #b0a8aa;
        }

        .auth-form .btn-register {
            width: 100%;
            padding: 14px;
            background: #b76d78;
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
            margin-top: 8px;
        }

        .auth-form .btn-register:hover {
            background: #a55f6b;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(183, 109, 120, 0.3);
        }

        .auth-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #eadfdb;
        }

        .auth-footer p {
            color: #766c6f;
            font-size: 14px;
            margin: 0;
        }

        .auth-footer a {
            color: #b76d78;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .auth-footer a:hover {
            color: #a55f6b;
            text-decoration: underline;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: #fbe9e7;
            color: #d32f2f;
            border-left: 4px solid #d32f2f;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #2e7d32;
        }

        .alert .alert-icon {
            font-size: 18px;
        }

        @media (max-width: 520px) {
            .auth-card {
                padding: 32px 24px;
            }
            .auth-header h1 {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>

<section class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo-icon">✨</div>
                <p class="eyebrow">JOIN LUMINÉ GLOW</p>
                <h1>Create account</h1>
                <p>Start your beauty journey with us</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <span class="alert-icon">⚠️</span>
                    <?= sanitize($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                
                <div class="form-group">
                    <label for="name">Full Name <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <span class="input-icon">👤</span>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            value="<?= isset($_POST['name']) ? sanitize($_POST['name']) : '' ?>" 
                            required 
                            placeholder="John Doe"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email Address <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <span class="input-icon">📧</span>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            value="<?= isset($_POST['email']) ? sanitize($_POST['email']) : '' ?>" 
                            required 
                            placeholder="your@email.com"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number (optional)</label>
                    <div class="input-wrapper">
                        <span class="input-icon">📱</span>
                        <input 
                            type="tel" 
                            id="phone" 
                            name="phone" 
                            value="<?= isset($_POST['phone']) ? sanitize($_POST['phone']) : '' ?>" 
                            placeholder="+94 XX XXX XXXX"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <span class="input-icon">🔒</span>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required 
                            placeholder="Minimum 6 characters"
                            minlength="6"
                        >
                    </div>
                </div>

                <button type="submit" class="btn-register">Create Account</button>
            </form>

            <div class="auth-footer">
                <p>Already have an account? <a href="login.php">Log in</a></p>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
</body>
</html>