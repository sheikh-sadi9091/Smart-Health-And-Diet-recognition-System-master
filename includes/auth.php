<?php
/**
 * Authentication and Role Authorization Helpers
 * Smart Health & Diet Recommendation System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Check if a user is currently logged in.
 *
 * @return bool
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function canAuthenticateUser(array $user): bool {
    return ($user['status'] ?? null) === 'active'
        || (($user['role'] ?? null) === 'dietitian' && ($user['status'] ?? null) === 'pending');
}

/**
 * Get current logged in user array from session or database.
 *
 * @return array|null
 */
function getCurrentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }

    static $currentUser = null;

    if ($currentUser === null) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, name, email, phone, role, status FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user || !canAuthenticateUser($user)) {
            logoutUser();
            return null;
        }

        $currentUser = $user;
    }

    return $currentUser;
}

/**
 * Ensure user is logged in, otherwise redirect to login page.
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        $base = getBasePath();
        setFlashMessage('error', 'Please log in to continue.');
        header("Location: {$base}login.php");
        exit;
    }
}

/**
 * Restrict access to a specific role ('admin', 'dietitian', 'user').
 *
 * @param string|array $allowedRoles
 */
function requireRole($allowedRoles): void {
    requireLogin();

    $user = getCurrentUser();
    $roles = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];

    if (!$user || !in_array($user['role'], $roles, true)) {
        $base = getBasePath();
        setFlashMessage('error', 'Access denied. You do not have permission to access that page.');
        redirectByRole($user['role'] ?? 'user');
        exit;
    }

    // Special check for Dietitian: must be approved
    if ($user['role'] === 'dietitian') {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT approval_status FROM dietitian_profiles WHERE user_id = :uid LIMIT 1");
        $stmt->execute(['uid' => $user['id']]);
        $profile = $stmt->fetch();

        if (!$profile || $profile['approval_status'] !== 'approved') {
            $base = getBasePath();
            setFlashMessage('warning', 'Your dietitian application is currently pending review and approval by an administrator.');
            // Allow them to see a pending notice or logout
            if (!str_contains($_SERVER['PHP_SELF'], 'pending.php')) {
                header("Location: {$base}dietitian/pending.php");
                exit;
            }
        }
    }
}

/**
 * Redirect user to their respective dashboard based on role.
 *
 * @param string $role
 */
function redirectByRole(string $role): void {
    $base = getBasePath();
    switch ($role) {
        case 'admin':
            header("Location: {$base}admin/dashboard.php");
            break;
        case 'dietitian':
            header("Location: {$base}dietitian/dashboard.php");
            break;
        case 'user':
        default:
            header("Location: {$base}user/dashboard.php");
            break;
    }
    exit;
}

/**
 * Log a user into session.
 *
 * @param array $user
 */
function loginUser(array $user): void {
    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
}

/**
 * Log out user and destroy session safely.
 */
function logoutUser(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
}
