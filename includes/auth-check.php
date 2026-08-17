<?php
/**
 * Include this at the top of EVERY protected page (after db.php).
 * It starts the session, confirms the user is logged in, and exposes
 * $currentUser for use in that page.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id']) || empty($_SESSION['role_id']) || empty($_SESSION['role_name'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . APP_URL . '/modules/auth/login.php');
    exit;
}

$currentUser = [
    'id'        => $_SESSION['user_id'],
    'name'      => $_SESSION['user_name'],
    'username'  => $_SESSION['username'],
    'role_id'   => $_SESSION['role_id'],
    'role_name' => $_SESSION['role_name'],
];

/**
 * Call this at the top of a page to restrict it to specific roles.
 * Example: requireRole(['Admin']);
 */
function requireRole(array $allowedRoles) {
    global $currentUser;
    if (!in_array($currentUser['role_name'], $allowedRoles, true)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:40px;text-align:center">'
          . '<h2>403 - Access Denied</h2>'
          . '<p>Your role (' . e($currentUser['role_name']) . ') cannot access this page.</p>'
          . '<a href="' . APP_URL . roleDashboardUrl($currentUser['role_name']) . '">Back to dashboard</a>'
          . '</div>');
    }
}
