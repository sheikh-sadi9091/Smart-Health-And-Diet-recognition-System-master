<?php
require_once __DIR__ . '/../includes/auth.php';

requireRole('user');
$currentUser = getCurrentUser();
$pdo = getDBConnection();
$error = '';
$foodItems = $pdo->query('SELECT id, food_name, serving_size, calories, protein, carbohydrates, fat FROM food_items ORDER BY food_name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $mealType = requestString($_POST['meal_type'] ?? null);
    $mealDate = requestString($_POST['meal_date'] ?? null);
    $mealTime = requestString($_POST['meal_time'] ?? null);
    $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_FLOAT);
    $foodId = filter_var(requestString($_POST['food_id'] ?? '0'), FILTER_VALIDATE_INT);

    if (!isValidCsrfToken($csrfToken)) {
        $error = 'Your session expired. Please submit the form again.';
    } elseif (!in_array($mealType, ['Breakfast', 'Lunch', 'Dinner', 'Snack'], true) || $quantity === false || $quantity < 0.1 || $quantity > 30 || $foodId === false) {
        $error = 'Enter a valid meal type and serving quantity.';
    } elseif (!isValidDate($mealDate) || $mealDate > date('Y-m-d') || !is_string($mealTime) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $mealTime)) {
        $error = 'Choose a valid date and time. Future meal dates are not allowed.';
    } else {
        $foodName = null;
        $protein = $carbohydrates = $fat = $calories = 0.0;
        $storedFoodId = null;

        if ($foodId > 0) {
            $foodStmt = $pdo->prepare('SELECT * FROM food_items WHERE id = :id LIMIT 1');
            $foodStmt->execute(['id' => $foodId]);
            $food = $foodStmt->fetch();
            if (!$food) {
                $error = 'Choose a food from the catalog.';
            } else {
                $storedFoodId = (int)$food['id'];
                $foodName = $food['food_name'];
                $protein = (float)$food['protein'] * $quantity;
                $carbohydrates = (float)$food['carbohydrates'] * $quantity;
                $fat = (float)$food['fat'] * $quantity;
                $calories = (float)$food['calories'] * $quantity;
            }
        } else {
            $foodName = trim(requestString($_POST['custom_food_name'] ?? null));
            $protein = filter_var($_POST['protein'] ?? null, FILTER_VALIDATE_FLOAT);
            $carbohydrates = filter_var($_POST['carbohydrates'] ?? null, FILTER_VALIDATE_FLOAT);
            $fat = filter_var($_POST['fat'] ?? null, FILTER_VALIDATE_FLOAT);
            if ($foodName === '' || strlen($foodName) > 150 || $protein === false || $carbohydrates === false || $fat === false || min($protein, $carbohydrates, $fat) < 0 || max($protein, $carbohydrates, $fat) > 500) {
                $error = 'Enter a food name and valid macros between 0 and 500 grams.';
            } else {
                $protein *= $quantity;
                $carbohydrates *= $quantity;
                $fat *= $quantity;
                $calories = ($protein * 4) + ($carbohydrates * 4) + ($fat * 9);
            }
        }

        if ($error === '') {
            $insertMeal = $pdo->prepare(
                'INSERT INTO meal_logs (user_id, food_id, custom_food_name, meal_type, quantity, calories, protein, carbohydrates, fat, meal_date, meal_time)
                 VALUES (:user_id, :food_id, :custom_food_name, :meal_type, :quantity, :calories, :protein, :carbohydrates, :fat, :meal_date, :meal_time)'
            );
            $insertMeal->execute([
                'user_id' => $currentUser['id'],
                'food_id' => $storedFoodId,
                'custom_food_name' => $storedFoodId === null ? $foodName : null,
                'meal_type' => $mealType,
                'quantity' => $quantity,
                'calories' => round($calories, 1),
                'protein' => round($protein, 1),
                'carbohydrates' => round($carbohydrates, 1),
                'fat' => round($fat, 1),
                'meal_date' => $mealDate,
                'meal_time' => $mealTime . ':00',
            ]);
            setFlashMessage('success', 'Meal added to your log.');
            header('Location: log-meals.php');
            exit;
        }
    }
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$mealsStmt = $pdo->prepare(
    'SELECT m.*, f.food_name FROM meal_logs m LEFT JOIN food_items f ON f.id = m.food_id
     WHERE m.user_id = :user_id ORDER BY m.meal_date DESC, m.meal_time DESC LIMIT 30'
);
$mealsStmt->execute(['user_id' => $currentUser['id']]);
$meals = $mealsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Meals - Health Track</title>
    <link rel="stylesheet" href="../dashboard.css">
</head>
<body>
    <div class="dashboard-layout">
        <aside class="sidebar">
            <div class="sidebar-header"><span class="logo-text-small">Health Track</span></div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-link">Overview</a>
                <a href="health-info.php" class="nav-link">Health Information</a>
                <a href="log-meals.php" class="nav-link active">Log Meals</a>
                <a href="calorie-reports.php" class="nav-link">Calorie Reports</a>
                <a href="messages.php" class="nav-link">Messages</a>
            </nav>
            <div class="sidebar-footer"><div><?= e($currentUser['name']) ?></div><a href="../logout.php" class="nav-link sign-out">Sign Out</a></div>
        </aside>
        <main class="main-content">
            <header class="top-header"><?php renderBackButton('dashboard.php'); ?><h1 class="header-title">Log Meals</h1></header>
            <div class="content-wrapper">
                <?php renderFlashMessage(); ?>
                <?php if ($error !== ''): ?><p role="alert" class="form-card" style="color:#e74c3c;"><?= e($error) ?></p><?php endif; ?>
                <form class="health-info-form" method="post" action="log-meals.php">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <div class="form-card">
                        <h2 class="card-title">Add a Meal</h2>
                        <div class="input-grid">
                            <div class="input-group"><label for="meal_type">Meal</label><select id="meal_type" name="meal_type" required><option>Breakfast</option><option>Lunch</option><option>Dinner</option><option>Snack</option></select></div>
                            <div class="input-group"><label for="meal_date">Date</label><input id="meal_date" name="meal_date" type="date" value="<?= e($_POST['meal_date'] ?? date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>" required></div>
                            <div class="input-group"><label for="meal_time">Time</label><input id="meal_time" name="meal_time" type="time" value="<?= e($_POST['meal_time'] ?? date('H:i')) ?>" required></div>
                            <div class="input-group"><label for="food_id">Food from catalog</label><select id="food_id" name="food_id"><option value="0">Custom food</option><?php foreach ($foodItems as $food): ?><option value="<?= (int)$food['id'] ?>" <?= (int)($_POST['food_id'] ?? 0) === (int)$food['id'] ? 'selected' : '' ?>><?= e($food['food_name'] . ' (' . $food['serving_size'] . ')') ?></option><?php endforeach; ?></select></div>
                            <div class="input-group"><label for="quantity">Servings</label><input id="quantity" name="quantity" type="number" min="0.1" max="30" step="0.1" value="<?= e($_POST['quantity'] ?? '1') ?>" required></div>
                            <div class="input-group"><label for="custom_food_name">Custom food name</label><input id="custom_food_name" name="custom_food_name" maxlength="150" value="<?= e($_POST['custom_food_name'] ?? '') ?>"></div>
                            <div class="input-group"><label for="protein">Protein (g per serving)</label><input id="protein" name="protein" type="number" min="0" max="500" step="0.1" value="<?= e($_POST['protein'] ?? '0') ?>"></div>
                            <div class="input-group"><label for="carbohydrates">Carbohydrates (g per serving)</label><input id="carbohydrates" name="carbohydrates" type="number" min="0" max="500" step="0.1" value="<?= e($_POST['carbohydrates'] ?? '0') ?>"></div>
                            <div class="input-group"><label for="fat">Fat (g per serving)</label><input id="fat" name="fat" type="number" min="0" max="500" step="0.1" value="<?= e($_POST['fat'] ?? '0') ?>"></div>
                        </div>
                        <button type="submit" class="primary-btn">Save Meal</button>
                    </div>
                </form>
                <div class="form-card">
                    <h2 class="card-title">Recent Meals</h2>
                    <?php if (!$meals): ?><p>No meals have been logged yet.</p><?php else: ?>
                        <div style="overflow-x:auto;"><table class="report-table"><thead><tr><th>Date</th><th>Meal</th><th>Food</th><th>Servings</th><th>Calories</th></tr></thead><tbody>
                        <?php foreach ($meals as $meal): ?><tr><td><?= e($meal['meal_date']) ?> <?= e(substr($meal['meal_time'], 0, 5)) ?></td><td><?= e($meal['meal_type']) ?></td><td><?= e($meal['food_name'] ?? $meal['custom_food_name']) ?></td><td><?= e($meal['quantity']) ?></td><td><?= e($meal['calories']) ?> kcal</td></tr><?php endforeach; ?>
                        </tbody></table></div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>