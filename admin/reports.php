<?php
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');
$currentUser = getCurrentUser();
$pdo = getDBConnection();

$userCounts = $pdo->query('SELECT role, COUNT(*) AS total FROM users GROUP BY role')->fetchAll(PDO::FETCH_KEY_PAIR);
$pendingDietitians = (int)$pdo->query("SELECT COUNT(*) FROM dietitian_profiles WHERE approval_status = 'pending'")->fetchColumn();
$foodCount = (int)$pdo->query('SELECT COUNT(*) FROM food_items')->fetchColumn();
$mealCount = (int)$pdo->query('SELECT COUNT(*) FROM meal_logs')->fetchColumn();
$dailyStmt = $pdo->query(
    'SELECT meal_date, COUNT(*) AS entries, COUNT(DISTINCT user_id) AS users, SUM(calories) AS calories
     FROM meal_logs WHERE meal_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY meal_date ORDER BY meal_date'
);
$dailyRows = [];
foreach ($dailyStmt->fetchAll() as $row) {
    $dailyRows[$row['meal_date']] = $row;
}
$dailyReport = [];
for ($offset = 6; $offset >= 0; $offset--) {
    $date = date('Y-m-d', strtotime("-{$offset} days"));
    $dailyReport[] = $dailyRows[$date] ?? ['meal_date' => $date, 'entries' => 0, 'users' => 0, 'calories' => 0];
}
$topFoods = $pdo->query(
    'SELECT COALESCE(f.food_name, m.custom_food_name) AS food_name, COUNT(*) AS entries, SUM(m.calories) AS calories
     FROM meal_logs m LEFT JOIN food_items f ON f.id = m.food_id
     GROUP BY food_name ORDER BY entries DESC, food_name LIMIT 10'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Reports - Health Track</title>
    <link rel="stylesheet" href="../dashboard.css">
</head>
<body>
    <div class="dashboard-layout">
        <aside class="sidebar admin-sidebar">
            <div class="sidebar-header"><span class="logo-text-small">Health Track Admin</span></div>
            <nav class="sidebar-nav admin-nav">
                <a href="dashboard.php" class="nav-link admin-nav-link">Dashboard</a>
                <a href="manage-users.php" class="nav-link admin-nav-link">Manage Users</a>
                <a href="approve-dietitians.php" class="nav-link admin-nav-link">Approve Dietitians</a>
                <a href="foods.php" class="nav-link admin-nav-link">Food Database</a>
                <a href="reports.php" class="nav-link admin-nav-link active">Reports</a>
            </nav>
            <div class="sidebar-footer"><div><?= e($currentUser['name']) ?></div><a href="../logout.php" class="nav-link sign-out">Sign Out</a></div>
        </aside>
        <main class="main-content">
            <header class="top-header"><?php renderBackButton('dashboard.php'); ?><h1 class="header-title">System Reports</h1></header>
            <div class="content-wrapper">
                <div class="summary-cards-grid">
                    <div class="summary-card"><div class="summary-value text-orange"><?= (int)($userCounts['user'] ?? 0) ?></div><div class="summary-label">Users</div></div>
                    <div class="summary-card"><div class="summary-value text-blue"><?= (int)($userCounts['dietitian'] ?? 0) ?></div><div class="summary-label">Dietitians</div></div>
                    <div class="summary-card"><div class="summary-value"><?= $pendingDietitians ?></div><div class="summary-label">Applications Pending</div></div>
                    <div class="summary-card"><div class="summary-value text-green"><?= $foodCount ?></div><div class="summary-label">Foods</div></div>
                </div>
                <div class="form-card">
                    <h2 class="card-title">Meal Activity, Last 7 Days (<?= $mealCount ?> total logged meals)</h2>
                    <div style="overflow-x:auto;"><table class="report-table"><thead><tr><th>Date</th><th>Meal entries</th><th>Users logging</th><th>Calories</th></tr></thead><tbody>
                        <?php foreach ($dailyReport as $day): ?><tr><td><?= e(date('D, M j', strtotime($day['meal_date']))) ?></td><td><?= (int)$day['entries'] ?></td><td><?= (int)$day['users'] ?></td><td><?= number_format((float)$day['calories']) ?> kcal</td></tr><?php endforeach; ?>
                    </tbody></table></div>
                </div>
                <div class="form-card">
                    <h2 class="card-title">Most Logged Foods</h2>
                    <?php if (!$topFoods): ?><p>No meal records exist yet.</p><?php else: ?><div style="overflow-x:auto;"><table class="report-table"><thead><tr><th>Food</th><th>Meal entries</th><th>Calories logged</th></tr></thead><tbody>
                        <?php foreach ($topFoods as $food): ?><tr><td><?= e($food['food_name'] ?? 'Unknown food') ?></td><td><?= (int)$food['entries'] ?></td><td><?= number_format((float)$food['calories']) ?> kcal</td></tr><?php endforeach; ?>
                    </tbody></table></div><?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>