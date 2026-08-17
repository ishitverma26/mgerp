<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['Admin', 'Plant Head']);

$where = [];
$params = [];

if (!empty($_GET['vendor_id'])) { $where[] = 'i.vendor_id = :vendor_id'; $params[':vendor_id'] = (int) $_GET['vendor_id']; }
if (!empty($_GET['material_id'])) { $where[] = 'i.raw_material_id = :material_id'; $params[':material_id'] = (int) $_GET['material_id']; }
if (!empty($_GET['date_from'])) { $where[] = 'i.inward_date >= :date_from'; $params[':date_from'] = $_GET['date_from']; }
if (!empty($_GET['date_to'])) { $where[] = 'i.inward_date <= :date_to'; $params[':date_to'] = $_GET['date_to']; }

$sql = "SELECT i.*, v.name AS vendor_name, m.name AS material_name
        FROM raw_material_inward i
        JOIN vendors v ON v.id = i.vendor_id
        JOIN raw_materials m ON m.id = i.raw_material_id";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY i.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$vendors = $pdo->query("SELECT * FROM vendors ORDER BY name")->fetchAll();
$materials = $pdo->query("SELECT * FROM raw_materials ORDER BY name")->fetchAll();

$liveStockMt = $pdo->query("SELECT COALESCE(SUM(remaining_mt),0) FROM raw_material_stock WHERE status='active'")->fetchColumn();
$liveStockJumbo = $pdo->query("SELECT COALESCE(SUM(remaining_jumbo),0) FROM raw_material_stock WHERE status='active'")->fetchColumn();

$pageTitle = 'Raw Material Inward History';
$activeMenu = 'inward-list';
$hideTopbar = true;
$pageHasFilter = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="activity-card-row" style="margin-bottom:16px;">
  <a href="<?= APP_URL ?>/modules/inward/inward-entry.php" class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-amber"><?= tabIcon('plus') ?></span>
      <span class="activity-card-label">Add New Inward</span>
      <span class="activity-card-view"><?= tabIcon('arrow-right') ?></span>
    </div>
  </a>
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-blue"><?= tabIcon('archive') ?></span>
      <span class="activity-card-label">Live Stock (MT)</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= number_format($liveStockMt, 1) ?></div>
  </div>
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-green"><?= tabIcon('package') ?></span>
      <span class="activity-card-label">Live Jumbo</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= number_format($liveStockJumbo) ?></div>
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
          <label>Vendor</label>
          <select name="vendor_id">
            <option value="">All</option>
            <?php foreach ($vendors as $v): ?>
              <option value="<?= $v['id'] ?>" <?= (($_GET['vendor_id'] ?? '') == $v['id']) ? 'selected' : '' ?>><?= e($v['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Raw Material</label>
          <select name="material_id">
            <option value="">All</option>
            <?php foreach ($materials as $m): ?>
              <option value="<?= $m['id'] ?>" <?= (($_GET['material_id'] ?? '') == $m['id']) ? 'selected' : '' ?>><?= e($m['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div><label>From</label><input type="date" name="date_from" value="<?= e($_GET['date_from'] ?? '') ?>"></div>
        <div><label>To</label><input type="date" name="date_to" value="<?= e($_GET['date_to'] ?? '') ?>"></div>
        <div><button type="submit" class="btn btn-outline">Filter</button></div>
      </form>
    </div>
  </div>
</div>

<div class="card">
  <h3 class="mt-0">Inward Entries (<?= count($rows) ?>)</h3>
  <?php if (!$rows): ?>
    <p class="row-card-empty">No inward entries found.</p>
  <?php else: ?>
  <div class="row-list">
    <?php foreach ($rows as $r): ?>
      <?php $bothPaid = $r['freight_payment_status'] === 'paid' && $r['material_payment_status'] === 'paid'; ?>
      <details class="row-card <?= $bothPaid ? 'row-card-paid' : 'row-card-pending' ?>">
        <summary>
          <span class="row-card-heading"><strong><?= e($r['lot_no']) ?></strong> <span class="text-soft"><?= e($r['vendor_name']) ?> &middot; <?= e($r['material_name']) ?> &middot; <?= formatDate($r['inward_date']) ?></span> <span class="pill pill-<?= $bothPaid ? 'active' : 'pending' ?>"><?= $bothPaid ? 'Payment Paid' : 'Payment Pending' ?></span></span>
          <span class="row-chevron"><?= tabIcon('chevron') ?></span>
        </summary>
        <div class="row-card-detail">
        <div class="row-card-fields">
          <div><div class="field-label">Vehicle</div><div class="field-value"><?= e($r['vehicle_no']) ?: '-' ?></div></div>
          <div><div class="field-label">Gross Wt (MT)</div><div class="field-value"><?= number_format($r['gross_weight'], 4) ?></div></div>
          <div><div class="field-label">Jumbo</div><div class="field-value"><?= (int) $r['jumbo_qty'] ?></div></div>
          <div><div class="field-label">Per Jumbo (MT)</div><div class="field-value"><?= number_format($r['per_jumbo_weight'], 6) ?></div></div>
          <div><div class="field-label">Slip</div><div class="field-value"><?php if ($r['slip_photo']): ?><a href="<?= APP_URL ?>/uploads/<?= e($r['slip_photo']) ?>" target="_blank">View</a><?php else: ?>-<?php endif; ?></div></div>
          <div><div class="field-label">Freight Payment</div><div class="field-value"><span class="pill pill-<?= paymentStatusPillClass($r['freight_payment_status']) ?>"><?= paymentStatusLabel($r['freight_payment_status']) ?></span></div></div>
          <div><div class="field-label">Material Payment</div><div class="field-value"><span class="pill pill-<?= paymentStatusPillClass($r['material_payment_status']) ?>"><?= paymentStatusLabel($r['material_payment_status']) ?></span></div></div>
        </div>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
