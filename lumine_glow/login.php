<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $role = $_SESSION['user_role'] ?? 'customer';
    if ($role === 'admin' || $role === 'editor') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = sanitize(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        // Check if email exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && passwordVerify($password, $user['password'])) {
            // Set session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            
            // Update last login
            $updateStmt = $pdo->prepare("UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE user_id = ?");
            $updateStmt->execute([$user['user_id']]);
            
            setFlashMessage('success', 'Welcome back, ' . $user['name'] . '!');
            
            // Redirect based on role
            if ($user['role'] === 'admin' || $user['role'] === 'editor') {
                header('Location: admin/dashboard.php');
            } else {
                header('Location: index.php');
            }
            exit;
        }
        $error = 'Invalid email or password. Please try again.';
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
    <style>
        /* Login Page Specific Styles */
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
        .auth-form input[type="text"] {
            width: 100%;
            padding: 12px 16px 12px 44px;
            border: 2px solid #eadfdb;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            background: #faf8f7;
            color: #2c2527;
        }

        .auth-form input[type="email"]:focus,
        .auth-form input[type="password"]:focus,
        .auth-form input[type="text"]:focus {
            outline: none;
            border-color: #b76d78;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(183, 109, 120, 0.1);
        }

        .auth-form input[type="email"]::placeholder,
        .auth-form input[type="password"]::placeholder,
        .auth-form input[type="text"]::placeholder {
            color: #b0a8aa;
        }

        .auth-form .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 16px 0 24px;
            font-size: 13px;
        }

        .auth-form .form-options label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 400;
            color: #766c6f;
            cursor: pointer;
            margin: 0;
        }

        .auth-form .form-options label input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #b76d78;
            cursor: pointer;
        }

        .auth-form .form-options a {
            color: #b76d78;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .auth-form .form-options a:hover {
            color: #a55f6b;
            text-decoration: underline;
        }

        .auth-form .btn-login {
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
        }

        .auth-form .btn-login:hover {
            background: #a55f6b;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(183, 109, 120, 0.3);
        }

        .auth-form .btn-login:active {
            transform: translateY(0);
        }

        .auth-form .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
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

        .auth-footer .divider {
            display: flex;
            align-items: center;
            gap: 16px;
            margin: 16px 0;
            color: #766c6f;
            font-size: 12px;
        }

        .auth-footer .divider::before,
        .auth-footer .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #eadfdb;
        }

        .auth-footer .social-login {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 12px;
        }

        .auth-footer .social-login a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border: 1px solid #eadfdb;
            border-radius: 50%;
            transition: all 0.3s ease;
            color: #766c6f;
            font-size: 18px;
            text-decoration: none;
        }

        .auth-footer .social-login a:hover {
            border-color: #b76d78;
            background: #faf8f7;
            color: #b76d78;
            transform: translateY(-2px);
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

        /* Responsive */
        @media (max-width: 520px) {
            .auth-card {
                padding: 32px 24px;
            }

            .auth-header h1 {
                font-size: 28px;
            }

            .auth-form input[type="email"],
            .auth-form input[type="password"],
            .auth-form input[type="text"] {
                padding: 10px 14px 10px 40px;
                font-size: 13px;
            }

            .auth-form .form-options {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }

            .auth-footer .social-login a {
                width: 38px;
                height: 38px;
                font-size: 15px;
            }
        }

        @media (max-width: 380px) {
            .auth-card {
                padding: 24px 16px;
            }

            .auth-header h1 {
                font-size: 24px;
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
                <div class="logo-icon">💄</div>
                <p class="eyebrow">WELCOME BACK</p>
                <h1>Log in</h1>
                <p>Access your beauty profile and orders</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <span class="alert-icon">⚠️</span>
                    <?= sanitize($error) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['flash'])): ?>
                <?= displayFlashMessage() ?>
            <?php endif; ?>

            <form method="post" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                
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
                            autocomplete="email"
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
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            minlength="6"
                        >
                    </div>
                </div>

                <div class="form-options">
                    <label>
                        <input type="checkbox" name="remember" id="remember">
                        Remember me
                    </label>
                    <a href="forgot-password.php">Forgot password?</a>
                </div>

                <button type="submit" class="btn-login">Log In</button>
            </form>

            <div class="auth-footer">
                <p>New here? <a href="register.php">Create an account</a></p>
                
                <div class="divider">or continue with</div>
                
                <div class="social-login">
                    <a href="#" title="Google">G</a>
                    <a href="#" title="Facebook">f</a>
                    <a href="#" title="Apple">🍎</a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
</body>
</html>