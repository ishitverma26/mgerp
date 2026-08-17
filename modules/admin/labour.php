<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
requireRole(['Admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'save') {
        $name = clean($_POST['name'] ?? '');
        $wage = clean($_POST['daily_wage'] ?? '');
        if ($name === '') {
            setFlash('error', 'Labour name is required.');
        } elseif ($wage !== '' && (!is_numeric($wage) || $wage < 0)) {
            setFlash('error', 'Enter a valid daily wage.');
        } else {
            $new = ['name' => $name, 'daily_wage' => $wage !== '' ? $wage : null];
            if ($id > 0) {
                $old = $pdo->prepare("SELECT * FROM labour WHERE id=:id"); $old->execute([':id' => $id]); $oldRow = $old->fetch();
                $pdo->prepare("UPDATE labour SET name=:name, daily_wage=:daily_wage WHERE id=:id")->execute($new + [':id' => $id]);
                logAudit($pdo, $currentUser['id'], 'update', 'labour', $id, $oldRow, $new);
                setFlash('success', 'Labour updated.');
            } else {
                $pdo->prepare("INSERT INTO labour (name, daily_wage, created_by) VALUES (:name, :daily_wage, :created_by)")
                    ->execute($new + [':created_by' => $currentUser['id']]);
                logAudit($pdo, $currentUser['id'], 'create', 'labour', (int) $pdo->lastInsertId(), null, $new);
                setFlash('success', 'Labour added.');
            }
        }
    }

    if ($action === 'toggle') {
        $current = $pdo->prepare("SELECT status FROM labour WHERE id=:id"); $current->execute([':id' => $id]);
        $status = $current->fetchColumn(); $newStatus = $status === 'active' ? 'inactive' : 'active';
        $pdo->prepare("UPDATE labour SET status=:s WHERE id=:id")->execute([':s' => $newStatus, ':id' => $id]);
        logAudit($pdo, $currentUser['id'], 'status_change', 'labour', $id, ['status' => $status], ['status' => $newStatus]);
        setFlash('success', 'Status updated.');
    }

    if ($action === 'delete') {
        try {
            $old = $pdo->prepare("SELECT * FROM labour WHERE id=:id"); $old->execute([':id' => $id]); $oldRow = $old->fetch();
            $pdo->prepare("DELETE FROM labour WHERE id=:id")->execute([':id' => $id]);
            logAudit($pdo, $currentUser['id'], 'delete', 'labour', $id, $oldRow, null);
            setFlash('success', 'Labour removed.');
        } catch (PDOException $e) {
            setFlash('error', 'Cannot delete this labour - it already has attendance recorded. Deactivate instead.');
        }
    }
    redirect('/modules/admin/labour.php');
}

$editRow = null;
if (!empty($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM labour WHERE id=:id"); $stmt->execute([':id' => (int) $_GET['edit']]); $editRow = $stmt->fetch();
}
$rows = $pdo->query("SELECT * FROM labour ORDER BY id DESC")->fetchAll();

$pageTitle = 'Labour';
$activeMenu = 'm-labour';
$hideTopbar = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="card">
  <h3 class="mt-0"><?= $editRow ? 'Edit Labour' : 'Add Labour' ?></h3>
  <p class="help-text">Everyone active here shows up on Plant Head's daily attendance page.</p>
  <form method="post" action="">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= e($editRow['id'] ?? '') ?>">
    <div class="form-row">
      <div><label>Name *</label><input type="text" name="name" required value="<?= e($editRow['name'] ?? '') ?>" placeholder="e.g. Ramesh Kumar"></div>
      <div><label>Daily Wage (Rs)</label><input type="number" step="0.01" min="0" name="daily_wage" value="<?= e($editRow['daily_wage'] ?? '') ?>" placeholder="e.g. 600"></div>
    </div>
    <button type="submit" class="btn btn-accent" style="margin-top:14px;"><?= $editRow ? 'Update' : 'Add' ?></button>
    <?php if ($editRow): ?><a href="<?= APP_URL ?>/modules/admin/labour.php<?= $embedMode ? '?embed=1' : '' ?>" class="btn btn-outline">Cancel</a><?php endif; ?>
  </form>
</div>
<div class="card">
  <h3 class="mt-0"><span class="icon-chip icon-chip-green" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('user') ?></span>All Labour</h3>
  <?php if (!$rows): ?>
    <p class="row-card-empty">No labour added yet.</p>
  <?php else: ?>
  <div class="row-list">
    <?php foreach ($rows as $r): ?>
      <details class="row-card <?= $r['status'] === 'active' ? 'row-card-good' : 'row-card-neutral' ?>">
        <summary>
          <span class="row-card-heading"><strong><?= e($r['name']) ?></strong> <span class="text-soft"><?= $r['daily_wage'] !== null ? 'Rs ' . number_format($r['daily_wage'], 2) . '/day' : 'No wage set' ?></span> <span class="pill pill-<?= e($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span></span>
          <span class="row-chevron"><?= tabIcon('chevron') ?></span>
        </summary>
        <div class="row-card-detail">
        <div class="row-card-actions">
          <a class="btn btn-sm btn-outline" href="?edit=<?= (int) $r['id'] ?><?= $embedMode ? '&embed=1' : '' ?>">Edit</a>
          <form method="post" style="display:inline" data-confirm="Change status?">
            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline"><?= $r['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
          </form>
          <form method="post" style="display:inline" data-confirm="Remove this labour? This cannot be undone.">
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
          </form>
        </div>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
