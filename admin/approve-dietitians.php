<?php
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');
$currentUser = getCurrentUser();
$pdo = getDBConnection();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
    $decision = $_POST['decision'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!isValidCsrfToken($csrfToken)) {
        $error = 'Your session expired. Please reload and try again.';
    } elseif ($userId === false || !in_array($decision, ['approve', 'reject'], true)) {
        $error = 'Invalid application action.';
    } else {
        $pdo->beginTransaction();
        try {
            $pending = $pdo->prepare("SELECT user_id FROM dietitian_profiles WHERE user_id = :user_id AND approval_status = 'pending' FOR UPDATE");
            $pending->execute(['user_id' => $userId]);
            if (!$pending->fetch()) {
                throw new RuntimeException('This application is no longer pending.');
            }

            $approvalStatus = $decision === 'approve' ? 'approved' : 'rejected';
            $userStatus = $decision === 'approve' ? 'active' : 'inactive';
            $updateProfile = $pdo->prepare('UPDATE dietitian_profiles SET approval_status = :status WHERE user_id = :user_id');
            $updateProfile->execute(['status' => $approvalStatus, 'user_id' => $userId]);
            $updateUser = $pdo->prepare('UPDATE users SET status = :status WHERE id = :user_id AND role = \'dietitian\'');
            $updateUser->execute(['status' => $userStatus, 'user_id' => $userId]);
            $pdo->commit();

            setFlashMessage('success', 'Dietitian application ' . $approvalStatus . '.');
            header('Location: approve-dietitians.php');
            exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Could not update the application.';
        }
    }
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$applications = $pdo->query(
    "SELECT u.id, u.name, u.email, u.created_at, d.qualification, d.specialization
     FROM dietitian_profiles d JOIN users u ON u.id = d.user_id
     WHERE d.approval_status = 'pending' ORDER BY d.created_at ASC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Dietitians - Health Track</title>
    <link rel="stylesheet" href="../dashboard.css">
</head>
<body>
    <div class="dashboard-layout">
        <aside class="sidebar">
            <div class="sidebar-header"><span class="logo-text-small">Health Track Admin</span></div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-link">Dashboard</a>
                <a href="manage-users.php" class="nav-link">Manage Users</a>
                <a href="approve-dietitians.php" class="nav-link active">Approve Dietitians</a>
            </nav>
            <div class="sidebar-footer"><div><?= e($currentUser['name']) ?></div><a href="../logout.php" class="nav-link sign-out">Sign Out</a></div>
        </aside>
        <main class="main-content">
            <header class="top-header"><?php renderBackButton('dashboard.php'); ?><h1 class="header-title">Dietitian Applications</h1></header>
            <div class="content-wrapper">
                <?php renderFlashMessage(); ?>
                <?php if ($error !== ''): ?><p role="alert" class="form-card" style="color:#e74c3c;"><?= e($error) ?></p><?php endif; ?>
                <div class="form-card">
                    <h2 class="card-title">Pending Review (<?= count($applications) ?>)</h2>
                    <?php if (!$applications): ?><p>No applications are waiting for review.</p><?php else: ?>
                        <div style="overflow-x:auto;"><table class="report-table"><thead><tr><th>Name</th><th>Email</th><th>Qualification</th><th>Specialization</th><th>Submitted</th><th>Decision</th></tr></thead><tbody>
                        <?php foreach ($applications as $application): ?><tr>
                            <td><?= e($application['name']) ?></td><td><?= e($application['email']) ?></td>
                            <td><?= e($application['qualification'] ?? 'Not provided') ?></td><td><?= e($application['specialization'] ?? 'Not provided') ?></td>
                            <td><?= e(date('M j, Y', strtotime($application['created_at']))) ?></td>
                            <td><form method="post" action="approve-dietitians.php" style="display:flex;gap:8px;">
                                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="user_id" value="<?= (int)$application['id'] ?>">
                                <button class="primary-btn" name="decision" value="approve" type="submit">Approve</button>
                                <button class="primary-btn" name="decision" value="reject" type="submit">Reject</button>
                            </form></td>
                        </tr><?php endforeach; ?>
                        </tbody></table></div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>