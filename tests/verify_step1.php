<?php
/**
 * Verification Test Script for Step 1 Implementation
 * Smart Health & Diet Recommendation System
 */

echo "========================================================\n";
echo "STEP 1: Full Verification & Automated Health Check\n";
echo "========================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest($description, $condition) {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] " . $description . "\n";
        $passCount++;
    } else {
        echo "[FAIL] " . $description . "\n";
        $failCount++;
    }
}

// 1. Verify Database Connection
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
try {
    $pdo = getDBConnection();
    assertTest("PDO Database connection established to smart_health_diet", $pdo instanceof PDO);
} catch (Exception $e) {
    assertTest("PDO Database connection established: " . $e->getMessage(), false);
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    exit(1);
}

// 2. Verify Tables Exist
$expectedTables = [
    'users',
    'dietitian_profiles',
    'health_profiles',
    'food_items',
    'meal_logs',
    'water_logs',
    'sleep_logs',
    'weight_logs',
    'meal_plans',
    'meal_plan_items',
    'recommendations',
    'messages',
    'resources'
];

$stmt = $pdo->query("SHOW TABLES");
$actualTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($expectedTables as $t) {
    assertTest("Table exists: {$t}", in_array($t, $actualTables));
}

// 3. Verify Password Hashing with password_hash & password_verify
$testPassword = 'TestSecurePassword#2026';
$hash = password_hash($testPassword, PASSWORD_BCRYPT);
assertTest("password_hash generates valid bcrypt hash", str_starts_with($hash, '$2y$'));
assertTest("password_verify validates correct password", password_verify($testPassword, $hash) === true);
assertTest("password_verify rejects incorrect password", password_verify('WrongPassword', $hash) === false);
assertTest("Active accounts can authenticate", canAuthenticateUser(['role' => 'user', 'status' => 'active']));
assertTest("Pending dietitians can authenticate to see their review status", canAuthenticateUser(['role' => 'dietitian', 'status' => 'pending']));
assertTest("Inactive accounts cannot authenticate", !canAuthenticateUser(['role' => 'dietitian', 'status' => 'inactive']));
assertTest("Suspended accounts cannot authenticate", !canAuthenticateUser(['role' => 'user', 'status' => 'suspended']));
assertTest("CSRF validation rejects malformed array tokens", !isValidCsrfToken(['invalid']));
assertTest("Request string helper rejects array input", requestString(['invalid']) === '');
assertTest("Date validation rejects impossible dates", !isValidDate('2026-02-30'));
assertTest("Date validation accepts valid dates", isValidDate('2026-02-28'));

// 4. Verify Seed Accounts
$stmt = $pdo->query("SELECT id, name, email, role, status FROM users ORDER BY id ASC");
$users = $stmt->fetchAll();
assertTest("Initial seed users populated (at least 4 users)", count($users) >= 4);

$seedEmails = [
    'admin@healthtrack.com' => false,
    'sarah@healthtrack.com' => false,
    'james@healthtrack.com' => false,
    'alice@example.com' => false,
];

foreach ($users as $u) {
    if (array_key_exists($u['email'], $seedEmails)) {
        $seedEmails[$u['email']] = true;
    }
}

assertTest("Admin account exists (admin@healthtrack.com)", $seedEmails['admin@healthtrack.com']);
assertTest("Approved Dietitian account exists (sarah@healthtrack.com)", $seedEmails['sarah@healthtrack.com']);
assertTest("Pending Dietitian account exists (james@healthtrack.com)", $seedEmails['james@healthtrack.com']);
assertTest("Regular User account exists (alice@example.com)", $seedEmails['alice@example.com']);

$demoPasswords = [
    'admin@healthtrack.com' => 'admin123',
    'sarah@healthtrack.com' => 'dietitian123',
    'james@healthtrack.com' => 'dietitian123',
    'alice@example.com' => 'user123',
];
$passwordStmt = $pdo->prepare('SELECT password FROM users WHERE email = :email LIMIT 1');
foreach ($demoPasswords as $email => $password) {
    $passwordStmt->execute(['email' => $email]);
    assertTest("Demo password verifies for {$email}", password_verify($password, (string)$passwordStmt->fetchColumn()));
}

// 5. Test User Registration via DB
$testToken = bin2hex(random_bytes(8));
$testUserEmail = 'test_student_' . $testToken . '@example.com';
$pdo->beginTransaction();
$insertStmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, role, status) VALUES ('Test Student', :email, :pass, '+123456789', 'user', 'active')");
$regResult = $insertStmt->execute([
    'email' => $testUserEmail,
    'pass'  => password_hash('student123', PASSWORD_BCRYPT)
]);
$newUserId = (int)$pdo->lastInsertId();
assertTest("User registration creates record in users table", $regResult && $newUserId > 0);

// Create linked health profile
$hpStmt = $pdo->prepare("INSERT INTO health_profiles (user_id, age, gender, height, weight, activity_level, health_goal, daily_calorie_target) VALUES (:uid, 22, 'male', 175.00, 68.00, 'moderate', 'maintain_weight', 2150)");
$hpResult = $hpStmt->execute(['uid' => $newUserId]);
assertTest("User registration creates linked health_profile", $hpResult === true);

// 6. Test Dietitian Registration via DB
$testDietitianEmail = 'test_dietitian_' . $testToken . '@example.com';
$insertDietitianStmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, role, status) VALUES ('Dr. Alex Taylor', :email, :pass, '+1999888777', 'dietitian', 'pending')");
$dResult = $insertDietitianStmt->execute([
    'email' => $testDietitianEmail,
    'pass'  => password_hash('dietitian123', PASSWORD_BCRYPT)
]);
$newDietitianId = (int)$pdo->lastInsertId();
assertTest("Dietitian registration creates user with pending status", $dResult && $newDietitianId > 0);

$dpStmt = $pdo->prepare("INSERT INTO dietitian_profiles (user_id, qualification, specialization, approval_status) VALUES (:uid, 'M.Sc Nutrition', 'Pediatric Diet', 'pending')");
$dpResult = $dpStmt->execute(['uid' => $newDietitianId]);
assertTest("Dietitian registration creates linked dietitian_profile with pending approval_status", $dpResult === true);
$pdo->rollBack();

// 7. Verify Core Files Exist
$coreFiles = [
    'config/database.php',
    'includes/functions.php',
    'includes/auth.php',
    'index.php',
    'login.php',
    'register.php',
    'logout.php',
    'admin/dashboard.php',
    'dietitian/dashboard.php',
    'dietitian/pending.php',
    'dietitian/health-info.php',
    'dietitian/meal-plans.php',
    'dietitian/recommendations.php',
    'dietitian/messages.php',
    'user/dashboard.php',
    'user/health-info.php',
    'user/log-meals.php',
    'user/calorie-reports.php',
    'user/diet-plans.php',
    'user/messages.php',
    'admin/manage-users.php',
    'admin/approve-dietitians.php',
    'admin/foods.php',
    'admin/reports.php',
    'database/smart_health_diet.sql'
];

foreach ($coreFiles as $f) {
    assertTest("File exists: {$f}", file_exists(__DIR__ . '/../' . $f));
}

// 8. Test Helper Functions
require_once __DIR__ . '/../includes/functions.php';

$bmiResult = calculateBMI(70.0, 175.0);
assertTest("BMI Calculation helper returns valid value (expected 22.9)", $bmiResult['bmi'] == 22.9 && $bmiResult['category'] === 'Normal');

$calorieEstimate = calculateDailyCalorieTarget(70.0, 175.0, 25, 'male', 'moderate', 'weight_loss');
assertTest("Calorie target formula returns reasonable positive integer (>1200)", $calorieEstimate > 1200);

echo "\n--------------------------------------------------------\n";
echo "SUMMARY: {$passCount} Passed, {$failCount} Failed\n";
echo "========================================================\n";

if ($failCount > 0) {
    exit(1);
}
