<?php
/**
 * ONE-TIME SETUP SCRIPT.
 * Run this once in the browser (e.g. localhost/cement-erp/database/create-admin.php)
 * to create your first Admin login, then DELETE this file - it is not
 * protected by any login check on purpose (there's no admin yet to log in with).
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$message = '';
$done = false;

// Refuse to run again if an Admin already exists, unless explicitly forced.
$existingAdmin = $pdo->query(
    "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name = 'Admin'"
)->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $existingAdmin == 0) {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || $username === '' || strlen($password) < 6) {
        $message = 'Please fill all fields. Password must be at least 6 characters.';
    } else {
        $roleId = $pdo->query("SELECT id FROM roles WHERE name = 'Admin'")->fetchColumn();
        if (!$roleId) {
            $message = 'Roles table is empty - run database/schema.sql and database/seed.sql first.';
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO users (name, username, password, role_id, status)
                 VALUES (:name, :username, :password, :role_id, 'active')"
            );
            $stmt->execute([
                ':name'     => $name,
                ':username' => $username,
                ':password' => password_hash($password, PASSWORD_DEFAULT),
                ':role_id'  => $roleId,
            ]);
            $done = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Create Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card" style="width:380px;">
    <div class="eyebrow">One-time setup</div>
    <h1>Create Admin User</h1>

    <?php if ($existingAdmin > 0 && !$done): ?>
      <div class="alert alert-info">An Admin user already exists. For security, delete this file now.</div>
    <?php elseif ($done): ?>
      <div class="alert alert-success">Admin user created. Go to <a href="../modules/auth/login.php">Login</a>, then delete <code>database/create-admin.php</code>.</div>
    <?php else: ?>
      <?php if ($message): ?><div class="alert alert-danger"><?= e($message) ?></div><?php endif; ?>
      <form method="post" action="">
        <label>Full Name</label>
        <input type="text" name="name" required value="<?= e($_POST['name'] ?? '') ?>">
        <label>Username</label>
        <input type="text" name="username" required value="<?= e($_POST['username'] ?? '') ?>">
        <label>Password</label>
        <input type="password" name="password" required minlength="6">
        <button type="submit" class="btn btn-accent" style="width:100%;margin-top:18px;">Create Admin</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
