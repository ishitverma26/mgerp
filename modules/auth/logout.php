<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION = [];
session_destroy();
require_once __DIR__ . '/../../config/constants.php';
header('Location: ' . APP_URL . '/modules/auth/login.php');
exit;
