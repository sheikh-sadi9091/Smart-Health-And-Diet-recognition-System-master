<?php
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');
$currentUser = getCurrentUser();
$pdo = getDBConnection();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $foodName = is_string($_POST['food_name'] ?? null) ? trim($_POST['food_name']) : '';
    $servingSize = is_string($_POST['serving_size'] ?? null) ? trim($_POST['serving_size']) : '';
    $servingWeight = filter_var($_POST['serving_weight_g'] ?? null, FILTER_VALIDATE_FLOAT);
    $calories = filter_var($_POST['calories'] ?? null, FILTER_VALIDATE_FLOAT);
    $protein = filter_var($_POST['protein'] ?? null, FILTER_VALIDATE_FLOAT);
    $carbohydrates = filter_var($_POST['carbohydrates'] ?? null, FILTER_VALIDATE_FLOAT);
    $fat = filter_var($_POST['fat'] ?? null, FILTER_VALIDATE_FLOAT);
    $fiber = filter_var($_POST['fiber'] ?? null, FILTER_VALIDATE_FLOAT);

    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please reload and try again.';
    } elseif ($foodName === '' || strlen($foodName) > 150 || $servingSize === '' || strlen($servingSize) > 100
        || $servingWeight === false || $servingWeight <= 0 || $servingWeight > 9999.99
        || $calories === false || $calories < 0 || $calories > 99999.9
        || $protein === false || $protein < 0 || $protein > 99999.9
        || $carbohydrates === false || $carbohydrates < 0 || $carbohydrates > 99999.9
        || $fat === false || $fat < 0 || $fat > 99999.9
        || $fiber === false || $fiber < 0 || $fiber > 99999.9) {
        $error = 'Enter valid food details and nutrition values.';
    } else {
        $insert = $pdo->prepare(
            'INSERT INTO food_items (food_name, serving_size, serving_weight_g, calories, protein, carbohydrates, fat, fiber)
             VALUES (:food_name, :serving_size, :serving_weight_g, :calories, :protein, :carbohydrates, :fat, :fiber)'
        );
        $insert->execute([
            'food_name' => $foodName,
            'serving_size' => $servingSize,
            'serving_weight_g' => $servingWeight,
            'calories' => $calories,
            'protein' => $protein,
            'carbohydrates' => $carbohydrates,
            'fat' => $fat,
            'fiber' => $fiber,
        ]);
        setFlashMessage('success', 'Food item added to the database.');
        header('Location: foods.php');
        exit;
    }
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$foods = $pdo->query('SELECT * FROM food_items ORDER BY food_name')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Food Database - Health Track</title>
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
                <a href="foods.php" class="nav-link admin-nav-link active">Food Database</a>
                <a href="reports.php" class="nav-link admin-nav-link">Reports</a>
            </nav>
            <div class="sidebar-footer"><div><?= e($currentUser['name']) ?></div><a href="../logout.php" class="nav-link sign-out">Sign Out</a></div>
        </aside>
        <main class="main-content">
            <header class="top-header"><?php renderBackButton('dashboard.php'); ?><h1 class="header-title">Food Database</h1></header>
            <div class="content-wrapper">
                <?php renderFlashMessage(); ?>
                <?php if ($error !== ''): ?><p role="alert" class="form-card" style="color:#e74c3c;"><?= e($error) ?></p><?php endif; ?>
                <form method="post" action="foods.php" class="health-info-form">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <div class="form-card">
                        <h2 class="card-title">Add Food Item</h2>
                        <div class="input-grid">
                            <div class="input-group"><label for="food_name">Food name</label><input id="food_name" name="food_name" maxlength="150" required></div>
                            <div class="input-group"><label for="serving_size">Serving size</label><input id="serving_size" name="serving_size" maxlength="100" value="100g" required></div>
                            <div class="input-group"><label for="serving_weight_g">Serving weight (g)</label><input id="serving_weight_g" name="serving_weight_g" type="number" min="0.01" max="9999.99" step="0.01" required></div>
                            <div class="input-group"><label for="calories">Calories</label><input id="calories" name="calories" type="number" min="0" max="99999.9" step="0.1" required></div>
                            <div class="input-group"><label for="protein">Protein (g)</label><input id="protein" name="protein" type="number" min="0" max="99999.9" step="0.1" value="0" required></div>
                            <div class="input-group"><label for="carbohydrates">Carbohydrates (g)</label><input id="carbohydrates" name="carbohydrates" type="number" min="0" max="99999.9" step="0.1" value="0" required></div>
                            <div class="input-group"><label for="fat">Fat (g)</label><input id="fat" name="fat" type="number" min="0" max="99999.9" step="0.1" value="0" required></div>
                            <div class="input-group"><label for="fiber">Fiber (g)</label><input id="fiber" name="fiber" type="number" min="0" max="99999.9" step="0.1" value="0" required></div>
                        </div>
                        <button class="primary-btn" type="submit">Add Food</button>
                    </div>
                </form>
                <div class="form-card">
                    <h2 class="card-title">Stored Foods (<?= count($foods) ?>)</h2>
                    <div style="overflow-x:auto;"><table class="report-table"><thead><tr><th>Food</th><th>Serving</th><th>Calories</th><th>Protein</th><th>Carbs</th><th>Fat</th><th>Fiber</th></tr></thead><tbody>
                        <?php foreach ($foods as $food): ?><tr><td><?= e($food['food_name']) ?></td><td><?= e($food['serving_size']) ?> (<?= e($food['serving_weight_g']) ?> g)</td><td><?= e($food['calories']) ?> kcal</td><td><?= e($food['protein']) ?> g</td><td><?= e($food['carbohydrates']) ?> g</td><td><?= e($food['fat']) ?> g</td><td><?= e($food['fiber']) ?> g</td></tr><?php endforeach; ?>
                    </tbody></table></div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>