<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectByRole($_SESSION['user_role'] ?? 'user');
}

$error = '';
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim(requestString($_POST['email'] ?? null));
    $password = requestString($_POST['password'] ?? null);
    $csrfToken = requestString($_POST['csrf_token'] ?? null);

    if (!isValidCsrfToken($csrfToken)) {
        $error = 'Your session expired. Please refresh the page and try again.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Please enter both your email and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if (!canAuthenticateUser($user)) {
                if ($user['status'] === 'suspended') {
                $error = 'Your account has been suspended. Please contact the administrator.';
                } else {
                    $error = 'Your account is not active. Please contact the administrator.';
                }
            } else {
                loginUser($user);
                setFlashMessage('success', "Welcome back, " . $user['name'] . "!");
                redirectByRole($user['role']);
            }
        } else {
            $error = 'Invalid email address or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Track - Sign In</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="split-layout">
        <!-- Left Branding Panel -->
        <div class="left-panel">
            <div class="brand-content">
                <div class="logo-container">
                    <svg class="heart-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                        <path d="M12 8v6M9 11h6" stroke-linecap="round"/>
                    </svg>
                    <h1 class="logo-text">Health Track</h1>
                </div>
                <p class="subtitle">Smart Health & Diet Recommendation System</p>
                <div class="stats-container">
                    <div class="stat-item">
                        <span class="stat-number">10K+</span>
                        <span class="stat-label">Users</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">98%</span>
                        <span class="stat-label">Satisfaction</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">50+</span>
                        <span class="stat-label">Dietitians</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Login Panel -->
        <div class="right-panel">
            <div class="login-container">
                <div class="logo-container small-logo">
                    <div class="filled-heart-icon" style="background-color: #f2500c; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <svg viewBox="0 0 24 24" fill="white" width="18" height="18">
                            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                        </svg>
                    </div>
                    <h2 class="logo-text" style="font-size: 24px; color: white;">Health Track</h2>
                </div>

                <h1 class="welcome-text">Welcome!</h1>

                <?php renderFlashMessage(); ?>

                <?php if (!empty($error)): ?>
                    <div style="background-color: rgba(231, 76, 60, 0.15); border: 1px solid #e74c3c; color: #e74c3c; padding: 14px 18px; border-radius: 12px; margin-bottom: 24px; font-size: 14px; font-weight: 500;">
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>

                <form class="login-form" method="POST" action="login.php">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <div class="input-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" placeholder="name@example.com" required>
                    </div>
                    <div class="input-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    </div>
                    
                    <div class="forgot-password">
                        <a href="reset-password.html">Forgot Password?</a>
                    </div>
                    
                    <button type="submit" class="login-btn">Log in</button>
                </form>

                <div class="create-account" style="margin-top: 20px;">
                    <span style="color: #8c8c8c; font-size: 15px;">Don't have an account? </span>
                    <a href="register.php">Create Account</a>
                </div>

                <!-- Viva / Quick Demo Credentials Box -->
                <div style="margin-top: 30px; padding: 16px; background-color: #1a1d24; border: 1px solid #2a2f3a; border-radius: 12px; font-size: 12px; color: #8c909a;">
                    <div style="font-weight: 600; color: #ffffff; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">Demo Accounts (Pre-configured):</div>
                    <div><strong style="color: #2ecc71;">Admin:</strong> admin@healthtrack.com / admin123</div>
                    <div><strong style="color: #3498db;">Approved Dietitian:</strong> sarah@healthtrack.com / dietitian123</div>
                    <div><strong style="color: #f39c12;">Pending Dietitian:</strong> james@healthtrack.com / dietitian123</div>
                    <div><strong style="color: #f2500c;">User:</strong> alice@example.com / user123</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
