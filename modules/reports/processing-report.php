<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['Admin']);

$where = [];
$params = [];
if (!empty($_GET['date_from'])) { $where[] = 'p.processing_date >= :date_from'; $params[':date_from'] = $_GET['date_from']; }
if (!empty($_GET['date_to'])) { $where[] = 'p.processing_date <= :date_to'; $params[':date_to'] = $_GET['date_to']; }

$sql = "SELECT p.*, m.name AS material_name FROM processing_requests p JOIN raw_materials m ON m.id=p.raw_material_id";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY p.id DESC';
$stmt = $pdo->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll();

$lotStmt = $pdo->prepare("SELECT c.jumbo_consumed, s.lot_no FROM processing_lot_consumption c JOIN raw_material_stock s ON s.id=c.stock_id WHERE c.processing_id=:pid");

$totalMtConsumed = array_sum(array_column($rows, 'total_mt_consumed'));
$totalJumboConsumed = array_sum(array_column($rows, 'requirement_jumbo'));

$pageTitle = 'Processing Report';
$activeMenu = 'r-processing';
$hideTopbar = true;
$pageHasFilter = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($rows): ?>
<div class="activity-card-row" style="margin-bottom:16px;">
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-amber"><?= tabIcon('refresh') ?></span>
      <span class="activity-card-label">Requests</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= count($rows) ?></div>
  </div>
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-blue"><?= tabIcon('archive') ?></span>
      <span class="activity-card-label">MT Consumed</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= number_format($totalMtConsumed, 1) ?></div>
  </div>
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-green"><?= tabIcon('package') ?></span>
      <span class="activity-card-label">Jumbo Consumed</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= number_format($totalJumboConsumed) ?></div>
  </div>
</div>
<?php endif; ?>

<div class="masters-modal-overlay<?= !empty($_GET) ? ' is-open' : '' ?>" id="filterModalOverlay">
  <div class="masters-modal filter-modal-sm">
    <div class="masters-modal-head">
      <span>Filters</span>
      <button type="button" class="masters-modal-close" id="filterModalClose" aria-label="Close">&times;</button>
    </div>
    <div class="filter-modal-body">
      <form method="get" class="form-row filter-form" style="align-items:flex-end;">
        <div><label>From</label><input type="date" name="date_from" value="<?= e($_GET['date_from'] ?? '') ?>"></div>
        <div><label>To</label><input type="date" name="date_to" value="<?= e($_GET['date_to'] ?? '') ?>"></div>
        <div><button type="submit" class="btn btn-outline">Filter</button></div>
      </form>
    </div>
  </div>
</div>
<div class="card">
  <h3 class="mt-0"><span class="icon-chip icon-chip-blue" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('refresh') ?></span>Processing (FIFO) Report</h3>
  <?php if (!$rows): ?>
    <p class="row-card-empty">No data.</p>
  <?php else: ?>
  <div class="row-list">
    <?php foreach ($rows as $r): $lotStmt->execute([':pid' => $r['id']]); $lots = $lotStmt->fetchAll(); ?>
      <details class="row-card row-card-info">
        <summary>
          <span class="row-card-heading"><strong><?= e($r['material_name']) ?></strong> <span class="text-soft"><?= formatDate($r['processing_date']) ?> &middot; <?= (int) $r['requirement_jumbo'] ?> Jumbo</span></span>
          <span class="row-chevron"><?= tabIcon('chevron') ?></span>
        </summary>
        <div class="row-card-detail">
        <div class="row-card-fields">
          <div><div class="field-label">MT</div><div class="field-value"><?= number_format($r['total_mt_consumed'],6) ?></div></div>
          <div>
            <div class="field-label">Lot-wise (FIFO)</div>
            <div class="field-value"><?php foreach ($lots as $l): ?><span class="pill pill-active"><?= e($l['lot_no']) ?>:<?= (int)$l['jumbo_consumed'] ?></span> <?php endforeach; ?></div>
          </div>
        </div>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
