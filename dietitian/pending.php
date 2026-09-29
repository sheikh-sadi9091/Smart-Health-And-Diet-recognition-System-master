<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
$user = getCurrentUser();

if ($user['role'] !== 'dietitian') {
    redirectByRole($user['role']);
}

$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT * FROM dietitian_profiles WHERE user_id = :uid LIMIT 1");
$stmt->execute(['uid' => $user['id']]);
$profile = $stmt->fetch();

if ($profile && $profile['approval_status'] === 'approved') {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Track - Application Pending Approval</title>
    <link rel="stylesheet" href="../dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="admin-full-page">
    <div class="admin-page-container" style="max-width: 650px; margin: 80px auto; text-align: center;">
        
        <div class="logo-container small-logo" style="justify-content: center; margin-bottom: 30px;">
            <div class="filled-heart-icon" style="width: 36px; height: 36px;">
                <svg viewBox="0 0 24 24" fill="white" width="20" height="20">
                    <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                </svg>
            </div>
            <span class="logo-text-small" style="font-size: 22px;">Health Track</span>
        </div>

        <div class="panel-card" style="padding: 40px; border-radius: 20px;">
            <div style="text-align:left; margin-bottom:20px;"><?php renderBackButton(); ?></div>
            <div class="icon-wrapper icon-orange" style="margin: 0 auto 20px auto; width: 70px; height: 70px; border-radius: 50%; background-color: rgba(242, 80, 12, 0.1);">
                <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
            </div>

            <h2 style="font-size: 24px; font-weight: 700; color: var(--text-light); margin-bottom: 12px;">Application Under Review</h2>
            <p style="font-size: 15px; color: var(--text-muted); line-height: 1.6; margin-bottom: 30px;">
                Hello, <strong><?= e($user['name']) ?></strong>! Your registration as a Registered Dietitian has been submitted. For safety and patient quality standards, an Administrator must verify your qualifications and certifications before your account is activated.
            </p>

            <div style="background-color: var(--input-bg); border-radius: 12px; padding: 20px; text-align: left; margin-bottom: 30px;">
                <div style="font-size: 12px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase;">Submitted Application Summary</div>
                <div style="font-size: 14px; color: var(--text-light); margin-bottom: 6px;"><strong>Specialization:</strong> <?= e($profile['specialization'] ?? 'Not specified') ?></div>
                <div style="font-size: 14px; color: var(--text-light); margin-bottom: 6px;"><strong>Qualification:</strong> <?= e($profile['qualification'] ?? 'Not specified') ?></div>
                <div style="font-size: 14px; color: var(--text-light);"><strong>Current Status:</strong> <span class="status-badge" style="background-color: rgba(243, 156, 18, 0.15); color: #f39c12;">Pending Approval</span></div>
            </div>

            <a href="../logout.php" class="primary-btn" style="display: inline-block; text-decoration: none; line-height: 52px; text-align: center;">Sign Out</a>
        </div>
    </div>
</body>
</html>
