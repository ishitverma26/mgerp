<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
requireRole(['Admin']);

$allowedPaymentStatuses = ['pending', 'partial', 'paid'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $freightStatus = in_array($_POST['freight_payment_status'] ?? '', $allowedPaymentStatuses, true) ? $_POST['freight_payment_status'] : 'pending';
    $materialStatus = in_array($_POST['material_payment_status'] ?? '', $allowedPaymentStatuses, true) ? $_POST['material_payment_status'] : 'pending';

    $old = $pdo->prepare("SELECT freight_payment_status, material_payment_status FROM raw_material_inward WHERE id=:id");
    $old->execute([':id' => $id]);
    $oldRow = $old->fetch();

    if ($id > 0 && $oldRow) {
        $pdo->prepare(
            "UPDATE raw_material_inward
             SET freight_payment_status=:freight, material_payment_status=:material
             WHERE id=:id"
        )->execute([':freight' => $freightStatus, ':material' => $materialStatus, ':id' => $id]);

        logAudit($pdo, $currentUser['id'], 'payment_update', 'raw_material_inward', $id, $oldRow, [
            'freight_payment_status' => $freightStatus,
            'material_payment_status' => $materialStatus,
        ]);
        setFlash('success', 'Payment status updated.');
    } else {
        setFlash('error', 'Inward entry not found.');
    }
    redirect('/modules/admin/payments.php' . (!empty($_POST['redirect_qs']) ? '?' . $_POST['redirect_qs'] : ''));
}

$where = [];
$params = [];
if (!empty($_GET['vendor_id'])) { $where[] = 'i.vendor_id = :vendor_id'; $params[':vendor_id'] = (int) $_GET['vendor_id']; }
if (!empty($_GET['status']) && in_array($_GET['status'], $allowedPaymentStatuses, true)) {
    $where[] = '(i.freight_payment_status = :status OR i.material_payment_status = :status2)';
    $params[':status'] = $_GET['status'];
    $params[':status2'] = $_GET['status'];
}

$sql = "SELECT i.*, v.name AS vendor_name
        FROM raw_material_inward i
        JOIN vendors v ON v.id = i.vendor_id";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY i.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$vendors = $pdo->query("SELECT * FROM vendors ORDER BY name")->fetchAll();
$queryString = http_build_query($_GET);

$pageTitle = 'Vendor Payments';
$activeMenu = 'payments';
$hideTopbar = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="card">
  <form method="get" action="" class="form-row filter-form" style="align-items:flex-end;">
    <div>
      <label>Vendor</label>
      <select name="vendor_id">
        <option value="">All</option>
        <?php foreach ($vendors as $v): ?>
          <option value="<?= $v['id'] ?>" <?= (($_GET['vendor_id'] ?? '') == $v['id']) ? 'selected' : '' ?>><?= e($v['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Payment Status</label>
      <select name="status">
        <option value="">All</option>
        <option value="pending" <?= (($_GET['status'] ?? '') === 'pending') ? 'selected' : '' ?>>Has a pending payment</option>
        <option value="partial" <?= (($_GET['status'] ?? '') === 'partial') ? 'selected' : '' ?>>Has a partial payment</option>
        <option value="paid" <?= (($_GET['status'] ?? '') === 'paid') ? 'selected' : '' ?>>Has a paid payment</option>
      </select>
    </div>
    <div><button type="submit" class="btn btn-outline">Filter</button></div>
  </form>
</div>

<div class="card">
  <h3 class="mt-0">Freight &amp; Material Payments (<?= count($rows) ?>)</h3>
  <p class="help-text">Mark whether the freight (vehicle/transport) payment and the material payment have been made to the vendor for each inward entry. Plant Head only sees this status - it's set here.</p>
  <?php if (!$rows): ?>
    <p class="row-card-empty">No inward entries found.</p>
  <?php else: ?>
  <div class="row-list">
    <?php foreach ($rows as $r):
      $bothPaid = $r['freight_payment_status'] === 'paid' && $r['material_payment_status'] === 'paid';
      $bothPending = $r['freight_payment_status'] === 'pending' && $r['material_payment_status'] === 'pending';
      $rowClass = $bothPaid ? 'row-card-paid' : ($bothPending ? 'row-card-pending' : 'row-card-info');
    ?>
      <details class="row-card <?= $rowClass ?>">
        <summary>
          <span class="row-card-heading">
            <strong><?= e($r['lot_no']) ?></strong> <span class="text-soft"><?= e($r['vendor_name']) ?> &middot; <?= formatDate($r['inward_date']) ?></span>
            <span class="pill pill-<?= paymentStatusPillClass($r['freight_payment_status']) ?>">Freight: <?= paymentStatusLabel($r['freight_payment_status']) ?></span>
            <span class="pill pill-<?= paymentStatusPillClass($r['material_payment_status']) ?>">Material: <?= paymentStatusLabel($r['material_payment_status']) ?></span>
          </span>
          <span class="row-chevron"><?= tabIcon('chevron') ?></span>
        </summary>
        <div class="row-card-detail">
        <div class="row-card-fields">
          <div><div class="field-label">Vehicle</div><div class="field-value"><?= e($r['vehicle_no']) ?: '-' ?></div></div>
          <div><div class="field-label">Gross Wt (MT)</div><div class="field-value"><?= number_format($r['gross_weight'], 4) ?></div></div>
          <div>
            <div class="field-label">Freight Payment</div>
            <form method="post" action="" style="display:flex;gap:8px;">
              <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <input type="hidden" name="material_payment_status" value="<?= e($r['material_payment_status']) ?>">
              <input type="hidden" name="redirect_qs" value="<?= e($queryString) ?>">
              <select name="freight_payment_status" class="pill-select pill-select-<?= e($r['freight_payment_status']) ?>">
                <option value="pending" <?= $r['freight_payment_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="partial" <?= $r['freight_payment_status'] === 'partial' ? 'selected' : '' ?>>Partial</option>
                <option value="paid" <?= $r['freight_payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
              </select>
              <button type="submit" class="btn btn-sm btn-outline">Save</button>
            </form>
          </div>
          <div>
            <div class="field-label">Material Payment</div>
            <form method="post" action="" style="display:flex;gap:8px;">
              <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <input type="hidden" name="freight_payment_status" value="<?= e($r['freight_payment_status']) ?>">
              <input type="hidden" name="redirect_qs" value="<?= e($queryString) ?>">
              <select name="material_payment_status" class="pill-select pill-select-<?= e($r['material_payment_status']) ?>">
                <option value="pending" <?= $r['material_payment_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="partial" <?= $r['material_payment_status'] === 'partial' ? 'selected' : '' ?>>Partial</option>
                <option value="paid" <?= $r['material_payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
              </select>
              <button type="submit" class="btn btn-sm btn-outline">Save</button>
            </form>
          </div>
        </div>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
