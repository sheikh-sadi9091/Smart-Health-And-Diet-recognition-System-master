<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    redirectByRole($_SESSION['user_role'] ?? 'user');
} else {
    header('Location: login.php');
    exit;
}
