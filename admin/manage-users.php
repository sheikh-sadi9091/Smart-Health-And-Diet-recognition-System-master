<?php
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');
$currentUser = getCurrentUser();
$pdo = getDBConnection();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
    $newStatus = $_POST['status'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!isValidCsrfToken($csrfToken)) {
        $error = 'Your session expired. Please reload and try again.';
    } elseif ($userId === false || !in_array($newStatus, ['active', 'suspended'], true)) {
        $error = 'Invalid user action.';
    } else {
        $userStmt = $pdo->prepare(
            'SELECT u.role, d.approval_status FROM users u LEFT JOIN dietitian_profiles d ON d.user_id = u.id WHERE u.id = :user_id LIMIT 1'
        );
        $userStmt->execute(['user_id' => $userId]);
        $targetUser = $userStmt->fetch();

        if (!$targetUser || $targetUser['role'] === 'admin') {
            $error = 'This account cannot be changed here.';
        } elseif ($newStatus === 'active' && $targetUser['role'] === 'dietitian' && $targetUser['approval_status'] !== 'approved') {
            $error = 'A dietitian must be approved before their account can be activated.';
        } else {
            $updateUser = $pdo->prepare('UPDATE users SET status = :status WHERE id = :user_id');
            $updateUser->execute(['status' => $newStatus, 'user_id' => $userId]);
            setFlashMessage('success', 'Account status updated.');
            header('Location: manage-users.php');
            exit;
        }
    }
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$users = $pdo->query(
    "SELECT u.id, u.name, u.email, u.role, u.status, u.created_at, d.approval_status
     FROM users u LEFT JOIN dietitian_profiles d ON d.user_id = u.id
     WHERE u.role <> 'admin' ORDER BY u.created_at DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Health Track</title>
    <link rel="stylesheet" href="../dashboard.css">
</head>
<body>
    <div class="dashboard-layout">
        <aside class="sidebar">
            <div class="sidebar-header"><span class="logo-text-small">Health Track Admin</span></div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-link">Dashboard</a>
                <a href="manage-users.php" class="nav-link active">Manage Users</a>
                <a href="approve-dietitians.php" class="nav-link">Approve Dietitians</a>
            </nav>
            <div class="sidebar-footer"><div><?= e($currentUser['name']) ?></div><a href="../logout.php" class="nav-link sign-out">Sign Out</a></div>
        </aside>
        <main class="main-content">
            <header class="top-header"><?php renderBackButton('dashboard.php'); ?><h1 class="header-title">Manage Users</h1></header>
            <div class="content-wrapper">
                <?php renderFlashMessage(); ?>
                <?php if ($error !== ''): ?><p role="alert" class="form-card" style="color:#e74c3c;"><?= e($error) ?></p><?php endif; ?>
                <div class="form-card">
                    <h2 class="card-title">Accounts (<?= count($users) ?>)</h2>
                    <div style="overflow-x:auto;"><table class="report-table">
                        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Dietitian review</th><th>Created</th><th>Action</th></tr></thead>
                        <tbody><?php foreach ($users as $user): ?>
                            <tr><td><?= e($user['name']) ?></td><td><?= e($user['email']) ?></td><td><?= e(ucfirst($user['role'])) ?></td>
                                <td><?= e(ucfirst($user['status'])) ?></td><td><?= e($user['approval_status'] ? ucfirst($user['approval_status']) : 'N/A') ?></td>
                                <td><?= e(date('M j, Y', strtotime($user['created_at']))) ?></td><td>
                                    <form method="post" action="manage-users.php">
                                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                        <?php if ($user['status'] === 'suspended'): ?>
                                            <button class="primary-btn" type="submit" name="status" value="active" <?= $user['role'] === 'dietitian' && $user['approval_status'] !== 'approved' ? 'disabled title="Application must be approved first"' : '' ?>>Activate</button>
                                        <?php else: ?>
                                            <button class="primary-btn" type="submit" name="status" value="suspended">Suspend</button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?></tbody>
                    </table></div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>