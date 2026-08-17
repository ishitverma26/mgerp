<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['Admin']);

$where = [];
$params = [];
if (!empty($_GET['vendor_id'])) { $where[] = 'i.vendor_id = :vendor_id'; $params[':vendor_id'] = (int) $_GET['vendor_id']; }
if (!empty($_GET['date_from'])) { $where[] = 'i.inward_date >= :date_from'; $params[':date_from'] = $_GET['date_from']; }
if (!empty($_GET['date_to'])) { $where[] = 'i.inward_date <= :date_to'; $params[':date_to'] = $_GET['date_to']; }

$sql = "SELECT i.*, v.name AS vendor_name FROM raw_material_inward i JOIN vendors v ON v.id=i.vendor_id";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY v.name, i.inward_date';
$stmt = $pdo->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll();

$totalMt = array_sum(array_column($rows, 'gross_weight'));
$vendors = $pdo->query("SELECT * FROM vendors ORDER BY name")->fetchAll();

$pageTitle = 'Vendor-wise Raw Material Report';
$activeMenu = 'r-vendor';
$hideTopbar = true;
$pageHasFilter = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="masters-modal-overlay<?= !empty($_GET) ? ' is-open' : '' ?>" id="filterModalOverlay">
  <div class="masters-modal filter-modal-sm">
    <div class="masters-modal-head">
      <span>Filters</span>
      <button type="button" class="masters-modal-close" id="filterModalClose" aria-label="Close">&times;</button>
    </div>
    <div class="filter-modal-body">
      <form method="get" class="form-row filter-form" style="align-items:flex-end;">
        <div><label>Vendor</label><select name="vendor_id"><option value="">All</option>
          <?php foreach ($vendors as $v): ?><option value="<?= $v['id'] ?>" <?= (($_GET['vendor_id'] ?? '') == $v['id']) ? 'selected' : '' ?>><?= e($v['name']) ?></option><?php endforeach; ?>
        </select></div>
        <div><label>From</label><input type="date" name="date_from" value="<?= e($_GET['date_from'] ?? '') ?>"></div>
        <div><label>To</label><input type="date" name="date_to" value="<?= e($_GET['date_to'] ?? '') ?>"></div>
        <div><button type="submit" class="btn btn-outline">Filter</button></div>
      </form>
    </div>
  </div>
</div>
<div class="card">
  <h3 class="mt-0">Vendor-wise Raw Material (Total: <?= number_format($totalMt, 3) ?> MT)</h3>
  <?php if (!$rows): ?>
    <p class="row-card-empty">No data.</p>
  <?php else: ?>
  <div class="row-list">
    <?php foreach ($rows as $r): ?>
      <details class="row-card">
        <summary>
          <span class="row-card-heading"><strong><?= e($r['lot_no']) ?></strong> <span class="text-soft"><?= e($r['vendor_name']) ?> &middot; <?= formatDate($r['inward_date']) ?></span></span>
          <span class="row-chevron"><?= tabIcon('chevron') ?></span>
        </summary>
        <div class="row-card-detail">
        <div class="row-card-fields">
          <div><div class="field-label">Vehicle</div><div class="field-value"><?= e($r['vehicle_no']) ?: '-' ?></div></div>
          <div><div class="field-label">Gross Wt</div><div class="field-value"><?= number_format($r['gross_weight'],4) ?></div></div>
          <div><div class="field-label">Jumbo</div><div class="field-value"><?= (int) $r['jumbo_qty'] ?></div></div>
          <div><div class="field-label">Per Jumbo</div><div class="field-value"><?= number_format($r['per_jumbo_weight'],6) ?></div></div>
          <div><div class="field-label">Total MT</div><div class="field-value"><?= number_format($r['gross_weight'],4) ?></div></div>
        </div>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
