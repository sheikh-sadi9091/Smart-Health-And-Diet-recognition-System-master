<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');
$currentUser = getCurrentUser();

$pdo = getDBConnection();

// Fetch key admin portal metrics
$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$totalDietitians = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'dietitian'")->fetchColumn();
$pendingDietitians = (int)$pdo->query("SELECT COUNT(*) FROM dietitian_profiles WHERE approval_status = 'pending'")->fetchColumn();
$totalFoods = (int)$pdo->query("SELECT COUNT(*) FROM food_items")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Track - Admin Portal</title>
    <link rel="stylesheet" href="../dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="dashboard-layout">
        
        <!-- Admin Sidebar Navigation -->
        <aside class="sidebar admin-sidebar">
            <div class="sidebar-header">
                <div class="logo-container small-logo admin-logo">
                    <div class="filled-heart-icon">
                        <svg viewBox="0 0 24 24" fill="white" width="16" height="16">
                            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                        </svg>
                    </div>
                    <span class="logo-text-small">Health Track Admin</span>
                </div>
            </div>

            <nav class="sidebar-nav admin-nav">
                <a href="dashboard.php" class="nav-link admin-nav-link active" style="color: var(--primary-orange);">
                    Dashboard Overview
                </a>
                <a href="manage-users.php" class="nav-link admin-nav-link">
                    Manage Users (<?= $totalUsers ?>)
                </a>
                <a href="approve-dietitians.php" class="nav-link admin-nav-link">
                    Approve Dietitians <?php if ($pendingDietitians > 0): ?><span style="background-color: #f39c12; color: #111419; font-size: 11px; padding: 2px 8px; border-radius: 10px; font-weight: 700; margin-left: 6px;"><?= $pendingDietitians ?></span><?php endif; ?>
                </a>
                <a href="foods.php" class="nav-link admin-nav-link">
                    Food Database (<?= $totalFoods ?>)
                </a>
                <a href="reports.php" class="nav-link admin-nav-link">
                    Generate Reports
                </a>
            </nav>

            <div class="sidebar-footer" style="margin-top: auto; padding: 20px 30px; border-top: 1px solid var(--sidebar-border);">
                <div style="font-size: 13px; color: var(--text-light); font-weight: 600; margin-bottom: 4px;"><?= e($currentUser['name']) ?></div>
                <div style="font-size: 12px; color: #2ecc71; margin-bottom: 16px;">Super Administrator</div>
                <a href="../logout.php" class="nav-link sign-out" style="padding: 0; display: flex; align-items: center; gap: 8px;">
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
        <main class="main-content admin-main-hub">
            <header class="top-header"><?php renderBackButton(); ?><h1 class="header-title">Admin Dashboard</h1></header>
            <div style="width: 100%; max-width: 900px;">
                <?php renderFlashMessage(); ?>

                <div class="admin-dashboard-grid">
                    
                    <!-- Manage Users Card -->
                    <a href="manage-users.php" class="admin-hub-card card-manage">
                        <div class="icon-wrapper icon-green">
                            <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                        </div>
                        <h3>Manage Users</h3>
                        <p style="font-size: 13px; color: var(--text-muted); margin-top: 6px;"><?= $totalUsers ?> registered users</p>
                    </a>

                    <!-- Approve Dietitians Card -->
                    <a href="approve-dietitians.php" class="admin-hub-card card-approve">
                        <div class="icon-wrapper icon-blue">
                            <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                                <polyline points="16 11 18 13 22 9"></polyline>
                            </svg>
                        </div>
                        <h3>Approve Dietitians</h3>
                        <p style="font-size: 13px; color: var(--text-muted); margin-top: 6px;">
                            <?= $pendingDietitians ?> pending verification
                        </p>
                    </a>

                    <!-- Food Database Card -->
                    <a href="foods.php" class="admin-hub-card card-food">
                        <div class="icon-wrapper icon-orange">
                            <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                                <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path>
                                <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path>
                            </svg>
                        </div>
                        <h3>Food Database</h3>
                        <p style="font-size: 13px; color: var(--text-muted); margin-top: 6px;"><?= $totalFoods ?> food items stored</p>
                    </a>

                    <!-- Generate Reports Card -->
                    <a href="reports.php" class="admin-hub-card card-reports">
                        <div class="icon-wrapper icon-purple">
                            <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="12" y1="18" x2="12" y2="12"></line>
                                <line x1="8" y1="18" x2="8" y2="15"></line>
                                <line x1="16" y1="18" x2="16" y2="15"></line>
                            </svg>
                        </div>
                        <h3>Generate Reports</h3>
                        <p style="font-size: 13px; color: var(--text-muted); margin-top: 6px;">System & health summaries</p>
                    </a>

                </div>
            </div>
        </main>
    </div>
</body>
</html>
