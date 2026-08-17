<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Already logged in? Send straight to the right dashboard.
if (!empty($_SESSION['user_id']) && !empty($_SESSION['role_name'])) {
    redirect(roleDashboardUrl($_SESSION['role_name']));
}

$companyName = getSetting($pdo, 'company_name', APP_NAME);
$companyLogo = getSetting($pdo, 'company_logo', null);

// "Follow us" row at the bottom - plain links to the company's own social
// profiles, not social login. Only platforms Admin has actually filled in
// (Settings > Social Media Links) show up here.
$socialPlatforms = [
    'instagram' => 'social_instagram',
    'facebook'  => 'social_facebook',
    'youtube'   => 'social_youtube',
    'whatsapp'  => 'social_whatsapp',
    'website'   => 'social_website',
];
$socialLinks = [];
foreach ($socialPlatforms as $key => $settingKey) {
    $url = getSetting($pdo, $settingKey, '');
    if ($url !== '') {
        $socialLinks[] = ['key' => $key, 'url' => $url];
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = clean($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $pdo->prepare(
            "SELECT u.*, r.name AS role_name
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.username = :username AND u.status = 'active'"
        );
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['role_id']   = $user['role_id'];
            $_SESSION['role_name'] = $user['role_name'];

            redirect(roleDashboardUrl($user['role_name']));
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login &middot; <?= e($companyName) ?></title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../../assets/css/style.css') ?>">
</head>
<body class="login-body">
<div class="login-page">
<img class="login-graphic" src="<?= APP_URL ?>/assets/img/illustrations/factory.svg" alt="">
<div class="login-topbar">
  <?php if ($companyLogo): ?>
    <img src="<?= UPLOAD_URL ?><?= e($companyLogo) ?>" alt="<?= e($companyName) ?>" class="login-topbar-logo">
  <?php else: ?>
    <span class="login-topbar-badge"><?= e(strtoupper(substr($companyName, 0, 1))) ?></span>
  <?php endif; ?>
  <span class="login-topbar-name"><?= e($companyName) ?></span>
</div>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">
      <?php if ($companyLogo): ?>
        <img src="<?= UPLOAD_URL ?><?= e($companyLogo) ?>" alt="<?= e($companyName) ?>">
      <?php else: ?>
        <span><?= e(strtoupper(substr($companyName, 0, 1))) ?></span>
      <?php endif; ?>
    </div>
    <div class="login-header-center">
      <h1><?= e($companyName) ?></h1>
      <p class="text-soft" style="margin-top:0;font-size:13px;">Sign in to continue</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="" class="login-form">
      <div class="login-input-group">
        <label for="username" class="sr-only">Username</label>
        <span class="login-input-icon"><?= tabIcon('user') ?></span>
        <input type="text" id="username" name="username" placeholder="Username" required autofocus value="<?= e($_POST['username'] ?? '') ?>">
      </div>

      <div class="login-input-group">
        <label for="password" class="sr-only">Password</label>
        <span class="login-input-icon"><?= tabIcon('lock') ?></span>
        <input type="password" id="password" name="password" placeholder="Password" required>
      </div>

      <button type="submit" class="login-submit-btn">Log In</button>
    </form>
    <a href="<?= APP_URL ?>/modules/cf/onboard.php" class="login-cf-register-link">New C&amp;F Partner? Register here</a>
  </div>
</div>
<?php if ($socialLinks): ?>
<div class="login-social-footer">
  <div class="login-social-label">Follow us</div>
  <div class="login-social-row">
    <?php foreach ($socialLinks as $s): ?>
      <a href="<?= e($s['url']) ?>" target="_blank" rel="noopener" class="login-social-btn login-social-btn-<?= $s['key'] ?>" title="<?= e(ucfirst($s['key'])) ?>"><?= socialIcon($s['key']) ?></a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
</div>
</body>
</html>
