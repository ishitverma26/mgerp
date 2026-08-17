<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
requireRole(['Admin', 'Plant Head']);

$isAdmin = $currentUser['role_name'] === 'Admin';

// Empty jumbo stock isn't logged directly - it's credited automatically from
// every raw_material_inward.jumbo_qty (bags that arrive along with the raw
// material). There's no separate manual "vendor returned" entry either - a
// vendor bringing back bags they were assigned just shows up as their next
// normal raw material delivery, which already has vendor_id + jumbo_qty. This
// helper only tallies what's manually logged AGAINST that stock: bags handed
// to a vendor, and damaged bags. Pass $excludeId when validating an edit so
// the row being edited doesn't count against its own old values.
function emptyJumboTotals(PDO $pdo, int $excludeId = 0): array {
    $received = (float) $pdo->query("SELECT COALESCE(SUM(jumbo_qty),0) FROM raw_material_inward")->fetchColumn();
    $sums = ['assigned' => 0.0, 'damaged' => 0.0];
    foreach ($sums as $type => $_) {
        $sql = "SELECT COALESCE(SUM(bags),0) FROM empty_jumbo_transactions WHERE transaction_type=:t";
        if ($excludeId > 0) $sql .= " AND id != :ex";
        $stmt = $pdo->prepare($sql);
        $params = [':t' => $type];
        if ($excludeId > 0) $params[':ex'] = $excludeId;
        $stmt->execute($params);
        $sums[$type] = (float) $stmt->fetchColumn();
    }
    $stock = $received - $sums['assigned'] - $sums['damaged'];
    return ['received' => $received, 'assigned' => $sums['assigned'], 'damaged' => $sums['damaged'], 'stock' => $stock];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (($action === 'create' || $action === 'edit') && $isAdmin) {
        $id = (int) ($_POST['id'] ?? 0);
        $excludeId = $action === 'edit' ? $id : 0;
        $type = clean($_POST['transaction_type'] ?? '');
        $vendorId = (int) ($_POST['vendor_id'] ?? 0);
        $bags = clean($_POST['bags'] ?? '');
        $date = clean($_POST['transaction_date'] ?? '');
        $remarks = clean($_POST['remarks'] ?? '');

        if (!in_array($type, ['assigned', 'damaged'], true)) {
            setFlash('error', 'Invalid transaction type.');
        } elseif ($date === '' || !is_numeric($bags) || (int) $bags <= 0) {
            setFlash('error', 'Enter a valid date and a bag count.');
        } elseif ($type === 'assigned' && $vendorId <= 0) {
            setFlash('error', 'Select a vendor.');
        } else {
            $bags = (int) $bags;
            $vendorId = $type === 'damaged' ? null : $vendorId;
            $totals = emptyJumboTotals($pdo, $excludeId);

            if ($bags > $totals['stock']) {
                setFlash('error', 'Only ' . (int) $totals['stock'] . ' bags in stock - cannot log ' . $bags . '.');
            } else {
                $new = [':vendor' => $vendorId, ':bags' => $bags, ':date' => $date, ':remarks' => $remarks !== '' ? $remarks : null];
                if ($action === 'edit') {
                    $old = $pdo->prepare("SELECT * FROM empty_jumbo_transactions WHERE id=:id");
                    $old->execute([':id' => $id]);
                    $oldRow = $old->fetch();
                    if (!$oldRow) {
                        setFlash('error', 'Entry not found.');
                    } else {
                        $pdo->prepare("UPDATE empty_jumbo_transactions SET vendor_id=:vendor, bags=:bags, transaction_date=:date, remarks=:remarks, updated_at=NOW() WHERE id=:id")
                            ->execute($new + [':id' => $id]);
                        logAudit($pdo, $currentUser['id'], 'update', 'empty_jumbo_transactions', $id, $oldRow, $new);
                        setFlash('success', 'Entry updated.');
                    }
                } else {
                    $stmt = $pdo->prepare(
                        "INSERT INTO empty_jumbo_transactions (transaction_type, vendor_id, bags, transaction_date, remarks, created_by)
                         VALUES (:type, :vendor, :bags, :date, :remarks, :uid)"
                    );
                    $stmt->execute($new + [':type' => $type, ':uid' => $currentUser['id']]);
                    logAudit($pdo, $currentUser['id'], 'create', 'empty_jumbo_transactions', (int) $pdo->lastInsertId(), null, $new + [':type' => $type]);
                    setFlash('success', 'Entry logged.');
                }
            }
        }
        redirect('/modules/utilities/empty-jumbo.php');
    }

    if ($action === 'delete' && $isAdmin) {
        $id = (int) ($_POST['id'] ?? 0);
        $old = $pdo->prepare("SELECT * FROM empty_jumbo_transactions WHERE id=:id");
        $old->execute([':id' => $id]);
        $oldRow = $old->fetch();
        $pdo->prepare("DELETE FROM empty_jumbo_transactions WHERE id=:id")->execute([':id' => $id]);
        logAudit($pdo, $currentUser['id'], 'delete', 'empty_jumbo_transactions', $id, $oldRow, null);
        setFlash('success', 'Entry deleted.');
        redirect('/modules/utilities/empty-jumbo.php');
    }
}

$editRow = null;
if ($isAdmin && !empty($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM empty_jumbo_transactions WHERE id=:id");
    $stmt->execute([':id' => (int) $_GET['edit']]);
    $editRow = $stmt->fetch();
}

$vendors = $pdo->query("SELECT * FROM vendors WHERE status='active' ORDER BY name")->fetchAll();
$totals = emptyJumboTotals($pdo);

$rows = $pdo->query(
    "SELECT e.*, v.name AS vendor_name, u.name AS user_name
     FROM empty_jumbo_transactions e
     LEFT JOIN vendors v ON v.id = e.vendor_id
     JOIN users u ON u.id = e.created_by
     ORDER BY e.transaction_date DESC, e.id DESC"
)->fetchAll();

// Vendor-wise ledger: one net balance per vendor - negative means the vendor
// is still holding that many of our bags, positive means their deliveries
// since then have brought back more than they were assigned. "Brought back"
// is read directly from their raw material inward deliveries (jumbo_qty),
// not a separate manual entry - only deliveries from the date of their
// FIRST assignment onward count, so older unrelated delivery history isn't
// mistaken for a return. Only vendors we've actually assigned bags to show
// up here at all.
$assignedByVendor = $pdo->query(
    "SELECT vendor_id, SUM(bags) AS total_bags, MIN(transaction_date) AS first_date
     FROM empty_jumbo_transactions WHERE transaction_type='assigned' GROUP BY vendor_id"
)->fetchAll();
$allVendorNames = $pdo->query("SELECT id, name FROM vendors")->fetchAll(PDO::FETCH_KEY_PAIR);
$inwardSinceStmt = $pdo->prepare("SELECT COALESCE(SUM(jumbo_qty),0) FROM raw_material_inward WHERE vendor_id=:v AND inward_date >= :d");

$vendorLedger = [];
foreach ($assignedByVendor as $row) {
    $vid = (int) $row['vendor_id'];
    if (!isset($allVendorNames[$vid])) continue;
    $inwardSinceStmt->execute([':v' => $vid, ':d' => $row['first_date']]);
    $broughtBack = (float) $inwardSinceStmt->fetchColumn();
    $vendorLedger[] = ['name' => $allVendorNames[$vid], 'balance' => $broughtBack - (float) $row['total_bags']];
}
usort($vendorLedger, fn($a, $b) => strcmp($a['name'], $b['name']));
$withVendorsBags = array_sum(array_map(fn($vl) => max(0, -$vl['balance']), $vendorLedger));

$typeLabel = ['assigned' => 'Assigned to Vendor', 'damaged' => 'Damaged'];
$typePill = ['assigned' => 'pending', 'damaged' => 'bad'];
$typeRowClass = ['assigned' => 'pending', 'damaged' => 'bad'];

$pageTitle = 'Empty Jumbo Bags';
$activeMenu = 'empty-jumbo';
$hideTopbar = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="activity-card-row" style="margin-bottom:16px;">
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-amber"><?= tabIcon('archive') ?></span>
      <span class="activity-card-label">Empty Jumbo Stock</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= number_format($totals['stock'], 0) ?></div>
  </div>
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-blue"><?= tabIcon('truck') ?></span>
      <span class="activity-card-label">With Vendors</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= number_format($withVendorsBags, 0) ?></div>
  </div>
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-red"><?= tabIcon('alert') ?></span>
      <span class="activity-card-label">Damaged</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= number_format($totals['damaged'], 0) ?></div>
  </div>
</div>

<?php if ($isAdmin): ?>
<details class="card">
  <summary class="flex-between">
    <h3 class="mt-0" style="margin:0;"><span class="icon-chip icon-chip-amber" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('archive') ?></span>Log Transaction</h3>
    <span class="batch-chevron"><?= tabIcon('chevron') ?></span>
  </summary>
  <div class="card-detail">
  <p class="help-text" style="margin-top:0;">Bags a vendor brings back don't need logging here - they show up automatically the next time that vendor's raw material delivery is entered.</p>
  <label>Transaction Type</label>
  <div class="segmented segmented-full" id="ejTypeGroup">
    <button type="button" class="segmented-btn active" data-type="assigned">Assign to Vendor</button>
    <button type="button" class="segmented-btn" data-type="damaged">Damaged</button>
  </div>
  <form method="post" action="" style="margin-top:12px;">
    <input type="hidden" name="action" value="create">
    <input type="hidden" name="transaction_type" id="ejTypeInput" value="assigned">
    <div class="form-row">
      <div id="ejVendorField">
        <label>Vendor *</label>
        <select name="vendor_id" id="ejVendorSelect">
          <option value="">Select vendor</option>
          <?php foreach ($vendors as $v): ?>
            <option value="<?= $v['id'] ?>"><?= e($v['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label>Bags *</label><input type="number" step="1" min="1" name="bags" required></div>
    </div>
    <div class="form-row">
      <div><label>Date *</label><input type="date" name="transaction_date" required value="<?= date('Y-m-d') ?>"></div>
      <div><label>Remarks</label><input type="text" name="remarks" maxlength="255"></div>
    </div>
    <button type="submit" class="btn btn-accent" style="margin-top:14px;width:100%;">Log Entry</button>
  </form>
  <script>
  (function () {
    var group = document.getElementById('ejTypeGroup');
    var typeInput = document.getElementById('ejTypeInput');
    var vendorField = document.getElementById('ejVendorField');
    var vendorSelect = document.getElementById('ejVendorSelect');
    if (!group) return;

    function applyType(type) {
      typeInput.value = type;
      var needsVendor = type === 'assigned';
      vendorField.style.display = needsVendor ? '' : 'none';
      if (vendorSelect) vendorSelect.required = needsVendor;
    }

    group.querySelectorAll('.segmented-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        group.querySelectorAll('.segmented-btn').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        applyType(btn.getAttribute('data-type'));
      });
    });
    applyType(typeInput.value);
  })();
  </script>
  </div>
</details>
<?php endif; ?>

<details class="card">
  <summary class="flex-between">
    <h3 class="mt-0" style="margin:0;"><span class="icon-chip icon-chip-blue" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('book') ?></span>Vendor-wise Ledger</h3>
    <span class="batch-chevron"><?= tabIcon('chevron') ?></span>
  </summary>
  <div class="card-detail">
  <p class="help-text" style="margin-top:0;">Negative = vendor is still holding that many of our bags. Positive = their deliveries have brought back more than assigned.</p>
  <?php if (!$vendorLedger): ?>
    <p class="row-card-empty">No vendor activity yet.</p>
  <?php else: ?>
  <div class="row-list">
    <?php foreach ($vendorLedger as $vl): ?>
      <div class="row-card row-card-info">
        <div class="row-card-heading" style="margin-bottom:0;display:flex;justify-content:space-between;align-items:center;width:100%;">
          <strong><?= e($vl['name']) ?></strong>
          <span class="pill pill-<?= $vl['balance'] < 0 ? 'pending' : 'active' ?>"><?= $vl['balance'] > 0 ? '+' : '' ?><?= number_format($vl['balance'], 0) ?> bags</span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  </div>
</details>

<details class="card"<?= $editRow ? ' open' : '' ?>>
  <summary class="flex-between">
    <h3 class="mt-0" style="margin:0;"><span class="icon-chip icon-chip-purple" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('clock') ?></span>Transaction History</h3>
    <span class="batch-chevron"><?= tabIcon('chevron') ?></span>
  </summary>
  <div class="card-detail">
  <?php if (!$rows): ?>
    <p class="row-card-empty">No transactions logged yet.</p>
  <?php else: ?>
  <div class="row-list">
    <?php foreach ($rows as $r): ?>
      <?php $isEditing = $editRow && (int) $editRow['id'] === (int) $r['id']; ?>
      <details class="row-card row-card-<?= $typeRowClass[$r['transaction_type']] ?>"<?= $isEditing ? ' open' : '' ?>>
        <summary>
          <span class="row-card-heading">
            <strong><?= (int) $r['bags'] ?> bags</strong>
            <span class="text-soft"><?= formatDate($r['transaction_date']) ?></span>
            <span class="pill pill-<?= $typePill[$r['transaction_type']] ?>"><?= $typeLabel[$r['transaction_type']] ?></span>
          </span>
          <span class="row-chevron"><?= tabIcon('chevron') ?></span>
        </summary>
        <div class="row-card-detail">
        <?php if ($isEditing): ?>
          <form method="post" action="">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <input type="hidden" name="transaction_type" value="<?= e($r['transaction_type']) ?>">
            <?php if ($r['transaction_type'] !== 'damaged'): ?>
            <div class="form-row">
              <div>
                <label>Vendor *</label>
                <select name="vendor_id" required>
                  <option value="">Select vendor</option>
                  <?php foreach ($vendors as $v): ?>
                    <option value="<?= $v['id'] ?>"<?= (int) $r['vendor_id'] === (int) $v['id'] ? ' selected' : '' ?>><?= e($v['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div><label>Bags *</label><input type="number" step="1" min="1" name="bags" required value="<?= (int) $r['bags'] ?>"></div>
            </div>
            <?php else: ?>
            <div class="form-row">
              <div><label>Bags *</label><input type="number" step="1" min="1" name="bags" required value="<?= (int) $r['bags'] ?>"></div>
            </div>
            <?php endif; ?>
            <div class="form-row">
              <div><label>Date *</label><input type="date" name="transaction_date" required value="<?= e($r['transaction_date']) ?>"></div>
              <div><label>Remarks</label><input type="text" name="remarks" maxlength="255" value="<?= e($r['remarks']) ?>"></div>
            </div>
            <div class="row-card-actions" style="margin-top:12px;">
              <button type="submit" class="btn btn-sm btn-accent">Update</button>
              <a href="<?= APP_URL ?>/modules/utilities/empty-jumbo.php<?= $embedMode ? '?embed=1' : '' ?>" class="btn btn-sm btn-outline">Cancel</a>
            </div>
          </form>
        <?php else: ?>
        <div class="row-card-fields">
          <?php if ($r['vendor_name']): ?><div><div class="field-label">Vendor</div><div class="field-value"><?= e($r['vendor_name']) ?></div></div><?php endif; ?>
          <div><div class="field-label">Bags</div><div class="field-value"><?= (int) $r['bags'] ?></div></div>
          <div><div class="field-label">Remarks</div><div class="field-value"><?= e($r['remarks']) ?: '-' ?></div></div>
          <div><div class="field-label">Logged By</div><div class="field-value"><?= e($r['user_name']) ?></div></div>
        </div>
        <?php if ($isAdmin): ?>
          <div class="row-card-actions" style="margin-top:12px;">
            <a class="btn btn-sm btn-outline" href="?edit=<?= (int) $r['id'] ?><?= $embedMode ? '&embed=1' : '' ?>">Edit</a>
            <form method="post" style="display:inline" data-confirm="Delete this entry? This cannot be undone.">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger">Delete</button>
            </form>
          </div>
        <?php endif; ?>
        <?php endif; ?>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  </div>
</details>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
