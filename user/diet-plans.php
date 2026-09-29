<?php
require_once __DIR__ . '/../includes/auth.php';

requireRole('user');
$currentUser = getCurrentUser();
$pdo = getDBConnection();
$plansStmt = $pdo->prepare(
    'SELECT p.*, d.name AS dietitian_name FROM meal_plans p
     JOIN users d ON d.id = p.dietitian_id WHERE p.user_id = :user_id
     ORDER BY p.start_date DESC, p.created_at DESC'
);
$plansStmt->execute(['user_id' => $currentUser['id']]);
$plans = $plansStmt->fetchAll();
$itemsStmt = $pdo->prepare(
    'SELECT i.*, f.food_name FROM meal_plan_items i LEFT JOIN food_items f ON f.id = i.food_id
     WHERE i.meal_plan_id = :plan_id ORDER BY i.id'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Diet Plans - Health Track</title>
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
                <a href="diet-plans.php" class="nav-link active">My Diet Plans</a>
                <a href="messages.php" class="nav-link">Messages</a>
            </nav>
            <div class="sidebar-footer"><div><?= e($currentUser['name']) ?></div><a href="../logout.php" class="nav-link sign-out">Sign Out</a></div>
        </aside>
        <main class="main-content">
            <header class="top-header"><?php renderBackButton('dashboard.php'); ?><h1 class="header-title">My Diet Plans</h1></header>
            <div class="content-wrapper">
                <?php if (!$plans): ?><div class="form-card"><h2 class="card-title">No plans yet</h2><p>Your dietitian's meal plans will appear here.</p></div><?php else: ?>
                    <?php foreach ($plans as $plan): ?>
                        <section class="form-card">
                            <h2 class="card-title"><?= e($plan['title']) ?></h2>
                            <p><?= e($plan['description']) ?></p>
                            <p><strong>Dietitian:</strong> <?= e($plan['dietitian_name']) ?> &nbsp; <strong>Goal:</strong> <?= e($plan['goal'] ?: 'Not specified') ?></p>
                            <p><strong>Dates:</strong> <?= e($plan['start_date']) ?> to <?= e($plan['end_date']) ?> &nbsp; <strong>Status:</strong> <?= e(ucfirst($plan['status'])) ?></p>
                            <?php $itemsStmt->execute(['plan_id' => $plan['id']]); $items = $itemsStmt->fetchAll(); ?>
                            <?php if ($items): ?><div style="overflow-x:auto;"><table class="report-table"><thead><tr><th>Meal</th><th>Food</th><th>Quantity</th><th>Calories</th><th>Notes</th></tr></thead><tbody>
                                <?php foreach ($items as $item): ?><tr><td><?= e($item['meal_type']) ?></td><td><?= e($item['food_name'] ?? $item['custom_food_name']) ?></td><td><?= e($item['quantity']) ?></td><td><?= e($item['calories']) ?> kcal</td><td><?= e($item['notes']) ?></td></tr><?php endforeach; ?>
                            </tbody></table></div><?php else: ?><p>Your dietitian has not added individual meal items to this plan yet.</p><?php endif; ?>
                        </section>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>