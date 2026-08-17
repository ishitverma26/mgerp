<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/labour.php';
requireRole(['Admin', 'Plant Head']);

$isAdmin = $currentUser['role_name'] === 'Admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_bonus_scheme' && $isAdmin) {
    $threshold = clean($_POST['threshold_mt'] ?? '');
    $base = clean($_POST['base_amount'] ?? '');
    $perExtra = clean($_POST['per_extra_mt'] ?? '');

    if (!is_numeric($threshold) || $threshold <= 0 || !is_numeric($base) || $base < 0 || !is_numeric($perExtra) || $perExtra < 0) {
        setFlash('error', 'Enter a valid threshold and amounts.');
    } else {
        $old = [
            'threshold_mt' => getSetting($pdo, 'labour_bonus_threshold_mt', null),
            'base_amount' => getSetting($pdo, 'labour_bonus_base_amount', null),
            'per_extra_mt' => getSetting($pdo, 'labour_bonus_per_extra_mt', null),
        ];
        setSetting($pdo, 'labour_bonus_threshold_mt', $threshold);
        setSetting($pdo, 'labour_bonus_base_amount', $base);
        setSetting($pdo, 'labour_bonus_per_extra_mt', $perExtra);
        logAudit($pdo, $currentUser['id'], 'update', 'app_settings', 0, $old, [
            'threshold_mt' => $threshold, 'base_amount' => $base, 'per_extra_mt' => $perExtra,
        ]);
        setFlash('success', 'Production bonus scheme updated.');
    }
    redirect('/modules/utilities/labour-cost.php');
}

// Admin sets these; defaults match the plant's stated deal (30MT -> Rs100/
// head, then Rs10/head per extra MT) so the feature works correctly even
// before Admin has explicitly saved anything.
$thresholdMt = (float) getSetting($pdo, 'labour_bonus_threshold_mt', '30');
$baseAmount = (float) getSetting($pdo, 'labour_bonus_base_amount', '100');
$perExtraMt = (float) getSetting($pdo, 'labour_bonus_per_extra_mt', '10');

// Read-only cost report otherwise - attendance itself is still marked on
// the existing Labour Attendance pages (modules/labour/attendance.php,
// modules/admin/labour-status.php); this rolls it up by date using each
// labourer's daily_wage plus the production bonus, computed from the same
// daily packed-MT figure the dashboards' Daily Target progress bar uses
// (see getPackedMt() in includes/functions.php) - not a separate
// production-tracking mechanism.
$allAttendance = $pdo->query(
    "SELECT a.attendance_date, a.status, l.name, l.daily_wage
     FROM labour_attendance a
     JOIN labour l ON l.id = a.labour_id
     ORDER BY a.attendance_date DESC, l.name"
)->fetchAll();

$byDate = [];
foreach ($allAttendance as $row) {
    $d = $row['attendance_date'];
    if (!isset($byDate[$d])) {
        $dailyMt = getPackedMt($pdo, $d, $d);
        $byDate[$d] = [
            'entries' => [], 'wage_total' => 0.0, 'bonus_total' => 0.0, 'total_cost' => 0.0,
            'present_count' => 0, 'mt' => $dailyMt,
            'bonus_per_head' => labourBonusPerHead($dailyMt, $thresholdMt, $baseAmount, $perExtraMt),
        ];
    }
    $wage = $row['daily_wage'] !== null ? (float) $row['daily_wage'] : null;
    $wageEarned = labourWageForStatus($wage, $row['status']);
    $bonusEarned = $row['status'] === 'full_day' ? $byDate[$d]['bonus_per_head'] : 0.0;
    $byDate[$d]['entries'][] = [
        'name' => $row['name'], 'status' => $row['status'], 'wage' => $wageEarned, 'bonus' => $bonusEarned,
    ];
    $byDate[$d]['wage_total'] += $wageEarned;
    $byDate[$d]['bonus_total'] += $bonusEarned;
    $byDate[$d]['total_cost'] += $wageEarned + $bonusEarned;
    if ($row['status'] === 'full_day') $byDate[$d]['present_count']++;
}

$today = date('Y-m-d');
$thisMonth = date('Y-m');
$todayCost = $byDate[$today]['total_cost'] ?? 0.0;
$monthCost = 0.0;
foreach ($byDate as $d => $info) {
    if (substr($d, 0, 7) === $thisMonth) $monthCost += $info['total_cost'];
}

$pageTitle = 'Labour Cost';
$activeMenu = 'labour-cost';
$hideTopbar = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="activity-card-row" style="margin-bottom:16px;">
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-purple"><?= tabIcon('dollar') ?></span>
      <span class="activity-card-label">Today's Cost</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value">Rs <?= number_format($todayCost, 0) ?></div>
  </div>
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-green"><?= tabIcon('chart') ?></span>
      <span class="activity-card-label">This Month</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value">Rs <?= number_format($monthCost, 0) ?></div>
  </div>
</div>

<?php if ($isAdmin): ?>
<details class="card">
  <summary class="flex-between">
    <h3 class="mt-0" style="margin:0;"><span class="icon-chip icon-chip-amber" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('chart') ?></span>Production Bonus Scheme</h3>
    <span class="batch-chevron"><?= tabIcon('chevron') ?></span>
  </summary>
  <div class="card-detail">
  <p class="help-text" style="margin-top:0;">Admin-only. Once the day's total production (same figure as the Daily Target on the dashboard) reaches the threshold, every labourer marked Full Day that day gets the base bonus, plus a rate for each MT beyond the threshold.</p>
  <form method="post" action="" class="filter-form">
    <input type="hidden" name="action" value="save_bonus_scheme">
    <div><label>Threshold (MT/day) *</label><input type="number" step="0.01" min="0.01" name="threshold_mt" required value="<?= e($thresholdMt) ?>"></div>
    <div><label>Base Bonus at Threshold (Rs/head) *</label><input type="number" step="0.01" min="0" name="base_amount" required value="<?= e($baseAmount) ?>"></div>
    <div><label>Rate per Extra MT (Rs/head) *</label><input type="number" step="0.01" min="0" name="per_extra_mt" required value="<?= e($perExtraMt) ?>"></div>
    <button type="submit" class="btn btn-accent" style="margin-top:14px;">Save Scheme</button>
  </form>
  </div>
</details>
<?php endif; ?>

<details class="card">
  <summary class="flex-between">
    <h3 class="mt-0" style="margin:0;"><span class="icon-chip icon-chip-purple" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('dollar') ?></span>Labour Cost by Day</h3>
    <span class="batch-chevron"><?= tabIcon('chevron') ?></span>
  </summary>
  <div class="card-detail">
  <?php if (!$byDate): ?>
    <p class="row-card-empty">No attendance marked yet.</p>
  <?php else: ?>
  <div class="row-list">
    <?php foreach ($byDate as $d => $info): ?>
      <details class="row-card row-card-info">
        <summary>
          <span class="row-card-heading"><strong><?= formatDate($d) ?></strong> <span class="text-soft"><?= $info['present_count'] ?> present &middot; <?= number_format($info['mt'], 1) ?> MT</span> <span class="pill pill-active">Rs <?= number_format($info['total_cost'], 0) ?></span></span>
          <span class="row-chevron"><?= tabIcon('chevron') ?></span>
        </summary>
        <div class="row-card-detail">
        <?php if ($info['bonus_per_head'] > 0): ?>
          <p class="help-text" style="margin-top:0;">Bonus unlocked - Rs <?= number_format($info['bonus_per_head'], 0) ?>/head (<?= number_format($info['mt'], 1) ?> MT produced).</p>
        <?php endif; ?>
        <div class="row-card-fields">
          <?php foreach ($info['entries'] as $e): ?>
            <div><div class="field-label"><?= e($e['name']) ?></div><div class="field-value"><?= labourStatusLabel($e['status']) ?> &middot; Rs <?= number_format($e['wage'], 0) ?><?= $e['bonus'] > 0 ? ' + Rs ' . number_format($e['bonus'], 0) . ' bonus' : '' ?></div></div>
          <?php endforeach; ?>
        </div>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  </div>
</details>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
