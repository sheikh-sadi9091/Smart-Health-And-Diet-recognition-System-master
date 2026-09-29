<?php
require_once __DIR__ . '/../includes/auth.php';

requireRole('dietitian');
$currentUser = getCurrentUser();
$pdo = getDBConnection();
$search = trim($_GET['q'] ?? '');

$patientsStmt = $pdo->prepare(
    "SELECT u.id, u.name, u.email, h.age, h.health_goal, h.bmi
     FROM users u JOIN health_profiles h ON h.user_id = u.id
     WHERE u.role = 'user' AND u.status = 'active'
       AND (EXISTS (SELECT 1 FROM meal_plans p WHERE p.user_id = u.id AND p.dietitian_id = :plan_dietitian)
         OR EXISTS (SELECT 1 FROM recommendations r WHERE r.user_id = u.id AND r.dietitian_id = :recommendation_dietitian))
       AND (:search_empty = '' OR u.name LIKE :search_name OR u.email LIKE :search_email)
     ORDER BY u.name"
);
$searchPattern = '%' . $search . '%';
$patientsStmt->execute([
    'plan_dietitian' => $currentUser['id'],
    'recommendation_dietitian' => $currentUser['id'],
    'search_empty' => $search,
    'search_name' => $searchPattern,
    'search_email' => $searchPattern,
]);
$patients = $patientsStmt->fetchAll();

$selectedId = filter_var($_GET['user_id'] ?? null, FILTER_VALIDATE_INT);
$patient = null;
foreach ($patients as $candidate) {
    if ($selectedId && (int)$candidate['id'] === $selectedId) {
        $patient = $candidate;
        break;
    }
}
if (!$patient && $patients) {
    $patient = $patients[0];
}

$profile = null;
$waterMl = 0;
$latestSleep = null;
$averageCalories = null;
$bmiData = null;
if ($patient) {
    $profileStmt = $pdo->prepare('SELECT * FROM health_profiles WHERE user_id = :user_id LIMIT 1');
    $profileStmt->execute(['user_id' => $patient['id']]);
    $profile = $profileStmt->fetch();
    $bmiData = calculateBMI((float)$profile['weight'], (float)$profile['height']);

    $waterStmt = $pdo->prepare(
        'SELECT COALESCE(SUM(amount_ml), 0) FROM water_logs
         WHERE user_id = :user_id AND log_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)'
    );
    $waterStmt->execute(['user_id' => $patient['id']]);
    $waterMl = (int)$waterStmt->fetchColumn();

    $sleepStmt = $pdo->prepare('SELECT duration_minutes FROM sleep_logs WHERE user_id = :user_id ORDER BY sleep_date DESC LIMIT 1');
    $sleepStmt->execute(['user_id' => $patient['id']]);
    $latestSleep = $sleepStmt->fetchColumn();

    $calorieStmt = $pdo->prepare(
        'SELECT AVG(day_calories) FROM (
            SELECT SUM(calories) AS day_calories FROM meal_logs
            WHERE user_id = :user_id AND meal_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY meal_date
         ) AS daily_totals'
    );
    $calorieStmt->execute(['user_id' => $patient['id']]);
    $averageCalories = $calorieStmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Health Information - Health Track</title>
    <link rel="stylesheet" href="../dashboard.css">
</head>
<body>
    <div class="dashboard-layout">
        <aside class="sidebar dietitian-sidebar">
            <div class="sidebar-header"><span class="logo-text-small">Health Track</span></div>
            <nav class="sidebar-nav dietitian-nav">
                <a href="dashboard.php" class="nav-link dietitian-nav-link">Dashboard</a>
                <a href="meal-plans.php" class="nav-link dietitian-nav-link">Meal Plans</a>
                <a href="health-info.php" class="nav-link dietitian-nav-link active">Health Information</a>
            </nav>
            <div class="sidebar-footer"><div><?= e($currentUser['name']) ?></div><a href="../logout.php" class="nav-link sign-out">Sign Out</a></div>
        </aside>
        <main class="main-content dietitian-main">
            <header class="page-header-clean">
                <div class="page-header-title-row"><?php renderBackButton('dashboard.php'); ?><h1 class="page-main-title">Health Information</h1></div>
                <p class="page-sub-title">Review profiles for patients assigned to you</p>
            </header>
            <div class="content-wrapper full-width-wrap">
                <div class="health-info-split">
                    <div class="left-col-users">
                        <form class="search-users-box" method="get" action="health-info.php">
                            <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search assigned patients..." aria-label="Search assigned patients">
                        </form>
                        <?php foreach ($patients as $listedPatient): ?>
                            <a href="?user_id=<?= (int)$listedPatient['id'] ?>&amp;q=<?= rawurlencode($search) ?>" class="user-list-card <?= $patient && (int)$patient['id'] === (int)$listedPatient['id'] ? 'selected-card' : '' ?>">
                                <div class="user-list-flex">
                                    <div class="avatar avatar-teal"><?= e(strtoupper(substr($listedPatient['name'], 0, 2))) ?></div>
                                    <div class="user-list-info"><h4><?= e($listedPatient['name']) ?></h4><p><?= e(ucwords(str_replace('_', ' ', $listedPatient['health_goal'] ?? 'No goal set'))) ?></p></div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                        <?php if (!$patients): ?><div class="user-list-card"><div class="user-list-info"><h4>No assigned patients</h4><p>Create a meal plan for an active user to establish an assignment.</p><a href="meal-plans.php">Open Meal Plans</a></div></div><?php endif; ?>
                    </div>
                    <div class="right-col-details">
                        <?php if ($patient && $profile): ?>
                            <div class="health-detail-panel">
                                <div class="hd-profile-header">
                                    <div class="hd-profile-left">
                                        <div class="avatar avatar-teal avatar-large"><?= e(strtoupper(substr($patient['name'], 0, 2))) ?></div>
                                        <div class="hd-profile-info"><h3><?= e($patient['name']) ?></h3><p><?= e($patient['email']) ?> &middot; <?= e($profile['age'] ?? 'Age not set') ?> years</p></div>
                                    </div>
                                    <div class="hd-profile-right"><span class="dt-badge badge-active">Active</span></div>
                                </div>
                                <div class="metrics-grid">
                                    <div class="metric-box"><div class="metric-header text-green"><span>BMI</span></div><div class="metric-value"><?= e($bmiData['bmi']) ?> <small><?= e($bmiData['category']) ?></small></div></div>
                                    <div class="metric-box"><div class="metric-header text-blue"><span>Weight</span></div><div class="metric-value"><?= e($profile['weight'] ?? 'Not set') ?> kg</div></div>
                                    <div class="metric-box"><div class="metric-header text-blue"><span>Height</span></div><div class="metric-value"><?= e($profile['height'] ?? 'Not set') ?> cm</div></div>
                                    <div class="metric-box"><div class="metric-header text-blue"><span>Water, last 7 days</span></div><div class="metric-value"><?= number_format($waterMl / 1000, 1) ?> L</div></div>
                                    <div class="metric-box"><div class="metric-header text-orange"><span>Average calories</span></div><div class="metric-value"><?= $averageCalories === null ? 'No logs' : number_format((float)$averageCalories) . ' kcal' ?></div></div>
                                    <div class="metric-box"><div class="metric-header text-green"><span>Daily calorie target</span></div><div class="metric-value"><?= e($profile['daily_calorie_target'] ?? 'Not set') ?> kcal</div></div>
                                    <div class="metric-box"><div class="metric-header text-blue"><span>Latest sleep</span></div><div class="metric-value"><?= $latestSleep === false ? 'No logs' : number_format((float)$latestSleep / 60, 1) . ' h' ?></div></div>
                                    <div class="metric-box"><div class="metric-header text-orange"><span>Activity level</span></div><div class="metric-value"><?= e(ucwords(str_replace('_', ' ', $profile['activity_level'] ?? 'Not set'))) ?></div></div>
                                </div>
                                <div class="conditions-grid">
                                    <div class="condition-box"><div class="cond-title">Dietary Preference</div><div class="tag-badge bg-green-dim text-green"><?= e(ucfirst($profile['dietary_preference'] ?? 'Not set')) ?></div></div>
                                    <div class="condition-box"><div class="cond-title">Health Goal</div><div class="tag-badge bg-orange-dim text-orange"><?= e(ucwords(str_replace('_', ' ', $profile['health_goal'] ?? 'Not set'))) ?></div></div>
                                </div>
                            </div>
                        <?php elseif ($patients): ?>
                            <div class="health-detail-panel"><h2>No profile found</h2><p>This patient has not completed a health profile.</p></div>
                        <?php else: ?>
                            <div class="health-detail-panel"><h2>Patient health profiles</h2><p>Health details are available for active patients with a meal plan or recommendation from you.</p></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>