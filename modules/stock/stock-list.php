<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['Admin', 'Plant Head']);

$where = [];
$params = [];
if (!empty($_GET['material_id'])) { $where[] = 's.raw_material_id = :material_id'; $params[':material_id'] = (int) $_GET['material_id']; }
if (!empty($_GET['vendor_id'])) { $where[] = 's.vendor_id = :vendor_id'; $params[':vendor_id'] = (int) $_GET['vendor_id']; }
if (!empty($_GET['status'])) { $where[] = 's.status = :status'; $params[':status'] = $_GET['status']; }

$sql = "SELECT s.*, v.name AS vendor_name, m.name AS material_name
        FROM raw_material_stock s
        JOIN vendors v ON v.id = s.vendor_id
        JOIN raw_materials m ON m.id = s.raw_material_id";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY s.id ASC'; // oldest lot first = FIFO order

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$activeRows = array_filter($rows, fn($r) => $r['status'] === 'active');
$activeLotsCount = count($activeRows);
$activeMt = array_sum(array_column($activeRows, 'remaining_mt'));
$activeJumbo = array_sum(array_column($activeRows, 'remaining_jumbo'));

$vendors = $pdo->query("SELECT * FROM vendors ORDER BY name")->fetchAll();
$materials = $pdo->query("SELECT * FROM raw_materials ORDER BY name")->fetchAll();

$pageTitle = 'Raw Material Stock';
$activeMenu = 'stock-list';
$hideTopbar = true;
$pageHasFilter = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="activity-card-row" style="margin-bottom:16px;">
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-amber"><?= tabIcon('package') ?></span>
      <span class="activity-card-label">Active Lots</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= $activeLotsCount ?></div>
  </div>
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-blue"><?= tabIcon('archive') ?></span>
      <span class="activity-card-label">Active MT</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= number_format($activeMt, 1) ?></div>
  </div>
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-green"><?= tabIcon('layers') ?></span>
      <span class="activity-card-label">Active Jumbo</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= number_format($activeJumbo) ?></div>
  </div>
</div>

<div class="masters-modal-overlay<?= !empty($_GET) ? ' is-open' : '' ?>" id="filterModalOverlay">
  <div class="masters-modal filter-modal-sm">
    <div class="masters-modal-head">
      <span>Filters</span>
      <button type="button" class="masters-modal-close" id="filterModalClose" aria-label="Close">&times;</button>
    </div>
    <div class="filter-modal-body">
      <form method="get" action="" class="form-row filter-form" style="align-items:flex-end;">
        <div>
          <label>Raw Material</label>
          <select name="material_id">
            <option value="">All</option>
            <?php foreach ($materials as $m): ?>
              <option value="<?= $m['id'] ?>" <?= (($_GET['material_id'] ?? '') == $m['id']) ? 'selected' : '' ?>><?= e($m['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
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
          <label>Status</label>
          <select name="status">
            <option value="">All</option>
            <option value="active" <?= (($_GET['status'] ?? '') === 'active') ? 'selected' : '' ?>>Active</option>
            <option value="exhausted" <?= (($_GET['status'] ?? '') === 'exhausted') ? 'selected' : '' ?>>Exhausted</option>
          </select>
        </div>
        <div><button type="submit" class="btn btn-outline">Filter</button></div>
      </form>
    </div>
  </div>
</div>

<div class="card">
  <h3 class="mt-0"><span class="icon-chip icon-chip-blue" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('archive') ?></span>Lot-wise Stock (oldest first = FIFO order)</h3>
  <?php if (!$rows): ?>
    <p class="row-card-empty">No stock records found.</p>
  <?php else: ?>
  <div class="row-list">
    <?php foreach ($rows as $r): ?>
      <details class="row-card <?= $r['status'] === 'active' ? 'row-card-good' : 'row-card-neutral' ?>">
        <summary>
          <span class="row-card-heading"><strong><?= e($r['lot_no']) ?></strong> <span class="text-soft"><?= e($r['material_name']) ?> &middot; <?= e($r['vendor_name']) ?></span> <span class="pill pill-<?= e($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span></span>
          <span class="row-chevron"><?= tabIcon('chevron') ?></span>
        </summary>
        <div class="row-card-detail">
        <div class="row-card-fields">
          <div><div class="field-label">Total Jumbo</div><div class="field-value"><?= (int) $r['total_jumbo'] ?></div></div>
          <div><div class="field-label">Remaining Jumbo</div><div class="field-value"><?= (int) $r['remaining_jumbo'] ?></div></div>
          <div><div class="field-label">Per Jumbo (MT)</div><div class="field-value"><?= number_format($r['per_jumbo_weight'], 6) ?></div></div>
          <div><div class="field-label">Total MT</div><div class="field-value"><?= number_format($r['total_mt'], 4) ?></div></div>
          <div><div class="field-label">Remaining MT</div><div class="field-value"><?= number_format($r['remaining_mt'], 4) ?></div></div>
        </div>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
