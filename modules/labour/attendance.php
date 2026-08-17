<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/labour.php';
requireRole(['Plant Head']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_attendance') {
    $date = clean($_POST['attendance_date'] ?? '');
    if ($date === '') $date = date('Y-m-d');
    $statuses = $_POST['status'] ?? [];

    $stmt = $pdo->prepare(
        "INSERT INTO labour_attendance (labour_id, attendance_date, status, marked_by)
         VALUES (:lid, :d, :s, :uid)
         ON DUPLICATE KEY UPDATE status = :s2, marked_by = :uid2, updated_at = NOW()"
    );
    $count = 0;
    foreach ($statuses as $labourId => $status) {
        if (!in_array($status, ['full_day', 'absent'], true)) continue;
        $stmt->execute([
            ':lid' => (int) $labourId, ':d' => $date, ':s' => $status, ':uid' => $currentUser['id'],
            ':s2' => $status, ':uid2' => $currentUser['id'],
        ]);
        $count++;
    }
    logAudit($pdo, $currentUser['id'], 'mark_attendance', 'labour_attendance', 0, null, ['date' => $date, 'count' => $count]);
    setFlash('success', $count > 0 ? "Attendance saved for $count labour(s)." : 'No attendance selected.');
    redirect('/modules/labour/attendance.php?date=' . $date);
}

$date = clean($_GET['date'] ?? '');
if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

$labourList = getLabourAttendanceForDate($pdo, $date);
$fullCount = count(array_filter($labourList, fn($l) => $l['attendance_status'] === 'full_day'));
$absentCount = count(array_filter($labourList, fn($l) => $l['attendance_status'] === 'absent'));
$labourCost = array_sum(array_map(fn($l) => labourWageForStatus($l['daily_wage'] !== null ? (float) $l['daily_wage'] : null, $l['attendance_status']), $labourList));

$pageTitle = 'Labour Attendance';
$activeMenu = 'labour-attendance';
$hideTopbar = true;
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

<div class="card">
  <h3 class="mt-0"><span class="icon-chip icon-chip-green" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('user') ?></span>Mark Attendance</h3>
  <form method="get" action="" class="filter-form" style="margin-bottom:4px;">
    <div style="max-width:220px;">
      <label>Date</label>
      <input type="date" name="date" value="<?= e($date) ?>" onchange="this.form.submit()">
    </div>
  </form>

  <?php if (!$labourList): ?>
    <p class="row-card-empty">No labour added yet - ask Admin to add some in Settings.</p>
  <?php else: ?>
  <form method="post" action="" id="attendanceForm">
    <input type="hidden" name="action" value="mark_attendance">
    <input type="hidden" name="attendance_date" value="<?= e($date) ?>">
    <div class="row-list" style="margin-top:14px;">
      <?php foreach ($labourList as $l): $cur = $l['attendance_status']; $earned = labourWageForStatus($l['daily_wage'] !== null ? (float) $l['daily_wage'] : null, $cur); ?>
        <div class="row-card <?= $cur === 'full_day' ? 'row-card-good' : ($cur === 'absent' ? 'row-card-pending' : 'row-card-neutral') ?>">
          <div class="flex-between" style="margin-bottom:8px;">
            <div style="font-weight:700;font-size:13.5px;"><?= e($l['name']) ?></div>
            <?php if ($l['daily_wage'] !== null): ?><span class="text-soft" style="font-size:12.5px;">Rs <?= number_format($earned, 0) ?></span><?php endif; ?>
          </div>
          <div class="segmented segmented-full attendance-toggle" data-labour="<?= (int) $l['id'] ?>">
            <button type="button" class="segmented-btn<?= $cur === 'full_day' ? ' active' : '' ?>" data-status="full_day">Full Day</button>
            <button type="button" class="segmented-btn<?= $cur === 'absent' ? ' active' : '' ?>" data-status="absent">Absent</button>
          </div>
          <input type="hidden" name="status[<?= (int) $l['id'] ?>]" value="<?= e($cur ?? '') ?>">
        </div>
      <?php endforeach; ?>
    </div>
    <button type="submit" class="btn btn-accent" style="margin-top:16px;width:100%;">Save Attendance</button>
  </form>
  <?php endif; ?>
</div>
<script>
(function () {
  document.querySelectorAll('.attendance-toggle').forEach(function (group) {
    var hiddenInput = group.parentElement.querySelector('input[type=hidden][name^="status["]');
    group.querySelectorAll('.segmented-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        group.querySelectorAll('.segmented-btn').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        if (hiddenInput) hiddenInput.value = btn.getAttribute('data-status');
      });
    });
  });
})();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
