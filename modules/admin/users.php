<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
requireRole(['Admin']);

$roles = $pdo->query("SELECT * FROM roles ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'save') {
        $name = clean($_POST['name'] ?? '');
        $username = clean($_POST['username'] ?? '');
        $roleId = (int) ($_POST['role_id'] ?? 0);
        $password = $_POST['password'] ?? '';

        if ($name === '' || $username === '' || $roleId <= 0) {
            setFlash('error', 'Name, username and role are required.');
        } elseif ($id === 0 && strlen($password) < 6) {
            setFlash('error', 'Password must be at least 6 characters for a new user.');
        } else {
            // Prevent duplicate usernames
            $dupCheck = $pdo->prepare("SELECT id FROM users WHERE username=:u AND id != :id");
            $dupCheck->execute([':u' => $username, ':id' => $id]);
            if ($dupCheck->fetch()) {
                setFlash('error', 'That username is already taken.');
            } else {
                if ($id > 0) {
                    $old = $pdo->prepare("SELECT id,name,username,role_id,status FROM users WHERE id=:id");
                    $old->execute([':id' => $id]);
                    $oldRow = $old->fetch();

                    if ($password !== '') {
                        if (strlen($password) < 6) {
                            setFlash('error', 'New password must be at least 6 characters.');
                            redirect('/modules/admin/users.php');
                        }
                        $pdo->prepare("UPDATE users SET name=:name, username=:username, role_id=:role_id, password=:password WHERE id=:id")
                            ->execute([':name'=>$name, ':username'=>$username, ':role_id'=>$roleId, ':password'=>password_hash($password, PASSWORD_DEFAULT), ':id'=>$id]);
                    } else {
                        $pdo->prepare("UPDATE users SET name=:name, username=:username, role_id=:role_id WHERE id=:id")
                            ->execute([':name'=>$name, ':username'=>$username, ':role_id'=>$roleId, ':id'=>$id]);
                    }
                    logAudit($pdo, $currentUser['id'], 'update', 'users', $id, $oldRow, ['name'=>$name,'username'=>$username,'role_id'=>$roleId]);
                    setFlash('success', 'User updated.');
                } else {
                    $stmt = $pdo->prepare("INSERT INTO users (name, username, password, role_id) VALUES (:name,:username,:password,:role_id)");
                    $stmt->execute([':name'=>$name, ':username'=>$username, ':password'=>password_hash($password, PASSWORD_DEFAULT), ':role_id'=>$roleId]);
                    logAudit($pdo, $currentUser['id'], 'create', 'users', $pdo->lastInsertId(), null, ['name'=>$name,'username'=>$username,'role_id'=>$roleId]);
                    setFlash('success', 'User added.');
                }
            }
        }
    }

    if ($action === 'toggle') {
        if ($id === (int) $currentUser['id']) {
            setFlash('error', 'You cannot deactivate your own account.');
        } else {
            $current = $pdo->prepare("SELECT status FROM users WHERE id=:id"); $current->execute([':id'=>$id]);
            $status = $current->fetchColumn(); $newStatus = $status === 'active' ? 'inactive' : 'active';
            $pdo->prepare("UPDATE users SET status=:s WHERE id=:id")->execute([':s'=>$newStatus, ':id'=>$id]);
            logAudit($pdo, $currentUser['id'], 'status_change', 'users', $id, ['status'=>$status], ['status'=>$newStatus]);
            setFlash('success', 'User status updated.');
        }
    }

    if ($action === 'delete') {
        if ($id === (int) $currentUser['id']) {
            setFlash('error', 'You cannot delete your own account.');
        } else {
            try {
                $old = $pdo->prepare("SELECT id,name,username,role_id,status FROM users WHERE id=:id"); $old->execute([':id'=>$id]); $oldRow = $old->fetch();
                $pdo->prepare("DELETE FROM users WHERE id=:id")->execute([':id'=>$id]);
                logAudit($pdo, $currentUser['id'], 'delete', 'users', $id, $oldRow, null);
                setFlash('success', 'User deleted.');
            } catch (PDOException $e) {
                setFlash('error', 'Cannot delete this user - they have existing records (entries, updates, etc). Deactivate them instead.');
            }
        }
    }
    redirect('/modules/admin/users.php');
}

$editRow = null;
if (!empty($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=:id"); $stmt->execute([':id'=>(int)$_GET['edit']]); $editRow = $stmt->fetch();
}
$users = $pdo->query("SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id=u.role_id ORDER BY u.id DESC")->fetchAll();

$pageTitle = 'Users';
$activeMenu = 'm-users';
$hideTopbar = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="card">
  <h3 class="mt-0"><?= $editRow ? 'Edit User' : 'Add User' ?></h3>
  <form method="post" action="">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= e($editRow['id'] ?? '') ?>">
    <div class="form-row">
      <div><label>Full Name *</label><input type="text" name="name" required value="<?= e($editRow['name'] ?? '') ?>"></div>
      <div><label>Username *</label><input type="text" name="username" required value="<?= e($editRow['username'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
      <div>
        <label>Role *</label>
        <select name="role_id" required>
          <option value="">Select role</option>
          <?php foreach ($roles as $r): ?>
            <option value="<?= $r['id'] ?>" <?= ($editRow['role_id'] ?? '') == $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Password <?= $editRow ? '(leave blank to keep unchanged)' : '*' ?></label>
        <input type="password" name="password" <?= $editRow ? '' : 'required' ?> minlength="6">
      </div>
    </div>
    <button type="submit" class="btn btn-accent" style="margin-top:14px;"><?= $editRow ? 'Update User' : 'Add User' ?></button>
    <?php if ($editRow): ?><a href="<?= APP_URL ?>/modules/admin/users.php<?= $embedMode ? '?embed=1' : '' ?>" class="btn btn-outline">Cancel</a><?php endif; ?>
  </form>
</div>
<div class="card">
  <h3 class="mt-0"><span class="icon-chip icon-chip-green" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('user') ?></span>All Users</h3>
  <?php if (!$users): ?>
    <p class="row-card-empty">No users yet.</p>
  <?php else: ?>
  <div class="row-list">
    <?php foreach ($users as $u): ?>
      <details class="row-card <?= $u['status'] === 'active' ? 'row-card-good' : 'row-card-neutral' ?>">
        <summary>
          <span class="row-card-heading"><strong><?= e($u['name']) ?></strong> <span class="text-soft"><?= e($u['username']) ?></span> <span class="pill pill-<?= e($u['status']) ?>"><?= e(ucfirst($u['status'])) ?></span></span>
          <span class="row-chevron"><?= tabIcon('chevron') ?></span>
        </summary>
        <div class="row-card-detail">
        <div class="row-card-fields">
          <div><div class="field-label">Role</div><div class="field-value"><?= e($u['role_name']) ?></div></div>
          <div>
            <div class="field-label">Actions</div>
            <div class="row-card-actions">
              <a class="btn btn-sm btn-outline" href="?edit=<?= (int)$u['id'] ?><?= $embedMode ? '&embed=1' : '' ?>">Edit</a>
              <?php if ((int)$u['id'] !== (int)$currentUser['id']): ?>
              <form method="post" style="display:inline" data-confirm="Change status of this user?">
                <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline"><?= $u['status']==='active'?'Deactivate':'Activate' ?></button>
              </form>
              <form method="post" style="display:inline" data-confirm="Delete this user? This cannot be undone.">
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
              </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
