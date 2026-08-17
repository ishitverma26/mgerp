<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
requireRole(['Admin', 'Plant Head']);

$isAdmin = $currentUser['role_name'] === 'Admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $startTime = clean($_POST['start_time'] ?? '');
        $endTime = clean($_POST['end_time'] ?? '');
        $reason = clean($_POST['reason'] ?? '');

        if ($startTime === '' || $reason === '') {
            setFlash('error', 'Enter a start time and reason.');
        } elseif ($endTime !== '' && $endTime < $startTime) {
            setFlash('error', 'End time cannot be before the start time.');
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO machine_downtime_log (start_time, end_time, reason, created_by) VALUES (:s, :e, :r, :uid)"
            );
            $stmt->execute([
                ':s' => $startTime, ':e' => $endTime !== '' ? $endTime : null,
                ':r' => $reason, ':uid' => $currentUser['id'],
            ]);
            $newId = (int) $pdo->lastInsertId();
            logAudit($pdo, $currentUser['id'], 'create', 'machine_downtime_log', $newId, null, [
                'start_time' => $startTime, 'end_time' => $endTime !== '' ? $endTime : null, 'reason' => $reason,
            ]);
            setFlash('success', 'Down time logged.' . ($endTime === '' ? ' Mark it ended once the machine is back up.' : ''));
        }
        redirect('/modules/utilities/downtime.php');
    }

    if ($action === 'end_now') {
        $id = (int) ($_POST['id'] ?? 0);
        $endTime = clean($_POST['end_time'] ?? '');
        $stmt = $pdo->prepare("SELECT * FROM machine_downtime_log WHERE id=:id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        if (!$row || $row['end_time'] !== null) {
            setFlash('error', 'This entry is already closed.');
        } elseif ($endTime === '' || $endTime < $row['start_time']) {
            setFlash('error', 'Enter a valid end time (after the start time).');
        } else {
            $pdo->prepare("UPDATE machine_downtime_log SET end_time=:e, updated_at=NOW() WHERE id=:id")
                ->execute([':e' => $endTime, ':id' => $id]);
            logAudit($pdo, $currentUser['id'], 'end_downtime', 'machine_downtime_log', $id,
                ['end_time' => null], ['end_time' => $endTime]);
            setFlash('success', 'Down time closed out.');
        }
        redirect('/modules/utilities/downtime.php');
    }

    if ($action === 'edit' && $isAdmin) {
        $id = (int) ($_POST['id'] ?? 0);
        $startTime = clean($_POST['start_time'] ?? '');
        $endTime = clean($_POST['end_time'] ?? '');
        $reason = clean($_POST['reason'] ?? '');
        $stmt = $pdo->prepare("SELECT * FROM machine_downtime_log WHERE id=:id");
        $stmt->execute([':id' => $id]);
        $old = $stmt->fetch();

        if (!$old || $startTime === '' || $reason === '') {
            setFlash('error', 'Enter a start time and reason.');
        } elseif ($endTime !== '' && $endTime < $startTime) {
            setFlash('error', 'End time cannot be before the start time.');
        } else {
            $pdo->prepare("UPDATE machine_downtime_log SET start_time=:s, end_time=:e, reason=:r, updated_at=NOW() WHERE id=:id")
                ->execute([':s' => $startTime, ':e' => $endTime !== '' ? $endTime : null, ':r' => $reason, ':id' => $id]);
            logAudit($pdo, $currentUser['id'], 'update', 'machine_downtime_log', $id, $old, [
                'start_time' => $startTime, 'end_time' => $endTime !== '' ? $endTime : null, 'reason' => $reason,
            ]);
            setFlash('success', 'Entry updated.');
        }
        redirect('/modules/utilities/downtime.php');
    }

    if ($action === 'delete' && $isAdmin) {
        $id = (int) ($_POST['id'] ?? 0);
        $old = $pdo->prepare("SELECT * FROM machine_downtime_log WHERE id=:id");
        $old->execute([':id' => $id]);
        $oldRow = $old->fetch();
        $pdo->prepare("DELETE FROM machine_downtime_log WHERE id=:id")->execute([':id' => $id]);
        logAudit($pdo, $currentUser['id'], 'delete', 'machine_downtime_log', $id, $oldRow, null);
        setFlash('success', 'Entry deleted.');
        redirect('/modules/utilities/downtime.php');
    }
}

$editRow = null;
if ($isAdmin && !empty($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM machine_downtime_log WHERE id=:id");
    $stmt->execute([':id' => (int) $_GET['edit']]);
    $editRow = $stmt->fetch();
}

$rows = $pdo->query(
    "SELECT d.*, u.name AS user_name FROM machine_downtime_log d JOIN users u ON u.id = d.created_by ORDER BY d.start_time DESC"
)->fetchAll();
$ongoingCount = count(array_filter($rows, fn($r) => $r['end_time'] === null));

function downtimeDuration(string $start, ?string $end): string {
    if (!$end) return '-';
    $mins = max(0, (int) round((strtotime($end) - strtotime($start)) / 60));
    $h = intdiv($mins, 60);
    $m = $mins % 60;
    return ($h > 0 ? $h . 'h ' : '') . $m . 'm';
}

$pageTitle = 'Machine Down Time';
$activeMenu = 'downtime';
$hideTopbar = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="activity-card-row" style="margin-bottom:16px;">
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-blue"><?= tabIcon('clock') ?></span>
      <span class="activity-card-label">Total Logged</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= count($rows) ?></div>
  </div>
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-red"><?= tabIcon('alert') ?></span>
      <span class="activity-card-label">Currently Down</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= $ongoingCount ?></div>
  </div>
</div>

<details class="card">
  <summary class="flex-between">
    <h3 class="mt-0" style="margin:0;">Log Down Time</h3>
    <span class="batch-chevron"><?= tabIcon('chevron') ?></span>
  </summary>
  <div class="card-detail">
  <form method="post" action="">
    <input type="hidden" name="action" value="create">
    <div class="form-row">
      <div>
        <label>Start Time *</label>
        <input type="datetime-local" name="start_time" required value="<?= date('Y-m-d\TH:i') ?>">
      </div>
      <div>
        <label>End Time <span class="text-soft">(leave blank if still down)</span></label>
        <input type="datetime-local" name="end_time">
      </div>
    </div>
    <div><label>Reason *</label><input type="text" name="reason" required maxlength="255"></div>
    <button type="submit" class="btn btn-accent" style="margin-top:14px;width:100%;">Log Down Time</button>
  </form>
  </div>
</details>

<details class="card"<?= $editRow ? ' open' : '' ?>>
  <summary class="flex-between">
    <h3 class="mt-0" style="margin:0;"><span class="icon-chip icon-chip-red" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('alert') ?></span>Down Time Log</h3>
    <span class="batch-chevron"><?= tabIcon('chevron') ?></span>
  </summary>
  <div class="card-detail">
  <?php if (!$rows): ?>
    <p class="row-card-empty">No down time logged yet.</p>
  <?php else: ?>
  <div class="row-list">
    <?php foreach ($rows as $r): ?>
      <?php $ongoing = $r['end_time'] === null; $isEditing = $editRow && (int) $editRow['id'] === (int) $r['id']; ?>
      <details class="row-card <?= $ongoing ? 'row-card-pending' : 'row-card-good' ?>"<?= $isEditing ? ' open' : '' ?>>
        <summary>
          <span class="row-card-heading"><strong><?= e($r['reason']) ?></strong> <span class="text-soft"><?= formatDateTime($r['start_time']) ?></span> <span class="pill pill-<?= $ongoing ? 'pending' : 'active' ?>"><?= $ongoing ? 'Ongoing' : downtimeDuration($r['start_time'], $r['end_time']) ?></span></span>
          <span class="row-chevron"><?= tabIcon('chevron') ?></span>
        </summary>
        <div class="row-card-detail">
        <?php if ($isEditing): ?>
          <form method="post" action="">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <div class="form-row">
              <div><label>Start Time *</label><input type="datetime-local" name="start_time" required value="<?= e(str_replace(' ', 'T', substr($r['start_time'], 0, 16))) ?>"></div>
              <div><label>End Time</label><input type="datetime-local" name="end_time" value="<?= e($r['end_time'] ? str_replace(' ', 'T', substr($r['end_time'], 0, 16)) : '') ?>"></div>
            </div>
            <div><label>Reason *</label><input type="text" name="reason" required maxlength="255" value="<?= e($r['reason']) ?>"></div>
            <div class="row-card-actions" style="margin-top:12px;">
              <button type="submit" class="btn btn-sm btn-accent">Update</button>
              <a href="<?= APP_URL ?>/modules/utilities/downtime.php<?= $embedMode ? '?embed=1' : '' ?>" class="btn btn-sm btn-outline">Cancel</a>
            </div>
          </form>
        <?php else: ?>
        <div class="row-card-fields">
          <div><div class="field-label">Start</div><div class="field-value"><?= formatDateTime($r['start_time']) ?></div></div>
          <div><div class="field-label">End</div><div class="field-value"><?= $r['end_time'] ? formatDateTime($r['end_time']) : 'Ongoing' ?></div></div>
          <div><div class="field-label">Duration</div><div class="field-value"><?= downtimeDuration($r['start_time'], $r['end_time']) ?></div></div>
          <div><div class="field-label">Logged By</div><div class="field-value"><?= e($r['user_name']) ?></div></div>
        </div>
        <?php if ($ongoing): ?>
          <form method="post" action="" class="form-row filter-form" style="margin-top:12px;align-items:flex-end;">
            <input type="hidden" name="action" value="end_now">
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <div><label>End Time *</label><input type="datetime-local" name="end_time" required value="<?= date('Y-m-d\TH:i') ?>"></div>
            <div><button type="submit" class="btn btn-sm btn-accent">Mark Ended</button></div>
          </form>
        <?php endif; ?>
        <?php if ($isAdmin): ?>
          <div class="row-card-actions" style="margin-top:12px;">
            <a class="btn btn-sm btn-outline" href="?edit=<?= (int) $r['id'] ?><?= $embedMode ? '&embed=1' : '' ?>">Edit</a>
            <form method="post" style="display:inline" data-confirm="Delete this down time entry? This cannot be undone.">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger">Delete</button>
            </form>
          </div>
        <?php endif; ?>
        <?php endif; ?>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  </div>
</details>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
