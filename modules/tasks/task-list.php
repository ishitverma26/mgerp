<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/tasks.php';
requireRole(['Plant Head']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'complete_task') {
    $taskId = (int) ($_POST['task_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id=:id AND status='active'");
    $stmt->execute([':id' => $taskId]);
    $task = $stmt->fetch();
    if ($task) {
        $pk = taskPeriodKey($task);
        $pdo->prepare("INSERT IGNORE INTO task_completions (task_id, period_key, completed_by) VALUES (:tid, :pk, :uid)")
            ->execute([':tid' => $taskId, ':pk' => $pk, ':uid' => $currentUser['id']]);
        setFlash('success', 'Task marked complete.');
    }
    redirect('/modules/tasks/task-list.php');
}

$tasks = getActiveTasksWithStatus($pdo);
$doneCount = count(array_filter($tasks, fn($t) => $t['done']));
$pendingCount = count(array_filter($tasks, fn($t) => $t['pending']));

$pageTitle = 'Tasks';
$activeMenu = 'task-list';
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
  <h3 class="mt-0"><span class="icon-chip icon-chip-red" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('check-square') ?></span>Your Tasks</h3>
  <p class="help-text">Tap a task's box once it's done. A task with a due time only counts as pending once that time has passed.</p>
  <?php if (!$tasks): ?>
    <p class="row-card-empty">No tasks set by Admin yet.</p>
  <?php else: ?>
  <div class="row-list">
    <?php foreach ($tasks as $t): ?>
      <?php $freqLabel = taskFrequencyLabel($t) . ($t['due_time'] ? ' &middot; by ' . date('h:i A', strtotime($t['due_time'])) : ''); ?>
      <?php if ($t['done']): ?>
        <div class="row-card row-card-good task-row">
          <div>
            <div class="task-row-title"><?= e($t['title']) ?></div>
            <span class="pill pill-<?= taskFrequencyPillClass($t['frequency']) ?>" style="margin-top:4px;"><?= $freqLabel ?></span>
          </div>
          <span class="task-row-check" title="Done"><?= tabIcon('check-square') ?></span>
        </div>
      <?php else: ?>
        <form method="post" action="" class="task-row-form">
          <input type="hidden" name="action" value="complete_task">
          <input type="hidden" name="task_id" value="<?= (int) $t['id'] ?>">
          <button type="submit" class="row-card <?= $t['pending'] ? 'row-card-bad' : 'row-card-neutral' ?> task-row task-row-btn">
            <div>
              <div class="task-row-title"><?= e($t['title']) ?></div>
              <span class="pill pill-<?= taskFrequencyPillClass($t['frequency']) ?>" style="margin-top:4px;"><?= $freqLabel ?></span>
              <?php if (!$t['is_due']): ?><span class="pill pill-inactive" style="margin-top:4px;">Not due yet</span><?php endif; ?>
            </div>
            <span class="task-row-tickbox" aria-hidden="true"><?= tabIcon('square') ?></span>
          </button>
        </form>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
