<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
requireRole(['Admin', 'Plant Head']);

$isAdmin = $currentUser['role_name'] === 'Admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || ($action === 'edit' && $isAdmin)) {
        $id = (int) ($_POST['id'] ?? 0);
        $receivedAt = clean($_POST['received_datetime'] ?? '');
        $qty = clean($_POST['quantity_liters'] ?? '');
        $priceRaw = clean($_POST['price_paid'] ?? '');
        // Price is Admin-only bookkeeping - Plant Head logs quantity
        // received, Admin fills in the price later via Edit.
        $price = ($isAdmin && $priceRaw !== '') ? $priceRaw : null;
        $remarks = clean($_POST['remarks'] ?? '');

        if ($receivedAt === '' || !is_numeric($qty) || $qty <= 0 || ($price !== null && (!is_numeric($price) || $price < 0))) {
            setFlash('error', 'Enter a valid date/time and quantity.');
        } elseif ($action === 'edit') {
            $old = $pdo->prepare("SELECT * FROM water_tanker_log WHERE id=:id");
            $old->execute([':id' => $id]);
            $oldRow = $old->fetch();
            $pdo->prepare("UPDATE water_tanker_log SET received_datetime=:dt, quantity_liters=:q, price_paid=:p, remarks=:r, updated_at=NOW() WHERE id=:id")
                ->execute([':dt' => $receivedAt, ':q' => $qty, ':p' => $price, ':r' => $remarks !== '' ? $remarks : null, ':id' => $id]);
            logAudit($pdo, $currentUser['id'], 'update', 'water_tanker_log', $id, $oldRow, [
                'received_datetime' => $receivedAt, 'quantity_liters' => $qty, 'price_paid' => $price, 'remarks' => $remarks,
            ]);
            setFlash('success', 'Entry updated.');
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO water_tanker_log (received_datetime, quantity_liters, price_paid, remarks, created_by) VALUES (:dt, :q, :p, :r, :uid)"
            );
            $stmt->execute([
                ':dt' => $receivedAt, ':q' => $qty, ':p' => $price, ':r' => $remarks !== '' ? $remarks : null, ':uid' => $currentUser['id'],
            ]);
            logAudit($pdo, $currentUser['id'], 'create', 'water_tanker_log', (int) $pdo->lastInsertId(), null, [
                'received_datetime' => $receivedAt, 'quantity_liters' => $qty, 'price_paid' => $price, 'remarks' => $remarks,
            ]);
            setFlash('success', 'Water tanker entry logged.');
        }
        redirect('/modules/utilities/water-tanker.php');
    }

    if ($action === 'delete' && $isAdmin) {
        $id = (int) ($_POST['id'] ?? 0);
        $old = $pdo->prepare("SELECT * FROM water_tanker_log WHERE id=:id");
        $old->execute([':id' => $id]);
        $oldRow = $old->fetch();
        $pdo->prepare("DELETE FROM water_tanker_log WHERE id=:id")->execute([':id' => $id]);
        logAudit($pdo, $currentUser['id'], 'delete', 'water_tanker_log', $id, $oldRow, null);
        setFlash('success', 'Entry deleted.');
        redirect('/modules/utilities/water-tanker.php');
    }
}

$editRow = null;
if ($isAdmin && !empty($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM water_tanker_log WHERE id=:id");
    $stmt->execute([':id' => (int) $_GET['edit']]);
    $editRow = $stmt->fetch();
}

$rows = $pdo->query(
    "SELECT w.*, u.name AS user_name FROM water_tanker_log w JOIN users u ON u.id = w.created_by ORDER BY w.received_datetime DESC"
)->fetchAll();
$totalLiters = array_sum(array_column($rows, 'quantity_liters'));
$totalCost = array_sum(array_column($rows, 'price_paid'));

$pageTitle = 'Water Tanker';
$activeMenu = 'water-tanker';
$hideTopbar = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="activity-card-row" style="margin-bottom:16px;">
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-blue"><?= tabIcon('truck') ?></span>
      <span class="activity-card-label">Entries</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= count($rows) ?></div>
  </div>
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-blue"><?= tabIcon('archive') ?></span>
      <span class="activity-card-label">Total Liters</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= number_format($totalLiters, 0) ?></div>
  </div>
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-purple"><?= tabIcon('dollar') ?></span>
      <span class="activity-card-label">Total Cost</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value">Rs <?= number_format($totalCost, 0) ?></div>
  </div>
</div>

<details class="card">
  <summary class="flex-between">
    <h3 class="mt-0" style="margin:0;">Log Water Tanker Received</h3>
    <span class="batch-chevron"><?= tabIcon('chevron') ?></span>
  </summary>
  <div class="card-detail">
  <label>Tanker Size</label>
  <div class="segmented segmented-full" id="tankerPreset">
    <button type="button" class="segmented-btn" data-qty="7000" data-price="800">7000 L</button>
    <button type="button" class="segmented-btn" data-qty="14000" data-price="1500">14000 L</button>
    <button type="button" class="segmented-btn active" data-qty="" data-price="">Custom</button>
  </div>
  <form method="post" action="" style="margin-top:12px;">
    <input type="hidden" name="action" value="create">
    <div class="form-row">
      <div>
        <label>Received On *</label>
        <input type="datetime-local" name="received_datetime" required value="<?= date('Y-m-d\TH:i') ?>">
      </div>
      <div><label>Quantity (Liters) *</label><input type="number" step="0.01" min="0.01" name="quantity_liters" id="tankerQty" required></div>
    </div>
    <div class="form-row">
      <?php if ($isAdmin): ?>
      <div><label>Price Paid (Rs) *</label><input type="number" step="0.01" min="0" name="price_paid" id="tankerPrice" required></div>
      <?php endif; ?>
      <div><label>Remarks</label><input type="text" name="remarks" maxlength="255"></div>
    </div>
    <button type="submit" class="btn btn-accent" style="margin-top:14px;width:100%;">Log Entry</button>
  </form>
  <script>
  (function () {
    var group = document.getElementById('tankerPreset');
    var qtyInput = document.getElementById('tankerQty');
    var priceInput = document.getElementById('tankerPrice');
    if (!group) return;
    group.querySelectorAll('.segmented-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        group.querySelectorAll('.segmented-btn').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        var qty = btn.getAttribute('data-qty');
        var price = btn.getAttribute('data-price');
        if (qty) qtyInput.value = qty;
        if (price && priceInput) priceInput.value = price;
        // Prices vary per delivery even for the same size, so this is just
        // a starting point - both fields stay editable either way.
      });
    });
  })();
  </script>
  </div>
</details>

<details class="card"<?= $editRow ? ' open' : '' ?>>
  <summary class="flex-between">
    <h3 class="mt-0" style="margin:0;"><span class="icon-chip icon-chip-blue" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('truck') ?></span>Water Tanker Log</h3>
    <span class="batch-chevron"><?= tabIcon('chevron') ?></span>
  </summary>
  <div class="card-detail">
  <?php if (!$rows): ?>
    <p class="row-card-empty">No water tanker entries logged yet.</p>
  <?php else: ?>
  <div class="row-list">
    <?php foreach ($rows as $r): ?>
      <?php $isEditing = $editRow && (int) $editRow['id'] === (int) $r['id']; ?>
      <details class="row-card row-card-info"<?= $isEditing ? ' open' : '' ?>>
        <summary>
          <span class="row-card-heading"><strong><?= number_format($r['quantity_liters'], 0) ?> L</strong> <span class="text-soft"><?= formatDateTime($r['received_datetime']) ?></span> <?php if ($r['price_paid'] !== null): ?><span class="pill pill-active">Rs <?= number_format($r['price_paid'], 0) ?></span><?php endif; ?></span>
          <span class="row-chevron"><?= tabIcon('chevron') ?></span>
        </summary>
        <div class="row-card-detail">
        <?php if ($isEditing): ?>
          <form method="post" action="">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <div class="form-row">
              <div><label>Received On *</label><input type="datetime-local" name="received_datetime" required value="<?= e(str_replace(' ', 'T', substr($r['received_datetime'], 0, 16))) ?>"></div>
              <div><label>Quantity (Liters) *</label><input type="number" step="0.01" min="0.01" name="quantity_liters" required value="<?= e($r['quantity_liters']) ?>"></div>
            </div>
            <div class="form-row">
              <div><label>Price Paid (Rs) *</label><input type="number" step="0.01" min="0" name="price_paid" required value="<?= e($r['price_paid']) ?>"></div>
              <div><label>Remarks</label><input type="text" name="remarks" maxlength="255" value="<?= e($r['remarks']) ?>"></div>
            </div>
            <div class="row-card-actions" style="margin-top:12px;">
              <button type="submit" class="btn btn-sm btn-accent">Update</button>
              <a href="<?= APP_URL ?>/modules/utilities/water-tanker.php<?= $embedMode ? '?embed=1' : '' ?>" class="btn btn-sm btn-outline">Cancel</a>
            </div>
          </form>
        <?php else: ?>
        <div class="row-card-fields">
          <div><div class="field-label">Quantity</div><div class="field-value"><?= number_format($r['quantity_liters'], 2) ?> L</div></div>
          <div><div class="field-label">Price Paid</div><div class="field-value"><?= $r['price_paid'] !== null ? 'Rs ' . number_format($r['price_paid'], 2) : '-' ?></div></div>
          <div><div class="field-label">Remarks</div><div class="field-value"><?= e($r['remarks']) ?: '-' ?></div></div>
          <div><div class="field-label">Logged By</div><div class="field-value"><?= e($r['user_name']) ?></div></div>
        </div>
        <?php if ($isAdmin): ?>
          <div class="row-card-actions" style="margin-top:12px;">
            <a class="btn btn-sm btn-outline" href="?edit=<?= (int) $r['id'] ?><?= $embedMode ? '&embed=1' : '' ?>">Edit</a>
            <form method="post" style="display:inline" data-confirm="Delete this water tanker entry? This cannot be undone.">
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
