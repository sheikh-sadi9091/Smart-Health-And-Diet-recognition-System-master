<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('user');
$currentUser = getCurrentUser();

$pdo = getDBConnection();
$userId = (int)$currentUser['id'];

// Fetch user health profile
$stmt = $pdo->prepare("SELECT * FROM health_profiles WHERE user_id = :uid LIMIT 1");
$stmt->execute(['uid' => $userId]);
$profile = $stmt->fetch();

// Default values if profile not yet populated
$weight = (float)($profile['weight'] ?? 65.0);
$height = (float)($profile['height'] ?? 170.0);
$targetCalories = (int)($profile['daily_calorie_target'] ?? 2000);
$goal = $profile['health_goal'] ?? 'maintain_weight';
$bmiData = calculateBMI($weight, $height);

// Today's meal calorie total
$stmt = $pdo->prepare("SELECT COALESCE(SUM(calories), 0) FROM meal_logs WHERE user_id = :uid AND meal_date = CURDATE()");
$stmt->execute(['uid' => $userId]);
$todayCalories = (int)$stmt->fetchColumn();

// Today's water total
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount_ml), 0) FROM water_logs WHERE user_id = :uid AND log_date = CURDATE()");
$stmt->execute(['uid' => $userId]);
$todayWaterMl = (int)$stmt->fetchColumn();
$waterTargetMl = 2500;
$waterPercent = min(100, round(($todayWaterMl / $waterTargetMl) * 100));

// Latest sleep duration
$stmt = $pdo->prepare("SELECT duration_minutes FROM sleep_logs WHERE user_id = :uid ORDER BY sleep_date DESC LIMIT 1");
$stmt->execute(['uid' => $userId]);
$latestSleepMinutes = $stmt->fetchColumn();
$sleepDisplay = $latestSleepMinutes ? round($latestSleepMinutes / 60, 1) . ' hrs' : '7.5 hrs';

// Recent meals
$stmt = $pdo->prepare("
    SELECT m.*, f.food_name 
    FROM meal_logs m 
    LEFT JOIN food_items f ON m.food_id = f.id 
    WHERE m.user_id = :uid 
    ORDER BY m.meal_date DESC, m.meal_time DESC 
    LIMIT 4
");
$stmt->execute(['uid' => $userId]);
$recentMeals = $stmt->fetchAll();

// Latest dietitian recommendation
$stmt = $pdo->prepare("
    SELECT r.*, u.name as dietitian_name 
    FROM recommendations r 
    JOIN users u ON r.dietitian_id = u.id 
    WHERE r.user_id = :uid 
    ORDER BY r.created_at DESC 
    LIMIT 1
");
$stmt->execute(['uid' => $userId]);
$latestRecommendation = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Track - User Dashboard</title>
    <link rel="stylesheet" href="../dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="dashboard-layout">
        
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo-container small-logo">
                    <div class="filled-heart-icon">
                        <svg viewBox="0 0 24 24" fill="white" width="16" height="16">
                            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                        </svg>
                    </div>
                    <span class="logo-text-small">Health Track</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-link active">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    Overview
                </a>
                <a href="health-info.php" class="nav-link">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                    Health Information
                </a>
                <a href="log-meals.php" class="nav-link">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg>
                    Log Meals
                </a>
                <a href="calorie-reports.php" class="nav-link">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10M12 20V4M6 20v-6"></path></svg>
                    Calorie Reports
                </a>
                <a href="diet-plans.php" class="nav-link">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                    My Diet Plans
                </a>
                <a href="messages.php" class="nav-link">Messages</a>
            </nav>

            <div class="sidebar-footer">
                <div style="margin-bottom: 16px; padding: 0 4px;">
                    <div style="font-size: 14px; font-weight: 600; color: var(--text-light);"><?= e($currentUser['name']) ?></div>
                    <div style="font-size: 12px; color: var(--text-muted);"><?= e($currentUser['email']) ?></div>
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
        <main class="main-content">
            <header class="top-header">
                <?php renderBackButton(); ?>
                <h1 class="header-title">Welcome back, <?= e($currentUser['name']) ?></h1>
            </header>

            <div class="content-wrapper" style="max-width: 1200px;">
                <?php renderFlashMessage(); ?>

                <!-- Medical Disclaimer Alert -->
                <div style="background-color: rgba(52, 152, 219, 0.08); border-left: 4px solid #3498db; border-radius: 8px; padding: 12px 18px; margin-bottom: 24px; font-size: 13px; color: #b0b4bd; line-height: 1.5;">
                    <strong style="color: #3498db;">Educational Notice:</strong> This system provides general health and nutrition tracking and should not replace professional advice from a qualified healthcare practitioner.
                </div>

                <!-- Vital Metrics Grid -->
                <div class="summary-cards-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 30px;">
                    
                    <!-- Current Weight Card -->
                    <div class="summary-card">
                        <div class="summary-value text-orange"><?= number_format($weight, 1) ?></div>
                        <div class="summary-unit">kg</div>
                        <div class="summary-label">Current Weight</div>
                    </div>

                    <!-- BMI Card -->
                    <div class="summary-card">
                        <div class="summary-value" style="color: <?= $bmiData['color'] ?>;"><?= $bmiData['bmi'] ?></div>
                        <div class="summary-unit"><?= $bmiData['category'] ?></div>
                        <div class="summary-label">Body Mass Index</div>
                    </div>

                    <!-- Today's Calorie Progress -->
                    <div class="summary-card">
                        <div class="summary-value text-green"><?= $todayCalories ?></div>
                        <div class="summary-unit">Target: <?= $targetCalories ?> kcal</div>
                        <div class="summary-label">Today's Calories</div>
                    </div>

                    <!-- Water Intake -->
                    <div class="summary-card">
                        <div class="summary-value text-blue"><?= $todayWaterMl ?></div>
                        <div class="summary-unit"><?= $waterPercent ?>% of 2,500 ml</div>
                        <div class="summary-label">Water Intake</div>
                    </div>

                </div>

                <!-- Split Section: Quick Actions & Highlights -->
                <div style="display: grid; grid-template-columns: 2fr 1.2fr; gap: 24px; margin-bottom: 30px;">
                    
                    <!-- Left: Recent Meal Summary / Quick Log -->
                    <div class="form-card" style="margin-bottom: 0;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h3 class="card-title" style="margin-bottom: 0;">Recent Meal Log</h3>
                            <a href="log-meals.php" class="add-text-btn">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                Add Meal
                            </a>
                        </div>

                        <?php if (empty($recentMeals)): ?>
                            <div style="background-color: var(--input-bg); border-radius: 12px; padding: 24px; text-align: center; color: var(--text-muted); font-size: 14px;">
                                No meals logged today yet. Click <strong>Add Meal</strong> or visit <a href="log-meals.php" style="color: var(--primary-orange); text-decoration: none;">Log Meals</a> to record your breakfast, lunch, or dinner.
                            </div>
                        <?php else: ?>
                            <div style="display: flex; flex-direction: column; gap: 12px;">
                                <?php foreach ($recentMeals as $meal): ?>
                                    <div style="background-color: var(--bg-dark); border-radius: 10px; padding: 14px 18px; display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <div style="font-size: 14px; font-weight: 600; color: var(--text-light);"><?= e($meal['food_name'] ?? $meal['custom_food_name']) ?></div>
                                            <div style="font-size: 12px; color: var(--text-muted);"><?= e($meal['meal_type']) ?> • <?= e($meal['meal_time']) ?></div>
                                        </div>
                                        <div style="text-align: right;">
                                            <span style="font-size: 16px; font-weight: 700; color: #2ecc71;"><?= e($meal['calories']) ?></span>
                                            <span style="font-size: 11px; color: var(--text-muted);"> kcal</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Right: Dietitian Recommendation & Sleep Tracker -->
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        
                        <!-- Dietitian Card -->
                        <div class="panel-card" style="padding: 20px;">
                            <h4 style="font-size: 15px; font-weight: 600; color: var(--text-light); margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#2ecc71" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                                Dietitian Guidance
                            </h4>
                            <?php if ($latestRecommendation): ?>
                                <p style="font-size: 13px; color: #b0b4bd; line-height: 1.5; margin-bottom: 10px;">
                                    "<?= e($latestRecommendation['recommendation']) ?>"
                                </p>
                                <div style="font-size: 11px; color: var(--text-muted); text-align: right;">
                                    — Dr. <?= e($latestRecommendation['dietitian_name']) ?>
                                </div>
                            <?php else: ?>
                                <p style="font-size: 13px; color: var(--text-muted); line-height: 1.5;">
                                    Maintain healthy hydration and balanced protein with each meal. Your assigned nutritionist will review your vitals weekly.
                                </p>
                            <?php endif; ?>
                        </div>

                        <!-- Sleep & Recovery Box -->
                        <div class="panel-card" style="padding: 20px;">
                            <h4 style="font-size: 15px; font-weight: 600; color: var(--text-light); margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#9b59b6" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                                Sleep & Recovery
                            </h4>
                            <div style="display: flex; justify-content: space-between; align-items: baseline;">
                                <div>
                                    <span style="font-size: 26px; font-weight: 700; color: #9b59b6;"><?= $sleepDisplay ?></span>
                                    <div style="font-size: 12px; color: var(--text-muted);">Recorded Sleep</div>
                                </div>
                                <span class="status-badge status-active">Optimal Rest</span>
                            </div>
                        </div>

                    </div>

                </div>

            </div>
        </main>
    </div>
</body>
</html>
