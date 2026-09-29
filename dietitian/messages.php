<?php
require_once __DIR__ . '/../includes/auth.php';

requireRole('dietitian');
$currentUser = getCurrentUser();
$pdo = getDBConnection();
$error = '';

$patientsStmt = $pdo->prepare(
    "SELECT DISTINCT u.id, u.name, u.email FROM users u
     WHERE u.role = 'user' AND u.status = 'active'
       AND (EXISTS (SELECT 1 FROM meal_plans p WHERE p.user_id = u.id AND p.dietitian_id = :plan_dietitian)
         OR EXISTS (SELECT 1 FROM recommendations r WHERE r.user_id = u.id AND r.dietitian_id = :recommendation_dietitian))
     ORDER BY u.name"
);
$patientsStmt->execute([
    'plan_dietitian' => $currentUser['id'],
    'recommendation_dietitian' => $currentUser['id'],
]);
$patients = $patientsStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
    $message = is_string($_POST['message'] ?? null) ? trim($_POST['message']) : '';

    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please reload and try again.';
    } elseif ($patientId === false || !in_array($patientId, array_map('intval', array_column($patients, 'id')), true) || $message === '' || strlen($message) > 4000) {
        $error = 'Choose an assigned patient and enter a message of up to 4,000 characters.';
    } else {
        $send = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, message) VALUES (:sender_id, :receiver_id, :message)');
        $send->execute(['sender_id' => $currentUser['id'], 'receiver_id' => $patientId, 'message' => $message]);
        header('Location: messages.php?user_id=' . $patientId);
        exit;
    }
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$selectedId = filter_var($_GET['user_id'] ?? ($_POST['user_id'] ?? null), FILTER_VALIDATE_INT);
$patient = null;
foreach ($patients as $candidate) {
    if ($selectedId && (int)$candidate['id'] === $selectedId) {
        $patient = $candidate;
        break;
    }
}

$messages = [];
if ($patient) {
    $markRead = $pdo->prepare('UPDATE messages SET is_read = 1 WHERE sender_id = :patient_id AND receiver_id = :dietitian_id');
    $markRead->execute(['patient_id' => $patient['id'], 'dietitian_id' => $currentUser['id']]);
    $threadStmt = $pdo->prepare(
        'SELECT m.*, sender.name AS sender_name FROM messages m
         JOIN users sender ON sender.id = m.sender_id
         WHERE (m.sender_id = :dietitian_sender AND m.receiver_id = :patient_receiver)
            OR (m.sender_id = :patient_sender AND m.receiver_id = :dietitian_receiver)
         ORDER BY m.created_at ASC LIMIT 200'
    );
    $threadStmt->execute([
        'dietitian_sender' => $currentUser['id'],
        'patient_receiver' => $patient['id'],
        'patient_sender' => $patient['id'],
        'dietitian_receiver' => $currentUser['id'],
    ]);
    $messages = $threadStmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Messages - Health Track</title>
    <link rel="stylesheet" href="../dashboard.css">
</head>
<body>
    <div class="dashboard-layout">
        <aside class="sidebar dietitian-sidebar">
            <div class="sidebar-header"><span class="logo-text-small">Health Track</span></div>
            <nav class="sidebar-nav dietitian-nav">
                <a href="dashboard.php" class="nav-link dietitian-nav-link">Dashboard</a>
                <a href="meal-plans.php" class="nav-link dietitian-nav-link">Meal Plans</a>
                <a href="health-info.php" class="nav-link dietitian-nav-link">Health Information</a>
                <a href="recommendations.php" class="nav-link dietitian-nav-link">Recommendations</a>
                <a href="messages.php" class="nav-link dietitian-nav-link active">Chat</a>
            </nav>
            <div class="sidebar-footer"><div><?= e($currentUser['name']) ?></div><a href="../logout.php" class="nav-link sign-out">Sign Out</a></div>
        </aside>
        <main class="main-content">
            <header class="top-header"><?php renderBackButton('dashboard.php'); ?><h1 class="header-title">Patient Messages</h1></header>
            <div class="content-wrapper">
                <?php if ($error !== ''): ?><p role="alert" class="form-card" style="color:#e74c3c;"><?= e($error) ?></p><?php endif; ?>
                <div class="form-card">
                    <h2 class="card-title">Assigned Patients</h2>
                    <?php if (!$patients): ?><p>No patients are assigned to you yet. Create a meal plan first.</p><?php else: ?><div class="pill-options"><?php foreach ($patients as $listedPatient): ?><a class="pill-btn" href="?user_id=<?= (int)$listedPatient['id'] ?>"><?= e($listedPatient['name']) ?></a><?php endforeach; ?></div><?php endif; ?>
                </div>
                <?php if ($patient): ?>
                    <div class="form-card">
                        <h2 class="card-title">Conversation with <?= e($patient['name']) ?></h2>
                        <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:24px;">
                            <?php if (!$messages): ?><p>No messages in this conversation yet.</p><?php endif; ?>
                            <?php foreach ($messages as $item): ?><div style="padding:14px;background:<?= (int)$item['sender_id'] === (int)$currentUser['id'] ? 'var(--input-bg)' : 'var(--bg-dark)' ?>;border-radius:10px;">
                                <strong><?= e($item['sender_name']) ?></strong><small style="margin-left:10px;color:var(--text-muted);"><?= e(date('M j, Y g:i a', strtotime($item['created_at']))) ?></small>
                                <p style="margin-top:8px;white-space:pre-wrap;"><?= e($item['message']) ?></p>
                            </div><?php endforeach; ?>
                        </div>
                        <form method="post" action="messages.php?user_id=<?= (int)$patient['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="user_id" value="<?= (int)$patient['id'] ?>">
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