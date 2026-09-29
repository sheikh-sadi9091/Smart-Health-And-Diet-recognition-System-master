<?php
require_once __DIR__ . '/../includes/auth.php';

requireRole('user');
$currentUser = getCurrentUser();
$pdo = getDBConnection();
$error = '';

$dietitiansStmt = $pdo->prepare(
    "SELECT DISTINCT d.id, d.name, d.email FROM users d
     JOIN dietitian_profiles dp ON dp.user_id = d.id AND dp.approval_status = 'approved'
     WHERE d.role = 'dietitian' AND d.status = 'active'
       AND (EXISTS (SELECT 1 FROM meal_plans p WHERE p.user_id = :plan_user AND p.dietitian_id = d.id)
         OR EXISTS (SELECT 1 FROM recommendations r WHERE r.user_id = :recommendation_user AND r.dietitian_id = d.id))
     ORDER BY d.name"
);
$dietitiansStmt->execute([
    'plan_user' => $currentUser['id'],
    'recommendation_user' => $currentUser['id'],
]);
$dietitians = $dietitiansStmt->fetchAll();
$dietitianIds = array_map('intval', array_column($dietitians, 'id'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dietitianId = filter_var($_POST['dietitian_id'] ?? null, FILTER_VALIDATE_INT);
    $message = is_string($_POST['message'] ?? null) ? trim($_POST['message']) : '';

    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please reload and try again.';
    } elseif ($dietitianId === false || !in_array($dietitianId, $dietitianIds, true) || $message === '' || strlen($message) > 4000) {
        $error = 'Choose an assigned dietitian and enter a message of up to 4,000 characters.';
    } else {
        $send = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, message) VALUES (:sender_id, :receiver_id, :message)');
        $send->execute(['sender_id' => $currentUser['id'], 'receiver_id' => $dietitianId, 'message' => $message]);
        header('Location: messages.php?dietitian_id=' . $dietitianId);
        exit;
    }
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$selectedId = filter_var($_GET['dietitian_id'] ?? ($_POST['dietitian_id'] ?? null), FILTER_VALIDATE_INT);
$dietitian = null;
foreach ($dietitians as $candidate) {
    if ($selectedId && (int)$candidate['id'] === $selectedId) {
        $dietitian = $candidate;
        break;
    }
}

$messages = [];
if ($dietitian) {
    $markRead = $pdo->prepare('UPDATE messages SET is_read = 1 WHERE sender_id = :dietitian_id AND receiver_id = :user_id');
    $markRead->execute(['dietitian_id' => $dietitian['id'], 'user_id' => $currentUser['id']]);
    $threadStmt = $pdo->prepare(
        'SELECT m.*, sender.name AS sender_name FROM messages m
         JOIN users sender ON sender.id = m.sender_id
         WHERE (m.sender_id = :user_sender AND m.receiver_id = :dietitian_receiver)
            OR (m.sender_id = :dietitian_sender AND m.receiver_id = :user_receiver)
         ORDER BY m.created_at ASC LIMIT 200'
    );
    $threadStmt->execute([
        'user_sender' => $currentUser['id'],
        'dietitian_receiver' => $dietitian['id'],
        'dietitian_sender' => $dietitian['id'],
        'user_receiver' => $currentUser['id'],
    ]);
    $messages = $threadStmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Health Track</title>
    <link rel="stylesheet" href="../dashboard.css">
</head>
<body>
    <div class="dashboard-layout">
        <aside class="sidebar">
            <div class="sidebar-header"><span class="logo-text-small">Health Track</span></div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-link">Overview</a>
                <a href="health-info.php" class="nav-link">Health Information</a>
                <a href="log-meals.php" class="nav-link">Log Meals</a>
                <a href="calorie-reports.php" class="nav-link">Calorie Reports</a>
                <a href="diet-plans.php" class="nav-link">My Diet Plans</a>
                <a href="messages.php" class="nav-link active">Messages</a>
            </nav>
            <div class="sidebar-footer"><div><?= e($currentUser['name']) ?></div><a href="../logout.php" class="nav-link sign-out">Sign Out</a></div>
        </aside>
        <main class="main-content">
            <header class="top-header"><?php renderBackButton('dashboard.php'); ?><h1 class="header-title">Messages</h1></header>
            <div class="content-wrapper">
                <?php if ($error !== ''): ?><p role="alert" class="form-card" style="color:#e74c3c;"><?= e($error) ?></p><?php endif; ?>
                <div class="form-card">
                    <h2 class="card-title">Your Dietitians</h2>
                    <?php if (!$dietitians): ?><p>Messages will be available when a dietitian is assigned to your care.</p><?php else: ?><div class="pill-options"><?php foreach ($dietitians as $listedDietitian): ?><a class="pill-btn" href="?dietitian_id=<?= (int)$listedDietitian['id'] ?>"><?= e($listedDietitian['name']) ?></a><?php endforeach; ?></div><?php endif; ?>
                </div>
                <?php if ($dietitian): ?>
                    <div class="form-card">
                        <h2 class="card-title">Conversation with <?= e($dietitian['name']) ?></h2>
                        <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:24px;">
                            <?php if (!$messages): ?><p>No messages in this conversation yet.</p><?php endif; ?>
                            <?php foreach ($messages as $item): ?><div style="padding:14px;background:<?= (int)$item['sender_id'] === (int)$currentUser['id'] ? 'var(--input-bg)' : 'var(--bg-dark)' ?>;border-radius:10px;">
                                <strong><?= e($item['sender_name']) ?></strong><small style="margin-left:10px;color:var(--text-muted);"><?= e(date('M j, Y g:i a', strtotime($item['created_at']))) ?></small>
                                <p style="margin-top:8px;white-space:pre-wrap;"><?= e($item['message']) ?></p>
                            </div><?php endforeach; ?>
                        </div>
                        <form method="post" action="messages.php?dietitian_id=<?= (int)$dietitian['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="dietitian_id" value="<?= (int)$dietitian['id'] ?>">
                            <div class="input-group"><label for="message">Message</label><textarea id="message" name="message" maxlength="4000" rows="4" required></textarea></div>
                            <button class="primary-btn" type="submit">Send Message</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>