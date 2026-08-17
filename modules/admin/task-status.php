<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/tasks.php';
requireRole(['Admin']);

$tasks = $pdo->query("SELECT * FROM tasks WHERE status='active' ORDER BY frequency, title")->fetchAll();
$completionStmt = $pdo->prepare(
    "SELECT tc.completed_at, u.name AS completed_by_name
     FROM task_completions tc JOIN users u ON u.id = tc.completed_by
     WHERE tc.task_id=:id AND tc.period_key=:pk"
);
$nowTime = date('H:i:s');
foreach ($tasks as &$t) {
    $pk = taskPeriodKey($t);
    $completionStmt->execute([':id' => $t['id'], ':pk' => $pk]);
    $c = $completionStmt->fetch();
    $t['done'] = (bool) $c;
    $t['completed_by_name'] = $c['completed_by_name'] ?? null;
    $t['completed_at'] = $c['completed_at'] ?? null;
    $t['is_due'] = $t['due_time'] === null || $nowTime >= $t['due_time'];
    $t['pending'] = !$t['done'] && $t['is_due'];
}
unset($t);

$doneCount = count(array_filter($tasks, fn($t) => $t['done']));
$pendingCount = count(array_filter($tasks, fn($t) => $t['pending']));

$pageTitle = 'Tasks Status';
$activeMenu = 'task-status';
$hideTopbar = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($tasks): ?>
<div class="activity-card-row" style="margin-bottom:16px;">
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-blue"><?= tabIcon('check-square') ?></span>
      <span class="activity-card-label">Total Tasks</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= count($tasks) ?></div>
  </div>
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-green"><?= tabIcon('check-square') ?></span>
      <span class="activity-card-label">Done</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= $doneCount ?></div>
  </div>
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-amber"><?= tabIcon('alert') ?></span>
      <span class="activity-card-label">Pending</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= $pendingCount ?></div>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="flex-between">
    <h3 class="mt-0"><span class="icon-chip icon-chip-blue" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('check-square') ?></span>Task Status</h3>
    <a href="<?= APP_URL ?>/modules/admin/settings.php" class="btn btn-sm btn-tint-blue">Manage Tasks</a>
  </div>
  <p class="help-text">Each task is checked against its own recurring period (daily/weekly/monthly/custom) and, if it has a due time, only counts as pending once that time has passed.</p>
  <?php if (!$tasks): ?>
    <p class="row-card-empty">No tasks set yet - add one from Settings.</p>
  <?php else: ?>
  <div class="row-list">
    <?php foreach ($tasks as $t): ?>
      <?php $statusPill = $t['done'] ? ['active', 'Done'] : ($t['is_due'] ? ['bad', 'Pending'] : ['inactive', 'Not due yet']); ?>
      <details class="row-card <?= $t['done'] ? 'row-card-good' : ($t['pending'] ? 'row-card-bad' : 'row-card-neutral') ?>">
        <summary>
          <span class="row-card-heading"><strong><?= e($t['title']) ?></strong> <span class="pill pill-<?= taskFrequencyPillClass($t['frequency']) ?>"><?= e(taskFrequencyLabel($t)) ?></span> <span class="pill pill-<?= $statusPill[0] ?>"><?= $statusPill[1] ?></span></span>
          <span class="row-chevron"><?= tabIcon('chevron') ?></span>
        </summary>
        <div class="row-card-detail">
          <?php if ($t['done']): ?>
            <p class="text-soft" style="margin:0;">Completed by <strong><?= e($t['completed_by_name']) ?></strong> at <?= formatDateTime($t['completed_at']) ?>.</p>
          <?php else: ?>
            <p class="text-soft" style="margin:0;">Not yet completed for this period<?= $t['due_time'] ? ' - due by ' . date('h:i A', strtotime($t['due_time'])) : '' ?>.</p>
          <?php endif; ?>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
