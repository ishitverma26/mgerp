<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['Plant Head']);

// No form here anymore - damage is logged right where it's actually found
// (line damage while packing, or the Damaged field on a vehicle's Mark
// Loading step - see batch-list.php), not through a separate "pick a
// batch after the fact" form. This page is purely the record of what's
// been logged, merged with Empty Jumbo Bags damage too (buildDamageRecord()
// in includes/functions.php, shared with Admin's Damage Report).
$rows = buildDamageRecord($pdo);

$sourceLabel = ['packing' => 'Packing (Finished Bags)', 'jumbo' => 'Empty Jumbo Bags'];
$sourceIconClass = ['packing' => 'icon-chip-red', 'jumbo' => 'icon-chip-amber'];

$pageTitle = 'Damage Record';
$activeMenu = 'damage-entry';
$hideTopbar = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="card">
  <h3 class="mt-0">Damage Record</h3>
  <p class="help-text">Every damaged-bag entry, from wherever it was actually found - packing damage and Empty Jumbo Bags damage together. Recovered cement always goes straight back into that batch's production.</p>
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
