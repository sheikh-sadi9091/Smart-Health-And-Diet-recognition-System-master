<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectByRole($_SESSION['user_role'] ?? 'user');
}

$error = '';
$fullname = '';
$email = '';
$phone = '';
$role = 'user';
$qualification = '';
$specialization = '';
$gender = 'other';
$age = 25;
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim(requestString($_POST['fullname'] ?? null));
    $email = trim(requestString($_POST['email'] ?? null));
    $phone = trim(requestString($_POST['phone'] ?? null));
    $password = requestString($_POST['password'] ?? null);
    $confirm_password = requestString($_POST['confirm_password'] ?? null);
    $role = in_array($_POST['role'] ?? '', ['user', 'dietitian']) ? $_POST['role'] : 'user';
    $qualification = trim(requestString($_POST['qualification'] ?? null));
    $specialization = trim(requestString($_POST['specialization'] ?? null));
    $csrfToken = requestString($_POST['csrf_token'] ?? null);

    // Validation
    if (!isValidCsrfToken($csrfToken)) {
        $error = 'Your session expired. Please refresh the page and try again.';
    } elseif (empty($fullname) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $pdo = getDBConnection();

        // Check if email is already taken
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            $error = 'An account with this email address already exists.';
        } else {
            // Hash password securely with bcrypt
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $status = ($role === 'dietitian') ? 'pending' : 'active';

            $pdo->beginTransaction();
            try {
                $insertUser = $pdo->prepare("
                    INSERT INTO users (name, email, password, phone, role, status) 
                    VALUES (:name, :email, :password, :phone, :role, :status)
                ");
                $insertUser->execute([
                    'name'     => $fullname,
                    'email'    => $email,
                    'password' => $hashedPassword,
                    'phone'    => $phone,
                    'role'     => $role,
                    'status'   => $status
                ]);
                $newUserId = (int)$pdo->lastInsertId();

                if ($role === 'dietitian') {
                    // Create dietitian profile with pending status
                    $insertDietitian = $pdo->prepare("
                        INSERT INTO dietitian_profiles (user_id, qualification, specialization, approval_status)
                        VALUES (:uid, :qual, :spec, 'pending')
                    ");
                    $insertDietitian->execute([
                        'uid'  => $newUserId,
                        'qual' => $qualification ?: 'Registered Nutritionist / Dietitian',
                        'spec' => $specialization ?: 'General Nutrition'
                    ]);
                } else {
                    // Create initial health profile for user
                    $insertHealth = $pdo->prepare("
                        INSERT INTO health_profiles (user_id, age, gender, height, weight, activity_level, health_goal, daily_calorie_target)
                        VALUES (:uid, 25, 'other', 170.00, 70.00, 'sedentary', 'maintain_weight', 2000)
                    ");
                    $insertHealth->execute(['uid' => $newUserId]);
                }

                $pdo->commit();

                // Log in user and redirect
                $newUserRecord = [
                    'id'    => $newUserId,
                    'name'  => $fullname,
                    'email' => $email,
                    'role'  => $role
                ];
                loginUser($newUserRecord);

                if ($role === 'dietitian') {
                    setFlashMessage('info', 'Your dietitian registration was received and is pending admin approval.');
                    header("Location: dietitian/pending.php");
                } else {
                    setFlashMessage('success', 'Account created successfully! Welcome to Health Track.');
                    header("Location: user/dashboard.php");
                }
                exit;

            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Failed to create account. Please try again.';
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
    <title>Health Track - Create Account</title>
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

        <!-- Right Registration Panel -->
        <div class="right-panel">
            <div class="login-container">
                <a href="login.php" class="back-link">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                    Back to Login
                </a>

                <div class="logo-container small-logo">
                    <div class="filled-heart-icon" style="background-color: #f2500c; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <svg viewBox="0 0 24 24" fill="white" width="18" height="18">
                            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                        </svg>
                    </div>
                    <span class="logo-text-small" style="font-size: 20px; color: white;">Health Track</span>
                </div>

                <h1 class="page-title">Create account</h1>
                <p class="form-subtitle">Join thousands on their health journey</p>

                <?php if (!empty($error)): ?>
                    <div style="background-color: rgba(231, 76, 60, 0.15); border: 1px solid #e74c3c; color: #e74c3c; padding: 14px 18px; border-radius: 12px; margin-bottom: 24px; font-size: 14px; font-weight: 500;">
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>

                <form class="login-form" method="POST" action="register.php">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <div class="input-row">
                        <div class="input-group">
                            <label for="fullname">Full Name *</label>
                            <input type="text" id="fullname" name="fullname" value="<?= e($fullname) ?>" placeholder="e.g. John Doe" required>
                        </div>
                        <div class="input-group">
                            <label for="phone">Phone</label>
                            <input type="tel" id="phone" name="phone" value="<?= e($phone) ?>" placeholder="e.g. +1 234 567 8900">
                        </div>
                    </div>
                    
                    <div class="input-group">
                        <label for="email">Email address *</label>
                        <input type="email" id="email" name="email" value="<?= e($email) ?>" placeholder="name@example.com" required>
                    </div>
                    
                    <div class="input-row">
                        <div class="input-group">
                            <label for="password">Password *</label>
                            <input type="password" id="password" name="password" placeholder="At least 6 characters" required>
                        </div>
                        <div class="input-group">
                            <label for="confirm_password">Confirm Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat password" required>
                        </div>
                    </div>

                    <div class="role-group">
                        <span class="role-label">I am registering as:</span>
                        <div class="role-options">
                            <label class="role-option">
                                <input type="radio" name="role" value="user" <?= ($role === 'user') ? 'checked' : '' ?> onchange="toggleDietitianFields()">
                                <span class="role-btn">User / Patient</span>
                            </label>
                            <label class="role-option">
                                <input type="radio" name="role" value="dietitian" <?= ($role === 'dietitian') ? 'checked' : '' ?> onchange="toggleDietitianFields()">
                                <span class="role-btn">Dietitian / Nutritionist</span>
                            </label>
                        </div>
                    </div>

                    <!-- Additional Dietitian specific fields (shown only if Dietitian role is selected) -->
                    <div id="dietitianExtraFields" style="display: <?= ($role === 'dietitian') ? 'block' : 'none' ?>; margin-bottom: 20px;">
                        <div class="input-row">
                            <div class="input-group">
                                <label for="qualification">Qualifications / Degree</label>
                                <input type="text" id="qualification" name="qualification" value="<?= e($qualification) ?>" placeholder="e.g. M.Sc. Clinical Nutrition, RD">
                            </div>
                            <div class="input-group">
                                <label for="specialization">Primary Specialization</label>
                                <input type="text" id="specialization" name="specialization" value="<?= e($specialization) ?>" placeholder="e.g. Sports Nutrition, Diabetes">
                            </div>
                        </div>
                        <p style="font-size: 12px; color: #8c909a; margin-top: -10px;">
                            Note: Dietitian accounts are subject to administrative review before being enabled.
                        </p>
                    </div>
                    
                    <button type="submit" class="primary-btn">Create Account</button>
                </form>

                <p class="auth-footer">Already have an account? <a href="login.php">Sign In</a></p>
            </div>
        </div>
    </div>

    <script>
        function toggleDietitianFields() {
            const role = document.querySelector('input[name="role"]:checked').value;
            const extraFields = document.getElementById('dietitianExtraFields');
            if (role === 'dietitian') {
                extraFields.style.display = 'block';
            } else {
                extraFields.style.display = 'none';
            }
        }
    </script>
</body>
</html>
