<?php
require_once __DIR__ . '/../includes/auth.php';

requireRole('user');
$currentUser = getCurrentUser();
$pdo = getDBConnection();
$error = '';

$profileStmt = $pdo->prepare('SELECT * FROM health_profiles WHERE user_id = :user_id LIMIT 1');
$profileStmt->execute(['user_id' => $currentUser['id']]);
$profile = $profileStmt->fetch() ?: [
    'age' => 25,
    'gender' => 'other',
    'height' => 170,
    'weight' => 70,
    'activity_level' => 'moderate',
    'health_goal' => 'maintain_weight',
    'dietary_preference' => 'anything',
];

$activities = ['sedentary', 'light', 'moderate', 'very_active', 'extra_active'];
$goals = ['weight_loss', 'weight_gain', 'maintain_weight', 'general_fitness'];
$diets = ['anything', 'vegetarian', 'vegan', 'keto', 'halal', 'paleo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $age = filter_var($_POST['age'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 13, 'max_range' => 120]]);
    $height = filter_var($_POST['height'] ?? null, FILTER_VALIDATE_FLOAT);
    $weight = filter_var($_POST['weight'] ?? null, FILTER_VALIDATE_FLOAT);
    $gender = requestString($_POST['gender'] ?? null);
    $activity = requestString($_POST['activity_level'] ?? null);
    $goal = requestString($_POST['health_goal'] ?? null);
    $diet = requestString($_POST['dietary_preference'] ?? null);
    $csrfToken = requestString($_POST['csrf_token'] ?? null);

    if (!isValidCsrfToken($csrfToken)) {
        $error = 'Your session expired. Please submit the form again.';
    } elseif ($age === false || $height === false || $height < 80 || $height > 250 || $weight === false || $weight < 25 || $weight > 400) {
        $error = 'Enter a valid age, height, and weight.';
    } elseif (!in_array($gender, ['male', 'female', 'other'], true) || !in_array($activity, $activities, true) || !in_array($goal, $goals, true) || !in_array($diet, $diets, true)) {
        $error = 'Choose valid options for your profile.';
    } else {
        $bmi = calculateBMI((float)$weight, (float)$height)['bmi'];
        $calorieTarget = calculateDailyCalorieTarget((float)$weight, (float)$height, (int)$age, $gender, $activity, $goal);
        $saveProfile = $pdo->prepare(
            'INSERT INTO health_profiles (user_id, age, gender, height, weight, activity_level, health_goal, dietary_preference, daily_calorie_target, bmi)
             VALUES (:user_id, :age, :gender, :height, :weight, :activity_level, :health_goal, :dietary_preference, :daily_calorie_target, :bmi)
             ON DUPLICATE KEY UPDATE age = VALUES(age), gender = VALUES(gender), height = VALUES(height), weight = VALUES(weight),
             activity_level = VALUES(activity_level), health_goal = VALUES(health_goal), dietary_preference = VALUES(dietary_preference),
             daily_calorie_target = VALUES(daily_calorie_target), bmi = VALUES(bmi)'
        );
        $saveProfile->execute([
            'user_id' => $currentUser['id'],
            'age' => $age,
            'gender' => $gender,
            'height' => $height,
            'weight' => $weight,
            'activity_level' => $activity,
            'health_goal' => $goal,
            'dietary_preference' => $diet,
            'daily_calorie_target' => $calorieTarget,
            'bmi' => $bmi,
        ]);
        setFlashMessage('success', 'Health information saved. Your daily calorie target is ' . $calorieTarget . ' kcal.');
        header('Location: health-info.php');
        exit;
    }

    $profile = array_merge($profile, [
        'age' => requestString($_POST['age'] ?? null), 'gender' => $gender, 'height' => requestString($_POST['height'] ?? null), 'weight' => requestString($_POST['weight'] ?? null),
        'activity_level' => $activity, 'health_goal' => $goal, 'dietary_preference' => $diet,
    ]);
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Information - Health Track</title>
    <link rel="stylesheet" href="../dashboard.css">
</head>
<body>
    <div class="dashboard-layout">
        <aside class="sidebar">
            <div class="sidebar-header"><span class="logo-text-small">Health Track</span></div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-link">Overview</a>
                <a href="health-info.php" class="nav-link active">Health Information</a>
                <a href="log-meals.php" class="nav-link">Log Meals</a>
                <a href="calorie-reports.php" class="nav-link">Calorie Reports</a>
                <a href="messages.php" class="nav-link">Messages</a>
            </nav>
            <div class="sidebar-footer">
                <div><?= e($currentUser['name']) ?></div>
                <a href="../logout.php" class="nav-link sign-out">Sign Out</a>
            </div>
        </aside>
        <main class="main-content">
            <header class="top-header"><?php renderBackButton('dashboard.php'); ?><h1 class="header-title">Health Information</h1></header>
            <div class="content-wrapper">
                <?php renderFlashMessage(); ?>
                <?php if ($error !== ''): ?><p role="alert" class="form-card" style="color:#e74c3c;"><?= e($error) ?></p><?php endif; ?>
                <form class="health-info-form" method="post" action="health-info.php">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <div class="form-card">
                        <h2 class="card-title">Basic Information</h2>
                        <div class="input-grid">
                            <div class="input-group"><label for="age">Age</label><input id="age" name="age" type="number" min="13" max="120" value="<?= e($profile['age']) ?>" required></div>
                            <div class="input-group"><label for="weight">Current weight (kg)</label><input id="weight" name="weight" type="number" min="25" max="400" step="0.1" value="<?= e($profile['weight']) ?>" required></div>
                            <div class="input-group"><label for="height">Height (cm)</label><input id="height" name="height" type="number" min="80" max="250" step="0.1" value="<?= e($profile['height']) ?>" required></div>
                            <div class="input-group"><label for="gender">Gender</label><select id="gender" name="gender"><option value="female" <?= $profile['gender'] === 'female' ? 'selected' : '' ?>>Female</option><option value="male" <?= $profile['gender'] === 'male' ? 'selected' : '' ?>>Male</option><option value="other" <?= $profile['gender'] === 'other' ? 'selected' : '' ?>>Other</option></select></div>
                        </div>
                    </div>
                    <div class="form-card">
                        <h2 class="card-title">Goals & Preferences</h2>
                        <div class="input-grid">
                            <div class="input-group"><label for="health_goal">Health goal</label><select id="health_goal" name="health_goal"><?php foreach ($goals as $value): ?><option value="<?= e($value) ?>" <?= $profile['health_goal'] === $value ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $value))) ?></option><?php endforeach; ?></select></div>
                            <div class="input-group"><label for="activity_level">Activity level</label><select id="activity_level" name="activity_level"><?php foreach ($activities as $value): ?><option value="<?= e($value) ?>" <?= $profile['activity_level'] === $value ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $value))) ?></option><?php endforeach; ?></select></div>
                            <div class="input-group"><label for="dietary_preference">Dietary preference</label><select id="dietary_preference" name="dietary_preference"><?php foreach ($diets as $value): ?><option value="<?= e($value) ?>" <?= $profile['dietary_preference'] === $value ? 'selected' : '' ?>><?= e(ucwords($value)) ?></option><?php endforeach; ?></select></div>
                        </div>
                    </div>
                    <button type="submit" class="primary-btn submit-info-btn">Save Health Information</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>