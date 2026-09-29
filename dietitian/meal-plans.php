<?php
require_once __DIR__ . '/../includes/auth.php';

requireRole('dietitian');
$currentUser = getCurrentUser();
$pdo = getDBConnection();
$error = '';
$patients = $pdo->query("SELECT id, name, email FROM users WHERE role = 'user' AND status = 'active' ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
    $title = trim(requestString($_POST['title'] ?? null));
    $description = trim(requestString($_POST['description'] ?? null));
    $startDate = requestString($_POST['start_date'] ?? null);
    $endDate = requestString($_POST['end_date'] ?? null);
    $goal = trim(requestString($_POST['goal'] ?? null));
    $csrfToken = $_POST['csrf_token'] ?? '';
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
    $end = DateTimeImmutable::createFromFormat('!Y-m-d', $endDate);

    if (!isValidCsrfToken($csrfToken)) {
        $error = 'Your session expired. Please reload and try again.';
    } elseif ($patientId === false || $title === '' || strlen($title) > 200 || strlen($description) > 5000 || strlen($goal) > 150 || !$start || !$end || $start->format('Y-m-d') !== $startDate || $end->format('Y-m-d') !== $endDate || $end < $start) {
        $error = 'Enter a patient, plan title, and valid date range.';
    } else {
        $patientStmt = $pdo->prepare("SELECT id FROM users WHERE id = :id AND role = 'user' AND status = 'active'");
        $patientStmt->execute(['id' => $patientId]);
        if (!$patientStmt->fetch()) {
            $error = 'Choose an active patient account.';
        } else {
            $insertPlan = $pdo->prepare(
                'INSERT INTO meal_plans (dietitian_id, user_id, title, description, start_date, end_date, goal, status)
                 VALUES (:dietitian_id, :user_id, :title, :description, :start_date, :end_date, :goal, \'active\')'
            );
            $insertPlan->execute([
                'dietitian_id' => $currentUser['id'],
                'user_id' => $patientId,
                'title' => $title,
                'description' => $description,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'goal' => $goal,
            ]);
            setFlashMessage('success', 'Meal plan created.');
            header('Location: meal-plans.php');
            exit;
        }
    }
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$plansStmt = $pdo->prepare(
    'SELECT p.*, u.name AS patient_name, u.email AS patient_email FROM meal_plans p
     JOIN users u ON u.id = p.user_id WHERE p.dietitian_id = :dietitian_id ORDER BY p.created_at DESC'
);
$plansStmt->execute(['dietitian_id' => $currentUser['id']]);
$plans = $plansStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meal Plans - Health Track</title>
    <link rel="stylesheet" href="../dashboard.css">
</head>
<body>
    <div class="dashboard-layout">
        <aside class="sidebar">
            <div class="sidebar-header"><span class="logo-text-small">Health Track</span></div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-link">Dashboard</a>
                <a href="meal-plans.php" class="nav-link active">Meal Plans</a>
            </nav>
            <div class="sidebar-footer">
                <div><?= e($currentUser['name']) ?></div>
                <a href="../logout.php" class="nav-link sign-out">Sign Out</a>
            </div>
        </aside>
        <main class="main-content">
            <header class="top-header"><?php renderBackButton('dashboard.php'); ?><h1 class="header-title">Meal Plans</h1></header>
            <div class="content-wrapper">
                <?php renderFlashMessage(); ?>
                <?php if ($error !== ''): ?><p role="alert" class="form-card" style="color:#e74c3c;"><?= e($error) ?></p><?php endif; ?>
                <form class="health-info-form" method="post" action="meal-plans.php">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <div class="form-card">
                        <h2 class="card-title">Create a Meal Plan</h2>
                        <div class="input-grid">
                            <div class="input-group"><label for="user_id">Patient</label><select id="user_id" name="user_id" required><option value="">Select a patient</option><?php foreach ($patients as $patient): ?><option value="<?= (int)$patient['id'] ?>" <?= (int)($_POST['user_id'] ?? 0) === (int)$patient['id'] ? 'selected' : '' ?>><?= e($patient['name'] . ' (' . $patient['email'] . ')') ?></option><?php endforeach; ?></select></div>
                            <div class="input-group"><label for="title">Plan title</label><input id="title" name="title" maxlength="200" value="<?= e($_POST['title'] ?? '') ?>" required></div>
                            <div class="input-group"><label for="goal">Goal</label><input id="goal" name="goal" maxlength="150" value="<?= e($_POST['goal'] ?? '') ?>"></div>
                            <div class="input-group"><label for="start_date">Start date</label><input id="start_date" name="start_date" type="date" value="<?= e($_POST['start_date'] ?? date('Y-m-d')) ?>" required></div>
                            <div class="input-group"><label for="end_date">End date</label><input id="end_date" name="end_date" type="date" value="<?= e($_POST['end_date'] ?? date('Y-m-d', strtotime('+7 days'))) ?>" required></div>
                            <div class="input-group"><label for="description">Description</label><textarea id="description" name="description" maxlength="5000" rows="4"><?= e($_POST['description'] ?? '') ?></textarea></div>
                        </div>
                        <button class="primary-btn" type="submit" <?= !$patients ? 'disabled title="No active patient accounts"' : '' ?>>Create Plan</button>
                    </div>
                </form>
                <div class="form-card">
                    <h2 class="card-title">Plans Created (<?= count($plans) ?>)</h2>
                    <?php if (!$plans): ?><p>No meal plans have been created yet.</p><?php else: ?><div style="overflow-x:auto;"><table class="report-table"><thead><tr><th>Patient</th><th>Plan</th><th>Goal</th><th>Dates</th><th>Status</th></tr></thead><tbody>
                        <?php foreach ($plans as $plan): ?><tr><td><?= e($plan['patient_name']) ?><br><?= e($plan['patient_email']) ?></td><td><?= e($plan['title']) ?><br><?= e($plan['description']) ?></td><td><?= e($plan['goal']) ?></td><td><?= e($plan['start_date']) ?> to <?= e($plan['end_date']) ?></td><td><?= e(ucfirst($plan['status'])) ?></td></tr><?php endforeach; ?>
                    </tbody></table></div><?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>