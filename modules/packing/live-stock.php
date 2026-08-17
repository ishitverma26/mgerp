<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['Admin', 'Plant Head']);

$stock = buildLiveStock($pdo);
$totalQty = array_sum(array_column($stock, 'qty'));
$totalSkus = count($stock);

$pageTitle = 'Live Stock';
$activeMenu = 'live-stock';
$hideTopbar = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($stock): ?>
<div class="activity-card-row" style="margin-bottom:16px;">
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-blue"><?= tabIcon('layers') ?></span>
      <span class="activity-card-label">SKUs</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= $totalSkus ?></div>
  </div>
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-green"><?= tabIcon('package') ?></span>
      <span class="activity-card-label">Bags Ready</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= number_format($totalQty) ?></div>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <h3 class="mt-0"><span class="icon-chip icon-chip-purple" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('layers') ?></span>Finished Bags in Stock (<?= number_format($totalQty) ?> pcs)</h3>
  <p class="help-text">Bags already packed beyond what a completed batch's target needed - not spoken for by any batch, sitting ready to use.</p>
  <?php if (!$stock): ?>
    <p class="row-card-empty">No surplus stock right now - every completed batch has exactly matched (or come short of) its target.</p>
  <?php else: ?>
  <div class="row-list">
    <?php foreach ($stock as $s): ?>
      <div class="row-card row-card-good">
        <span class="row-card-heading"><strong><?= e($s['product_name']) ?> <?= e($s['size_kg']) ?>KG</strong> <span class="text-soft">Token <?= e($s['token_value']) ?></span> <span class="pill pill-active"><?= number_format($s['qty']) ?> pcs</span></span>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
