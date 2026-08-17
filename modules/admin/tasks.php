<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/tasks.php';
requireRole(['Admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'save') {
        $title = clean($_POST['title'] ?? '');
        $allowedFreq = ['daily', 'weekly', 'monthly', 'custom'];
        $frequency = in_array($_POST['frequency'] ?? '', $allowedFreq, true) ? $_POST['frequency'] : 'daily';

        $customInterval = null;
        $customUnit = null;
        if ($frequency === 'custom') {
            $customInterval = max(1, (int) ($_POST['custom_interval'] ?? 1));
            $allowedUnits = ['days', 'weeks', 'months', 'years'];
            $customUnit = in_array($_POST['custom_unit'] ?? '', $allowedUnits, true) ? $_POST['custom_unit'] : 'days';
        }

        $dueTimeRaw = clean($_POST['due_time'] ?? '');
        $dueTime = $dueTimeRaw !== '' ? $dueTimeRaw . ':00' : null;

        if ($title === '') {
            setFlash('error', 'Task title is required.');
        } else {
            $new = [
                'title' => $title, 'frequency' => $frequency,
                'custom_interval' => $customInterval, 'custom_unit' => $customUnit, 'due_time' => $dueTime,
            ];
            if ($id > 0) {
                $old = $pdo->prepare("SELECT * FROM tasks WHERE id=:id"); $old->execute([':id' => $id]); $oldRow = $old->fetch();
                $pdo->prepare(
                    "UPDATE tasks SET title=:title, frequency=:frequency, custom_interval=:custom_interval, custom_unit=:custom_unit, due_time=:due_time WHERE id=:id"
                )->execute($new + [':id' => $id]);
                logAudit($pdo, $currentUser['id'], 'update', 'tasks', $id, $oldRow, $new);
                setFlash('success', 'Task updated.');
            } else {
                $pdo->prepare(
                    "INSERT INTO tasks (title, frequency, custom_interval, custom_unit, due_time, created_by)
                     VALUES (:title, :frequency, :custom_interval, :custom_unit, :due_time, :created_by)"
                )->execute($new + [':created_by' => $currentUser['id']]);
                logAudit($pdo, $currentUser['id'], 'create', 'tasks', (int) $pdo->lastInsertId(), null, $new);
                setFlash('success', 'Task added.');
            }
        }
    }

    if ($action === 'toggle') {
        $current = $pdo->prepare("SELECT status FROM tasks WHERE id=:id"); $current->execute([':id' => $id]);
        $status = $current->fetchColumn(); $newStatus = $status === 'active' ? 'inactive' : 'active';
        $pdo->prepare("UPDATE tasks SET status=:s WHERE id=:id")->execute([':s' => $newStatus, ':id' => $id]);
        logAudit($pdo, $currentUser['id'], 'status_change', 'tasks', $id, ['status' => $status], ['status' => $newStatus]);
        setFlash('success', 'Status updated.');
    }

    if ($action === 'delete') {
        $old = $pdo->prepare("SELECT * FROM tasks WHERE id=:id"); $old->execute([':id' => $id]); $oldRow = $old->fetch();
        $pdo->prepare("DELETE FROM tasks WHERE id=:id")->execute([':id' => $id]);
        logAudit($pdo, $currentUser['id'], 'delete', 'tasks', $id, $oldRow, null);
        setFlash('success', 'Task deleted.');
    }
    redirect('/modules/admin/tasks.php');
}

$editRow = null;
if (!empty($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id=:id"); $stmt->execute([':id' => (int) $_GET['edit']]); $editRow = $stmt->fetch();
}
$rows = $pdo->query("SELECT t.*, u.name AS created_by_name FROM tasks t JOIN users u ON u.id = t.created_by ORDER BY t.id DESC")->fetchAll();

$pageTitle = 'Tasks';
$activeMenu = 'm-tasks';
$hideTopbar = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="card">
  <h3 class="mt-0"><?= $editRow ? 'Edit Task' : 'Add Task' ?></h3>
  <p class="help-text">Sets a recurring checklist item for Plant Head - it stays until you delete it here.</p>
  <form method="post" action="">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= e($editRow['id'] ?? '') ?>">
    <label>Task Title *</label>
    <input type="text" name="title" required value="<?= e($editRow['title'] ?? '') ?>" placeholder="e.g. Check compressor oil level">
    <label>Frequency *</label>
    <div class="segmented segmented-full" id="freqToggle">
      <button type="button" class="segmented-btn<?= ($editRow['frequency'] ?? 'daily') === 'daily' ? ' active' : '' ?>" data-freq="daily">Daily</button>
      <button type="button" class="segmented-btn<?= ($editRow['frequency'] ?? '') === 'weekly' ? ' active' : '' ?>" data-freq="weekly">Weekly</button>
      <button type="button" class="segmented-btn<?= ($editRow['frequency'] ?? '') === 'monthly' ? ' active' : '' ?>" data-freq="monthly">Monthly</button>
      <button type="button" class="segmented-btn<?= ($editRow['frequency'] ?? '') === 'custom' ? ' active' : '' ?>" data-freq="custom">Custom</button>
    </div>
    <input type="hidden" name="frequency" id="freqInput" value="<?= e($editRow['frequency'] ?? 'daily') ?>">

    <div id="customFreqFields" class="form-row" style="margin-top:10px;<?= ($editRow['frequency'] ?? '') === 'custom' ? '' : 'display:none;' ?>">
      <div>
        <label>Every *</label>
        <input type="number" min="1" step="1" name="custom_interval" value="<?= e($editRow['custom_interval'] ?? 1) ?>">
      </div>
      <div>
        <label>Unit *</label>
        <select name="custom_unit">
          <?php foreach (['days' => 'Days', 'weeks' => 'Weeks', 'months' => 'Months', 'years' => 'Years'] as $val => $label): ?>
            <option value="<?= $val ?>" <?= ($editRow['custom_unit'] ?? 'days') === $val ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <label style="margin-top:14px;">Due Time (optional)</label>
    <input type="time" name="due_time" value="<?= e($editRow['due_time'] ? substr($editRow['due_time'], 0, 5) : '') ?>">
    <p class="help-text">If set, this task only shows as "pending" for Plant Head once this time of day has passed - leave blank to make it pending as soon as its period starts.</p>

    <button type="submit" class="btn btn-accent" style="margin-top:14px;"><?= $editRow ? 'Update' : 'Add' ?></button>
    <?php if ($editRow): ?><a href="<?= APP_URL ?>/modules/admin/tasks.php<?= $embedMode ? '?embed=1' : '' ?>" class="btn btn-outline">Cancel</a><?php endif; ?>
  </form>
  <script>
  (function () {
    var toggle = document.getElementById('freqToggle');
    var input = document.getElementById('freqInput');
    var customFields = document.getElementById('customFreqFields');
    if (!toggle || !input) return;
    toggle.querySelectorAll('.segmented-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        toggle.querySelectorAll('.segmented-btn').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        var freq = btn.getAttribute('data-freq');
        input.value = freq;
        if (customFields) customFields.style.display = freq === 'custom' ? '' : 'none';
      });
    });
  })();
  </script>
</div>
<div class="card">
  <h3 class="mt-0"><span class="icon-chip icon-chip-blue" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('check-square') ?></span>All Tasks</h3>
  <?php if (!$rows): ?>
    <p class="row-card-empty">No tasks yet.</p>
  <?php else: ?>
  <div class="row-list">
    <?php foreach ($rows as $r): ?>
      <details class="row-card <?= $r['status'] === 'active' ? 'row-card-good' : 'row-card-neutral' ?>">
        <summary>
          <span class="row-card-heading"><strong><?= e($r['title']) ?></strong> <span class="pill pill-<?= taskFrequencyPillClass($r['frequency']) ?>"><?= e(taskFrequencyLabel($r)) ?></span> <span class="pill pill-<?= e($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span></span>
          <span class="row-chevron"><?= tabIcon('chevron') ?></span>
        </summary>
        <div class="row-card-detail">
        <p class="text-soft" style="margin:0 0 10px;">Added by <?= e($r['created_by_name']) ?> &middot; <?= formatDateTime($r['created_at']) ?><?php if ($r['due_time']): ?> &middot; Due by <?= date('h:i A', strtotime($r['due_time'])) ?><?php endif; ?></p>
        <div class="row-card-actions">
          <a class="btn btn-sm btn-outline" href="?edit=<?= (int) $r['id'] ?><?= $embedMode ? '&embed=1' : '' ?>">Edit</a>
          <form method="post" style="display:inline" data-confirm="Change status?">
            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline"><?= $r['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
          </form>
          <form method="post" style="display:inline" data-confirm="Delete this task? This cannot be undone.">
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
          </form>
        </div>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
