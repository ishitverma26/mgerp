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
        $tokenValue = clean($_POST['token_value'] ?? '');
        if ($tokenValue === '') {
            setFlash('error', 'Token value is required (use N/A if there is no token).');
        } else {
            $new = ['token_value' => $tokenValue];
            if ($id > 0) {
                $old = $pdo->prepare("SELECT * FROM tokens WHERE id=:id"); $old->execute([':id'=>$id]); $oldRow = $old->fetch();
                $pdo->prepare("UPDATE tokens SET token_value=:token_value WHERE id=:id")->execute($new + [':id'=>$id]);
                logAudit($pdo, $currentUser['id'], 'update', 'tokens', $id, $oldRow, $new);
                setFlash('success', 'Token updated.');
            } else {
                $pdo->prepare("INSERT INTO tokens (token_value) VALUES (:token_value)")->execute($new);
                logAudit($pdo, $currentUser['id'], 'create', 'tokens', $pdo->lastInsertId(), null, $new);
                setFlash('success', 'Token added.');
            }
        }
    }
    if ($action === 'toggle') {
        $current = $pdo->prepare("SELECT status FROM tokens WHERE id=:id"); $current->execute([':id'=>$id]);
        $status = $current->fetchColumn(); $newStatus = $status === 'active' ? 'inactive' : 'active';
        $pdo->prepare("UPDATE tokens SET status=:s WHERE id=:id")->execute([':s'=>$newStatus, ':id'=>$id]);
        logAudit($pdo, $currentUser['id'], 'status_change', 'tokens', $id, ['status'=>$status], ['status'=>$newStatus]);
        setFlash('success', 'Status updated.');
    }

    if ($action === 'delete') {
        try {
            $old = $pdo->prepare("SELECT * FROM tokens WHERE id=:id"); $old->execute([':id'=>$id]); $oldRow = $old->fetch();
            $pdo->prepare("DELETE FROM tokens WHERE id=:id")->execute([':id'=>$id]);
            logAudit($pdo, $currentUser['id'], 'delete', 'tokens', $id, $oldRow, null);
            setFlash('success', 'Token deleted.');
        } catch (PDOException $e) {
            setFlash('error', 'Cannot delete this token - it is used in existing production batches. Deactivate it instead.');
        }
    }
    redirect('/modules/admin/tokens.php');
}

$editRow = null;
if (!empty($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM tokens WHERE id=:id"); $stmt->execute([':id'=>(int)$_GET['edit']]); $editRow = $stmt->fetch();
}
$rows = $pdo->query("SELECT * FROM tokens ORDER BY id DESC")->fetchAll();

$pageTitle = 'Tokens';
$activeMenu = 'm-tokens';
$hideTopbar = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="card">
  <h3 class="mt-0"><?= $editRow ? 'Edit Token' : 'Add Token' ?></h3>
  <p class="help-text">Tokens are not fixed to one product - "N/A" is a valid token for batches with no token.</p>
  <form method="post" action="">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= e($editRow['id'] ?? '') ?>">
    <label>Token Value *</label>
    <input type="text" name="token_value" required value="<?= e($editRow['token_value'] ?? '') ?>" placeholder="e.g. 100 or N/A">
    <button type="submit" class="btn btn-accent" style="margin-top:14px;"><?= $editRow ? 'Update' : 'Add' ?></button>
    <?php if ($editRow): ?><a href="<?= APP_URL ?>/modules/admin/tokens.php<?= $embedMode ? '?embed=1' : '' ?>" class="btn btn-outline">Cancel</a><?php endif; ?>
  </form>
</div>
<div class="card">
  <h3 class="mt-0"><span class="icon-chip icon-chip-blue" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('hash') ?></span>All Tokens</h3>
  <?php if (!$rows): ?>
    <p class="row-card-empty">No tokens yet.</p>
  <?php else: ?>
  <div class="row-list">
    <?php foreach ($rows as $r): ?>
      <details class="row-card <?= $r['status'] === 'active' ? 'row-card-good' : 'row-card-neutral' ?>">
        <summary>
          <span class="row-card-heading"><strong><?= e($r['token_value']) ?></strong> <span class="pill pill-<?= e($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span></span>
          <span class="row-chevron"><?= tabIcon('chevron') ?></span>
        </summary>
        <div class="row-card-detail">
        <div class="row-card-actions">
          <a class="btn btn-sm btn-outline" href="?edit=<?= (int)$r['id'] ?><?= $embedMode ? '&embed=1' : '' ?>">Edit</a>
          <form method="post" style="display:inline" data-confirm="Change status?">
            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline"><?= $r['status']==='active'?'Deactivate':'Activate' ?></button>
          </form>
          <form method="post" style="display:inline" data-confirm="Delete this token? This cannot be undone.">
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
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
