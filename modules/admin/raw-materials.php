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
        $unit = clean($_POST['unit'] ?? 'MT');
        if ($name === '') {
            setFlash('error', 'Raw material name is required.');
        } else {
            $new = ['name' => $name, 'unit' => $unit];
            if ($id > 0) {
                $old = $pdo->prepare("SELECT * FROM raw_materials WHERE id=:id"); $old->execute([':id'=>$id]); $oldRow = $old->fetch();
                $pdo->prepare("UPDATE raw_materials SET name=:name, unit=:unit WHERE id=:id")->execute($new + [':id'=>$id]);
                logAudit($pdo, $currentUser['id'], 'update', 'raw_materials', $id, $oldRow, $new);
                setFlash('success', 'Raw material updated.');
            } else {
                $pdo->prepare("INSERT INTO raw_materials (name, unit) VALUES (:name,:unit)")->execute($new);
                logAudit($pdo, $currentUser['id'], 'create', 'raw_materials', $pdo->lastInsertId(), null, $new);
                setFlash('success', 'Raw material added.');
            }
        }
    }
    if ($action === 'toggle') {
        $current = $pdo->prepare("SELECT status FROM raw_materials WHERE id=:id"); $current->execute([':id'=>$id]);
        $status = $current->fetchColumn(); $newStatus = $status === 'active' ? 'inactive' : 'active';
        $pdo->prepare("UPDATE raw_materials SET status=:s WHERE id=:id")->execute([':s'=>$newStatus, ':id'=>$id]);
        logAudit($pdo, $currentUser['id'], 'status_change', 'raw_materials', $id, ['status'=>$status], ['status'=>$newStatus]);
        setFlash('success', 'Status updated.');
    }

    if ($action === 'delete') {
        try {
            $old = $pdo->prepare("SELECT * FROM raw_materials WHERE id=:id"); $old->execute([':id'=>$id]); $oldRow = $old->fetch();
            $pdo->prepare("DELETE FROM raw_materials WHERE id=:id")->execute([':id'=>$id]);
            logAudit($pdo, $currentUser['id'], 'delete', 'raw_materials', $id, $oldRow, null);
            setFlash('success', 'Raw material deleted.');
        } catch (PDOException $e) {
            setFlash('error', 'Cannot delete this raw material - it is used in existing records (inward, stock, processing, etc). Deactivate it instead.');
        }
    }
    redirect('/modules/admin/raw-materials.php');
}

$editRow = null;
if (!empty($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM raw_materials WHERE id=:id"); $stmt->execute([':id'=>(int)$_GET['edit']]); $editRow = $stmt->fetch();
}
$rows = $pdo->query("SELECT * FROM raw_materials ORDER BY id DESC")->fetchAll();

$pageTitle = 'Raw Materials';
$activeMenu = 'm-raw-materials';
$hideTopbar = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="card">
  <h3 class="mt-0"><?= $editRow ? 'Edit Raw Material' : 'Add Raw Material' ?></h3>
  <form method="post" action="">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= e($editRow['id'] ?? '') ?>">
    <div class="form-row">
      <div><label>Name *</label><input type="text" name="name" required value="<?= e($editRow['name'] ?? '') ?>"></div>
      <div><label>Unit</label><input type="text" name="unit" value="<?= e($editRow['unit'] ?? 'MT') ?>"></div>
    </div>
    <button type="submit" class="btn btn-accent" style="margin-top:14px;"><?= $editRow ? 'Update' : 'Add' ?></button>
    <?php if ($editRow): ?><a href="<?= APP_URL ?>/modules/admin/raw-materials.php<?= $embedMode ? '?embed=1' : '' ?>" class="btn btn-outline">Cancel</a><?php endif; ?>
  </form>
</div>
<div class="card">
  <h3 class="mt-0"><span class="icon-chip icon-chip-blue" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('archive') ?></span>All Raw Materials</h3>
  <?php if (!$rows): ?>
    <p class="row-card-empty">No raw materials yet.</p>
  <?php else: ?>
  <div class="row-list">
    <?php foreach ($rows as $r): ?>
      <details class="row-card <?= $r['status'] === 'active' ? 'row-card-good' : 'row-card-neutral' ?>">
        <summary>
          <span class="row-card-heading"><strong><?= e($r['name']) ?></strong> <span class="pill pill-<?= e($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span></span>
          <span class="row-chevron"><?= tabIcon('chevron') ?></span>
        </summary>
        <div class="row-card-detail">
        <div class="row-card-fields">
          <div><div class="field-label">Unit</div><div class="field-value"><?= e($r['unit']) ?></div></div>
          <div>
            <div class="field-label">Actions</div>
            <div class="row-card-actions">
              <a class="btn btn-sm btn-outline" href="?edit=<?= (int)$r['id'] ?><?= $embedMode ? '&embed=1' : '' ?>">Edit</a>
              <form method="post" style="display:inline" data-confirm="Change status?">
                <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline"><?= $r['status']==='active'?'Deactivate':'Activate' ?></button>
              </form>
              <form method="post" style="display:inline" data-confirm="Delete this raw material? This cannot be undone.">
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
              </form>
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
