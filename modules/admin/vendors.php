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
        $contact = clean($_POST['contact_no'] ?? '');
        $address = clean($_POST['address'] ?? '');
        $gst = clean($_POST['gst_no'] ?? '');

        if ($name === '') {
            setFlash('error', 'Vendor name is required.');
        } else {
            $new = ['name' => $name, 'contact_no' => $contact, 'address' => $address, 'gst_no' => $gst];
            if ($id > 0) {
                $old = $pdo->prepare("SELECT * FROM vendors WHERE id=:id");
                $old->execute([':id' => $id]);
                $oldRow = $old->fetch();
                $pdo->prepare("UPDATE vendors SET name=:name, contact_no=:contact_no, address=:address, gst_no=:gst_no WHERE id=:id")
                    ->execute($new + [':id' => $id]);
                logAudit($pdo, $currentUser['id'], 'update', 'vendors', $id, $oldRow, $new);
                setFlash('success', 'Vendor updated.');
            } else {
                $pdo->prepare("INSERT INTO vendors (name, contact_no, address, gst_no) VALUES (:name,:contact_no,:address,:gst_no)")
                    ->execute($new);
                logAudit($pdo, $currentUser['id'], 'create', 'vendors', $pdo->lastInsertId(), null, $new);
                setFlash('success', 'Vendor added.');
            }
        }
    }

    if ($action === 'toggle') {
        $current = $pdo->prepare("SELECT status FROM vendors WHERE id=:id");
        $current->execute([':id' => $id]);
        $status = $current->fetchColumn();
        $newStatus = $status === 'active' ? 'inactive' : 'active';
        $pdo->prepare("UPDATE vendors SET status=:s WHERE id=:id")->execute([':s' => $newStatus, ':id' => $id]);
        logAudit($pdo, $currentUser['id'], 'status_change', 'vendors', $id, ['status' => $status], ['status' => $newStatus]);
        setFlash('success', 'Vendor status updated.');
    }

    if ($action === 'delete') {
        try {
            $old = $pdo->prepare("SELECT * FROM vendors WHERE id=:id"); $old->execute([':id' => $id]); $oldRow = $old->fetch();
            $pdo->prepare("DELETE FROM vendors WHERE id=:id")->execute([':id' => $id]);
            logAudit($pdo, $currentUser['id'], 'delete', 'vendors', $id, $oldRow, null);
            setFlash('success', 'Vendor deleted.');
        } catch (PDOException $e) {
            setFlash('error', 'Cannot delete this vendor - it is used in existing records (inward entries, etc). Deactivate it instead.');
        }
    }
    redirect('/modules/admin/vendors.php');
}

$editRow = null;
if (!empty($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM vendors WHERE id=:id");
    $stmt->execute([':id' => (int) $_GET['edit']]);
    $editRow = $stmt->fetch();
}
$vendors = $pdo->query("SELECT * FROM vendors ORDER BY id DESC")->fetchAll();

$pageTitle = 'Vendors';
$activeMenu = 'm-vendors';
$hideTopbar = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="card">
  <h3 class="mt-0"><?= $editRow ? 'Edit Vendor' : 'Add Vendor' ?></h3>
  <form method="post" action="">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= e($editRow['id'] ?? '') ?>">
    <div class="form-row">
      <div><label>Vendor Name *</label><input type="text" name="name" required value="<?= e($editRow['name'] ?? '') ?>"></div>
      <div><label>Contact No.</label><input type="text" name="contact_no" value="<?= e($editRow['contact_no'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
      <div><label>Address</label><input type="text" name="address" value="<?= e($editRow['address'] ?? '') ?>"></div>
      <div><label>GST No.</label><input type="text" name="gst_no" value="<?= e($editRow['gst_no'] ?? '') ?>"></div>
    </div>
    <button type="submit" class="btn btn-accent" style="margin-top:14px;"><?= $editRow ? 'Update Vendor' : 'Add Vendor' ?></button>
    <?php if ($editRow): ?><a href="<?= APP_URL ?>/modules/admin/vendors.php<?= $embedMode ? '?embed=1' : '' ?>" class="btn btn-outline">Cancel</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <h3 class="mt-0"><span class="icon-chip icon-chip-green" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('briefcase') ?></span>All Vendors</h3>
  <?php if (!$vendors): ?>
    <p class="row-card-empty">No vendors yet.</p>
  <?php else: ?>
  <div class="row-list">
    <?php foreach ($vendors as $v): ?>
      <details class="row-card <?= $v['status'] === 'active' ? 'row-card-good' : 'row-card-neutral' ?>">
        <summary>
          <span class="row-card-heading"><strong><?= e($v['name']) ?></strong> <span class="pill pill-<?= e($v['status']) ?>"><?= e(ucfirst($v['status'])) ?></span></span>
          <span class="row-chevron"><?= tabIcon('chevron') ?></span>
        </summary>
        <div class="row-card-detail">
        <div class="row-card-fields">
          <div><div class="field-label">Contact</div><div class="field-value"><?= e($v['contact_no']) ?: '-' ?></div></div>
          <div><div class="field-label">GST No.</div><div class="field-value"><?= e($v['gst_no']) ?: '-' ?></div></div>
          <div>
            <div class="field-label">Actions</div>
            <div class="row-card-actions">
              <a class="btn btn-sm btn-outline" href="?edit=<?= (int) $v['id'] ?><?= $embedMode ? '&embed=1' : '' ?>">Edit</a>
              <form method="post" style="display:inline" data-confirm="Change status of this vendor?">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline"><?= $v['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
              </form>
              <form method="post" style="display:inline" data-confirm="Delete this vendor? This cannot be undone.">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
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
