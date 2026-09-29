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
$patientIds = array_map('intval', array_column($patients, 'id'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
    $title = is_string($_POST['title'] ?? null) ? trim($_POST['title']) : '';
    $recommendation = is_string($_POST['recommendation'] ?? null) ? trim($_POST['recommendation']) : '';

    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please reload and try again.';
    } elseif ($patientId === false || !in_array($patientId, $patientIds, true) || $title === '' || strlen($title) > 200 || $recommendation === '' || strlen($recommendation) > 10000) {
        $error = 'Choose an assigned patient and enter a title and recommendation.';
    } else {
        $insert = $pdo->prepare('INSERT INTO recommendations (dietitian_id, user_id, title, recommendation) VALUES (:dietitian_id, :user_id, :title, :recommendation)');
        $insert->execute([
            'dietitian_id' => $currentUser['id'],
            'user_id' => $patientId,
            'title' => $title,
            'recommendation' => $recommendation,
        ]);
        setFlashMessage('success', 'Recommendation sent to the patient.');
        header('Location: recommendations.php');
        exit;
    }
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$recommendationsStmt = $pdo->prepare(
    'SELECT r.*, u.name AS patient_name, u.email AS patient_email FROM recommendations r
     JOIN users u ON u.id = r.user_id WHERE r.dietitian_id = :dietitian_id
     ORDER BY r.created_at DESC LIMIT 100'
);
$recommendationsStmt->execute(['dietitian_id' => $currentUser['id']]);
$recommendations = $recommendationsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recommendations - Health Track</title>
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
                <a href="recommendations.php" class="nav-link dietitian-nav-link active">Recommendations</a>
                <a href="messages.php" class="nav-link dietitian-nav-link">Chat</a>
            </nav>
            <div class="sidebar-footer"><div><?= e($currentUser['name']) ?></div><a href="../logout.php" class="nav-link sign-out">Sign Out</a></div>
        </aside>
        <main class="main-content">
            <header class="top-header"><?php renderBackButton('dashboard.php'); ?><h1 class="header-title">Recommendations</h1></header>
            <div class="content-wrapper">
                <?php renderFlashMessage(); ?>
                <?php if ($error !== ''): ?><p role="alert" class="form-card" style="color:#e74c3c;"><?= e($error) ?></p><?php endif; ?>
                <form method="post" action="recommendations.php" class="health-info-form">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <div class="form-card">
                        <h2 class="card-title">Send a Recommendation</h2>
                        <div class="input-grid">
                            <div class="input-group"><label for="user_id">Assigned patient</label><select id="user_id" name="user_id" required><option value="">Select patient</option><?php foreach ($patients as $patient): ?><option value="<?= (int)$patient['id'] ?>" <?= (int)($_POST['user_id'] ?? 0) === (int)$patient['id'] ? 'selected' : '' ?>><?= e($patient['name'] . ' (' . $patient['email'] . ')') ?></option><?php endforeach; ?></select></div>
                            <div class="input-group"><label for="title">Title</label><input id="title" name="title" maxlength="200" value="<?= e($_POST['title'] ?? '') ?>" required></div>
                            <div class="input-group"><label for="recommendation">Recommendation</label><textarea id="recommendation" name="recommendation" maxlength="10000" rows="5" required><?= e($_POST['recommendation'] ?? '') ?></textarea></div>
                        </div>
                        <button class="primary-btn" type="submit" <?= !$patients ? 'disabled title="Create a meal plan to assign a patient first"' : '' ?>>Send Recommendation</button>
                        <?php if (!$patients): ?><p>No assigned patients yet. Create a meal plan first.</p><?php endif; ?>
                    </div>
                </form>
                <div class="form-card">
                    <h2 class="card-title">Recent Recommendations</h2>
                    <?php if (!$recommendations): ?><p>No recommendations have been sent yet.</p><?php else: ?><div style="overflow-x:auto;"><table class="report-table"><thead><tr><th>Patient</th><th>Title</th><th>Recommendation</th><th>Sent</th></tr></thead><tbody>
                        <?php foreach ($recommendations as $item): ?><tr><td><?= e($item['patient_name']) ?><br><?= e($item['patient_email']) ?></td><td><?= e($item['title']) ?></td><td><?= nl2br(e($item['recommendation'])) ?></td><td><?= e(date('M j, Y', strtotime($item['created_at']))) ?></td></tr><?php endforeach; ?>
                    </tbody></table></div><?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>