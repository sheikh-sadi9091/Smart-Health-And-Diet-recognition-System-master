<?php
require_once __DIR__ . '/../includes/auth.php';

requireRole('user');
$currentUser = getCurrentUser();
$pdo = getDBConnection();

$targetStmt = $pdo->prepare('SELECT daily_calorie_target FROM health_profiles WHERE user_id = :user_id LIMIT 1');
$targetStmt->execute(['user_id' => $currentUser['id']]);
$dailyTarget = (int)($targetStmt->fetchColumn() ?: 2000);

$reportStmt = $pdo->prepare(
    'SELECT meal_date, SUM(calories) AS calories FROM meal_logs
     WHERE user_id = :user_id AND meal_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY meal_date ORDER BY meal_date'
);
$reportStmt->execute(['user_id' => $currentUser['id']]);
$loggedCalories = [];
foreach ($reportStmt->fetchAll() as $row) {
    $loggedCalories[$row['meal_date']] = (int)round((float)$row['calories']);
}

$days = [];
$totalCalories = 0;
$daysOnTarget = 0;
for ($offset = 6; $offset >= 0; $offset--) {
    $date = date('Y-m-d', strtotime("-{$offset} days"));
    $calories = $loggedCalories[$date] ?? 0;
    $days[] = ['date' => $date, 'calories' => $calories];
    $totalCalories += $calories;
    if ($calories > 0 && $calories <= $dailyTarget) {
        $daysOnTarget++;
    }
}
$averageCalories = (int)round($totalCalories / count($days));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calorie Reports - Health Track</title>
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
                <a href="calorie-reports.php" class="nav-link active">Calorie Reports</a>
                <a href="messages.php" class="nav-link">Messages</a>
            </nav>
            <div class="sidebar-footer"><div><?= e($currentUser['name']) ?></div><a href="../logout.php" class="nav-link sign-out">Sign Out</a></div>
        </aside>
        <main class="main-content">
            <header class="top-header"><?php renderBackButton('dashboard.php'); ?><h1 class="header-title">Calorie Reports</h1></header>
            <div class="content-wrapper">
                <div class="form-card">
                    <h2 class="card-title">Last 7 Days</h2>
                    <div style="overflow-x:auto;"><table class="report-table">
                        <thead><tr><th>Day</th><th>Consumed</th><th>Daily target</th><th>Difference</th><th>Status</th></tr></thead>
                        <tbody><?php foreach ($days as $day): $difference = $day['calories'] - $dailyTarget; ?><tr>
                            <td><?= e(date('D, M j', strtotime($day['date']))) ?></td>
                            <td><?= number_format($day['calories']) ?> kcal</td>
                            <td><?= number_format($dailyTarget) ?> kcal</td>
                            <td><?= $difference > 0 ? '+' : '' ?><?= number_format($difference) ?> kcal</td>
                            <td><?= $day['calories'] === 0 ? 'No meals logged' : ($difference <= 0 ? 'On target' : 'Over target') ?></td>
                        </tr><?php endforeach; ?></tbody>
                    </table></div>
                </div>
                <div class="summary-cards-grid">
                    <div class="summary-card"><div class="summary-value text-orange"><?= number_format($averageCalories) ?></div><div class="summary-unit">kcal / day</div><div class="summary-label">Average Consumed</div></div>
                    <div class="summary-card"><div class="summary-value text-green"><?= number_format($dailyTarget) ?></div><div class="summary-unit">kcal / day</div><div class="summary-label">Daily Target</div></div>
                    <div class="summary-card"><div class="summary-value"><?= $daysOnTarget ?>/7</div><div class="summary-unit">days</div><div class="summary-label">Days On Target</div></div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>