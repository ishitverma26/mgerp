<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/labour.php';
requireRole(['Admin']);

$date = clean($_GET['date'] ?? '');
if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

$labourList = getLabourAttendanceForDate($pdo, $date);
$fullCount = count(array_filter($labourList, fn($l) => $l['attendance_status'] === 'full_day'));
$absentCount = count(array_filter($labourList, fn($l) => $l['attendance_status'] === 'absent'));
$unmarkedCount = count($labourList) - $fullCount - $absentCount;
$labourCost = array_sum(array_map(fn($l) => labourWageForStatus($l['daily_wage'] !== null ? (float) $l['daily_wage'] : null, $l['attendance_status']), $labourList));

$pageTitle = 'Labour Attendance Status';
$activeMenu = 'labour-status';
$hideTopbar = true;
$pageHasFilter = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($labourList): ?>
<div class="activity-card-row" style="margin-bottom:16px;">
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-green"><?= tabIcon('check-square') ?></span>
      <span class="activity-card-label">Full Day</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= $fullCount ?></div>
  </div>
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-blue"><?= tabIcon('alert') ?></span>
      <span class="activity-card-label">Absent</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= $absentCount ?></div>
  </div>
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-purple"><?= tabIcon('dollar') ?></span>
      <span class="activity-card-label">Labour Cost</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value">Rs <?= number_format($labourCost, 0) ?></div>
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
      <form method="get" action="" class="form-row filter-form" style="align-items:flex-end;max-width:260px;">
        <div>
          <label>Date</label>
          <input type="date" name="date" value="<?= e($date) ?>">
        </div>
        <div><button type="submit" class="btn btn-outline">Filter</button></div>
      </form>
    </div>
  </div>
</div>

<div class="card">
  <div class="flex-between">
    <h3 class="mt-0"><span class="icon-chip icon-chip-blue" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('user') ?></span>Labour Status - <?= e(formatDate($date)) ?></h3>
    <a href="<?= APP_URL ?>/modules/admin/settings.php" class="btn btn-sm btn-tint-blue">Manage Labour</a>
  </div>
  <?php if ($unmarkedCount > 0): ?>
    <p class="help-text"><?= $unmarkedCount ?> labour<?= $unmarkedCount === 1 ? '' : 's' ?> not marked yet for this date.</p>
  <?php endif; ?>
  <?php if (!$labourList): ?>
    <p class="row-card-empty">No labour added yet - add some from Settings.</p>
  <?php else: ?>
  <div class="row-list">
    <?php foreach ($labourList as $l): $cur = $l['attendance_status']; $earned = labourWageForStatus($l['daily_wage'] !== null ? (float) $l['daily_wage'] : null, $cur); ?>
      <div class="row-card <?= $cur === 'full_day' ? 'row-card-good' : ($cur === 'absent' ? 'row-card-pending' : 'row-card-neutral') ?>" style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
        <strong style="font-size:13.5px;"><?= e($l['name']) ?></strong>
        <span style="display:flex;align-items:center;gap:8px;">
          <?php if ($l['daily_wage'] !== null): ?><span class="text-soft" style="font-size:12.5px;">Rs <?= number_format($earned, 0) ?></span><?php endif; ?>
          <span class="pill pill-<?= $cur === 'full_day' ? 'active' : ($cur === 'absent' ? 'garbage' : 'inactive') ?>"><?= e(labourStatusLabel($cur)) ?></span>
        </span>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
