<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['Admin']);

$rows = $pdo->query(
    "SELECT s.*, v.name AS vendor_name, m.name AS material_name
     FROM raw_material_stock s JOIN vendors v ON v.id=s.vendor_id JOIN raw_materials m ON m.id=s.raw_material_id
     ORDER BY m.name, s.id"
)->fetchAll();

$pageTitle = 'Raw Material Stock Report';
$activeMenu = 'r-stock';
$hideTopbar = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="card">
  <h3 class="mt-0">Lot-wise Remaining Stock</h3>
  <?php if (!$rows): ?>
    <p class="row-card-empty">No data.</p>
  <?php else: ?>
  <div class="row-list">
    <?php foreach ($rows as $r): ?>
      <details class="row-card">
        <summary>
          <span class="row-card-heading"><strong><?= e($r['lot_no']) ?></strong> <span class="text-soft"><?= e($r['material_name']) ?></span> <span class="pill pill-<?= e($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span></span>
          <span class="row-chevron"><?= tabIcon('chevron') ?></span>
        </summary>
        <div class="row-card-detail">
        <div class="row-card-fields">
          <div><div class="field-label">Vendor</div><div class="field-value"><?= e($r['vendor_name']) ?></div></div>
          <div><div class="field-label">Jumbo Remaining</div><div class="field-value"><?= (int) $r['remaining_jumbo'] ?></div></div>
          <div><div class="field-label">MT Remaining</div><div class="field-value"><?= number_format($r['remaining_mt'],4) ?></div></div>
        </div>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
