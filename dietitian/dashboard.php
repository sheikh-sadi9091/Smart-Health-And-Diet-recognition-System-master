<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('dietitian');
$currentUser = getCurrentUser();

$pdo = getDBConnection();

// Fetch dietitian profile details
$stmt = $pdo->prepare("SELECT * FROM dietitian_profiles WHERE user_id = :uid LIMIT 1");
$stmt->execute(['uid' => $currentUser['id']]);
$profile = $stmt->fetch();

// Dynamic metrics
$mealPlansStmt = $pdo->prepare("SELECT COUNT(*) FROM meal_plans WHERE dietitian_id = :dietitian_id AND status = 'active'");
$mealPlansStmt->execute(['dietitian_id' => $currentUser['id']]);
$mealPlansCount = (int)$mealPlansStmt->fetchColumn();
$assignedUsersStmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT u.id) FROM users u
     WHERE u.role = 'user' AND u.status = 'active'
       AND (EXISTS (SELECT 1 FROM meal_plans p WHERE p.user_id = u.id AND p.dietitian_id = :plan_dietitian)
         OR EXISTS (SELECT 1 FROM recommendations r WHERE r.user_id = u.id AND r.dietitian_id = :recommendation_dietitian))"
);
$assignedUsersStmt->execute([
    'plan_dietitian' => $currentUser['id'],
    'recommendation_dietitian' => $currentUser['id'],
]);
$assignedUsersCount = (int)$assignedUsersStmt->fetchColumn();
$recommendationsCount = (int)$pdo->query("SELECT COUNT(*) FROM recommendations WHERE dietitian_id = " . (int)$currentUser['id'])->fetchColumn();
$totalGuidesCount = (int)$pdo->query("SELECT COUNT(*) FROM resources WHERE dietitian_id = " . (int)$currentUser['id'])->fetchColumn();

$patientQueueStmt = $pdo->prepare(
    "SELECT u.id, u.name, hp.health_goal, hp.daily_calorie_target,
            COALESCE(today.calories, 0) AS today_calories
     FROM users u
     LEFT JOIN health_profiles hp ON hp.user_id = u.id
     LEFT JOIN (
         SELECT user_id, SUM(calories) AS calories FROM meal_logs
         WHERE meal_date = CURDATE() GROUP BY user_id
     ) today ON today.user_id = u.id
     WHERE u.role = 'user' AND u.status = 'active'
       AND (EXISTS (SELECT 1 FROM meal_plans p WHERE p.user_id = u.id AND p.dietitian_id = :plan_dietitian)
         OR EXISTS (SELECT 1 FROM recommendations r WHERE r.user_id = u.id AND r.dietitian_id = :recommendation_dietitian))
     ORDER BY (COALESCE(today.calories, 0) > COALESCE(hp.daily_calorie_target, 2000)) DESC, u.name
     LIMIT 5"
);
$patientQueueStmt->execute([
    'plan_dietitian' => $currentUser['id'],
    'recommendation_dietitian' => $currentUser['id'],
]);
$patientQueue = $patientQueueStmt->fetchAll();

$recentActivityStmt = $pdo->prepare(
    "SELECT u.id, u.name, MAX(m.meal_date) AS latest_meal_date,
            SUM(CASE WHEN m.meal_date = CURDATE() THEN m.calories ELSE 0 END) AS today_calories
     FROM users u JOIN meal_logs m ON m.user_id = u.id
     WHERE u.role = 'user' AND u.status = 'active'
       AND m.meal_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
       AND (EXISTS (SELECT 1 FROM meal_plans p WHERE p.user_id = u.id AND p.dietitian_id = :plan_dietitian)
         OR EXISTS (SELECT 1 FROM recommendations r WHERE r.user_id = u.id AND r.dietitian_id = :recommendation_dietitian))
     GROUP BY u.id, u.name ORDER BY latest_meal_date DESC LIMIT 4"
);
$recentActivityStmt->execute([
    'plan_dietitian' => $currentUser['id'],
    'recommendation_dietitian' => $currentUser['id'],
]);
$recentActivity = $recentActivityStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Track - Dietitian Dashboard</title>
    <link rel="stylesheet" href="../dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="dashboard-layout">
        
        <!-- Sidebar Navigation (Dietitian Portal) -->
        <aside class="sidebar dietitian-sidebar">
            <div class="sidebar-header">
                <div class="logo-container small-logo">
                    <div class="filled-heart-icon">
                        <svg viewBox="0 0 24 24" fill="white" width="16" height="16">
                            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                        </svg>
                    </div>
                    <div class="sidebar-title-group">
                        <span class="logo-text-small">Health Track</span>
                        <span class="sidebar-subtext">Dietitian Portal</span>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav dietitian-nav">
                <a href="dashboard.php" class="nav-link dietitian-nav-link active">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    Dashboard
                </a>
                <a href="meal-plans.php" class="nav-link dietitian-nav-link">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    Meal Plans
                </a>
                <a href="health-info.php" class="nav-link dietitian-nav-link">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                    Health Information
                </a>
                <a href="recommendations.php" class="nav-link dietitian-nav-link">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    Recommendations
                </a>
                <a href="messages.php" class="nav-link dietitian-nav-link">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                    Chat
                </a>
                <a href="../dietitian-guides.html" class="nav-link dietitian-nav-link">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                    Nutritional Guides
                </a>
            </nav>

            <div class="sidebar-footer border-top-footer">
                <div class="user-profile-compact">
                    <div class="avatar avatar-blue">
                        <?= strtoupper(substr($currentUser['name'], 0, 2)) ?>
                    </div>
                    <div class="profile-text">
                        <span class="profile-name"><?= e($currentUser['name']) ?></span>
                        <span class="profile-role"><?= e($profile['specialization'] ?? 'Registered Dietitian') ?></span>
                    </div>
                </div>
                <a href="../logout.php" class="nav-link sign-out">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    Sign Out
                </a>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content dietitian-main">
            <header class="page-header-clean">
                <div class="page-header-title-row"><?php renderBackButton(); ?><h1 class="page-main-title">Dietitian Dashboard</h1></div>
                <p class="page-sub-title">Welcome back, <?= e($currentUser['name']) ?></p>
            </header>

            <div class="content-wrapper full-width-wrap">
                <?php renderFlashMessage(); ?>
                
                <!-- Top KPI Grid -->
                <div class="dietitian-kpi-grid">
                    <div class="dt-kpi-card">
                        <div class="kpi-icon-box bg-orange-light text-orange">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                        </div>
                        <div class="dt-kpi-number"><?= $mealPlansCount ?></div>
                        <div class="dt-kpi-label">Active Meal Plans</div>
                    </div>

                    <div class="dt-kpi-card">
                        <div class="kpi-icon-box bg-teal-light text-teal">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                        </div>
                        <div class="dt-kpi-number"><?= $assignedUsersCount ?></div>
                        <div class="dt-kpi-label">Registered Patients</div>
                    </div>

                    <div class="dt-kpi-card">
                        <div class="kpi-icon-box bg-blue-light text-blue">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                        </div>
                        <div class="dt-kpi-number"><?= $recommendationsCount ?></div>
                        <div class="dt-kpi-label">Recommendations Sent</div>
                    </div>

                    <div class="dt-kpi-card">
                        <div class="kpi-icon-box bg-green-light text-green">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                        </div>
                        <div class="dt-kpi-number"><?= $totalGuidesCount ?></div>
                        <div class="dt-kpi-label">Nutritional Guides</div>
                    </div>
                </div>

                <!-- Split Grid Layout -->
                <div class="dashboard-split-grid">
                    
                    <!-- Left: Needs Attention -->
                    <div class="panel-card">
                        <h3 class="panel-title flex-title">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#f2500c" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                            Patient Priority Queue
                        </h3>
                        <div class="attention-list">
                            <?php if (!$patientQueue): ?>
                                <p>No patients are assigned to your care yet.</p>
                            <?php else: ?>
                                <?php foreach ($patientQueue as $patient): ?>
                                    <?php
                                    $target = (int)($patient['daily_calorie_target'] ?: 2000);
                                    $overTarget = (int)$patient['today_calories'] > $target;
                                    ?>
                                    <div class="attention-item">
                                        <div class="att-text">
                                            <h4><?= e($patient['name']) ?></h4>
                                            <p><?= number_format((int)$patient['today_calories']) ?> / <?= number_format($target) ?> kcal today · Goal: <?= e(ucwords(str_replace('_', ' ', $patient['health_goal'] ?? 'Not set'))) ?></p>
                                        </div>
                                        <a href="health-info.php?user_id=<?= (int)$patient['id'] ?>" class="action-pill <?= $overTarget ? 'pill-orange' : 'pill-blue' ?>">Review Vitals</a>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Right: Today's Consultation Schedule -->
                    <div class="panel-card">
                        <h3 class="panel-title flex-title">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#3498db" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            Dietary Consultations
                        </h3>
                        <div class="schedule-timeline">
                            <?php if (!$recentActivity): ?>
                                <p>No meal activity from assigned patients during the last seven days.</p>
                            <?php else: ?>
                                <?php foreach ($recentActivity as $activity): ?>
                                    <div class="timeline-item">
                                        <div class="tl-dot bg-dot-blue"></div>
                                        <div class="tl-content">
                                            <span class="tl-time">Latest meal: <?= e(date('M j', strtotime($activity['latest_meal_date']))) ?></span>
                                            <span class="tl-name"><?= e($activity['name']) ?></span>
                                            <span class="tl-desc"><?= number_format((int)$activity['today_calories']) ?> kcal logged today</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

            </div>
        </main>
    </div>
</body>
</html>
