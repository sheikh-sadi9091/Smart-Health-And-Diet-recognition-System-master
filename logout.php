<?php
require_once __DIR__ . '/includes/auth.php';

logoutUser();
setFlashMessage('success', 'You have been successfully logged out.');
header('Location: login.php');
exit;
