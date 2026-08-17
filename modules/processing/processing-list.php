<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['Plant Head']);

$requests = $pdo->query(
    "SELECT p.*, m.name AS material_name, u.name AS user_name
     FROM processing_requests p
     JOIN raw_materials m ON m.id = p.raw_material_id
     JOIN users u ON u.id = p.created_by
     ORDER BY p.id DESC"
)->fetchAll();

$lotStmt = $pdo->prepare(
    "SELECT c.jumbo_consumed, c.mt_consumed, s.lot_no
     FROM processing_lot_consumption c
     JOIN raw_material_stock s ON s.id = c.stock_id
     WHERE c.processing_id = :pid"
);

$totalMtConsumed = array_sum(array_column($requests, 'total_mt_consumed'));
$totalJumboConsumed = array_sum(array_column($requests, 'requirement_jumbo'));

$pageTitle = 'Processing History';
$activeMenu = 'processing-list';
$hideTopbar = true;
$pageHeaderExtraIcon = ['icon' => 'plus', 'url' => '/modules/processing/processing-entry.php', 'title' => 'New Processing'];
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="activity-card-row" style="margin-bottom:16px;">
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-amber"><?= tabIcon('refresh') ?></span>
      <span class="activity-card-label">Requests</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= count($requests) ?></div>
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

<div class="card">
  <h3 class="mt-0">Processing Requests (<?= count($requests) ?>)</h3>
  <?php if (!$requests): ?>
    <p class="row-card-empty">No processing history yet.</p>
  <?php else: ?>
  <div class="row-list">
    <?php foreach ($requests as $r): ?>
      <?php $lotStmt->execute([':pid' => $r['id']]); $lots = $lotStmt->fetchAll(); ?>
      <details class="row-card row-card-info">
        <summary>
          <span class="row-card-heading"><strong>#<?= (int) $r['id'] ?> &middot; <?= e($r['material_name']) ?></strong> <span class="text-soft"><?= formatDate($r['processing_date']) ?> &middot; <?= (int) $r['requirement_jumbo'] ?> Jumbo</span> <span class="pill pill-active"><?= number_format($r['total_mt_consumed'], 3) ?> MT</span></span>
          <span class="row-chevron"><?= tabIcon('chevron') ?></span>
        </summary>
        <div class="row-card-detail">
        <div class="row-card-fields">
          <div><div class="field-label">MT Consumed</div><div class="field-value"><?= number_format($r['total_mt_consumed'], 6) ?></div></div>
          <div>
            <div class="field-label">Lot-wise Breakdown</div>
            <div class="field-value"><?php foreach ($lots as $l): ?><span class="pill pill-active"><?= e($l['lot_no']) ?>: <?= (int) $l['jumbo_consumed'] ?> Jumbo</span> <?php endforeach; ?></div>
          </div>
          <div><div class="field-label">By</div><div class="field-value"><?= e($r['user_name']) ?></div></div>
        </div>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
