<?php
require_once __DIR__ . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!empty($_SESSION['user_id']) && !empty($_SESSION['role_name'])) {
    header('Location: ' . APP_URL . '/dashboard/' . ($_SESSION['role_name'] === 'Admin' ? 'admin.php' : 'plant-head.php'));
} else {
    header('Location: ' . APP_URL . '/modules/auth/login.php');
}
exit;
