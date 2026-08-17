<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['Admin']);

$rows = buildDamageRecord($pdo);

$sourceLabel = ['packing' => 'Packing (Finished Bags)', 'jumbo' => 'Empty Jumbo Bags'];
$sourceIconClass = ['packing' => 'icon-chip-red', 'jumbo' => 'icon-chip-amber'];

$pageTitle = 'Damage Report';
$activeMenu = 'r-damage';
$hideTopbar = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="card">
  <h3 class="mt-0">Damage Record - All Sources</h3>
  <p class="help-text">Every damaged-bag entry in the app - packing damage (with Reprocessing/Garbage routing) and Empty Jumbo Bags damage together. Logged from wherever it was actually found (Damage Entry, or a vehicle's Mark Loading step) - this is a read-only record.</p>
  <?php if (!$rows): ?>
    <p class="row-card-empty">No damage recorded yet.</p>
  <?php else: ?>
  <div class="row-list">
    <?php foreach ($rows as $r): ?>
      <details class="row-card">
        <summary>
          <span class="row-card-heading">
            <span class="icon-chip <?= $sourceIconClass[$r['source']] ?>" style="margin-bottom:0;width:24px;height:24px;border-radius:7px;"><?= tabIcon('alert') ?></span>
            <strong><?= e($r['heading']) ?></strong>
            <span class="text-soft"><?= e($r['sub']) ?></span>
            <span class="pill pill-<?= $r['pill_class'] ?>"><?= e($r['pill_label']) ?></span>
          </span>
          <span class="row-chevron"><?= tabIcon('chevron') ?></span>
        </summary>
        <div class="row-card-detail">
        <div class="row-card-fields">
          <div><div class="field-label">Source</div><div class="field-value"><?= e($sourceLabel[$r['source']]) ?></div></div>
          <div><div class="field-label">Date</div><div class="field-value"><?= formatDate($r['date']) ?></div></div>
          <?php foreach ($r['fields'] as $label => $value): ?>
            <div><div class="field-label"><?= e($label) ?></div><div class="field-value"><?= e($value) ?></div></div>
          <?php endforeach; ?>
        </div>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
