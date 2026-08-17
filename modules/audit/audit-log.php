<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
requireRole(['Admin']);

$where = [];
$params = [];
if (!empty($_GET['module'])) { $where[] = 'a.module = :module'; $params[':module'] = $_GET['module']; }
if (!empty($_GET['user_id'])) { $where[] = 'a.user_id = :user_id'; $params[':user_id'] = (int) $_GET['user_id']; }

$sql = "SELECT a.*, u.name AS user_name FROM audit_logs a JOIN users u ON u.id=a.user_id";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY a.id DESC LIMIT 300';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$modules = $pdo->query("SELECT DISTINCT module FROM audit_logs ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);
$users = $pdo->query("SELECT id, name FROM users ORDER BY name")->fetchAll();

$lookups = buildAuditLookups($pdo);

$todayCount = 0;
foreach ($rows as $r) {
    if (date('Y-m-d', strtotime($r['created_at'])) === date('Y-m-d')) $todayCount++;
}
$distinctUsers = count(array_unique(array_column($rows, 'user_name')));

$pageTitle = 'Audit Log';
$activeMenu = 'audit-log';
$hideTopbar = true;
$pageHasFilter = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($rows): ?>
<div class="activity-card-row" style="margin-bottom:16px;">
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-blue"><?= tabIcon('clipboard') ?></span>
      <span class="activity-card-label">Entries Shown</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= count($rows) ?></div>
  </div>
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-amber"><?= tabIcon('clock') ?></span>
      <span class="activity-card-label">Today</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= $todayCount ?></div>
  </div>
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-green"><?= tabIcon('user') ?></span>
      <span class="activity-card-label">Users Active</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= $distinctUsers ?></div>
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
      <form method="get" action="" class="form-row filter-form" style="align-items:flex-end;">
        <div>
          <label>Module</label>
          <select name="module">
            <option value="">All</option>
            <?php foreach ($modules as $m): ?><option value="<?= e($m) ?>" <?= (($_GET['module'] ?? '') === $m) ? 'selected' : '' ?>><?= e(humanAuditModule($m)) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>User</label>
          <select name="user_id">
            <option value="">All</option>
            <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= (($_GET['user_id'] ?? '') == $u['id']) ? 'selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div><button type="submit" class="btn btn-outline">Filter</button></div>
      </form>
    </div>
  </div>
</div>

<div class="card">
  <h3 class="mt-0"><span class="icon-chip icon-chip-purple" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('clipboard') ?></span>Audit Trail (latest 300)</h3>
  <?php if (!$rows): ?>
    <p class="row-card-empty">No audit records found.</p>
  <?php else: ?>
  <div class="row-list">
    <?php foreach ($rows as $r): $described = describeAuditLog($r, $lookups); ?>
      <details class="row-card row-card-info">
        <summary>
          <span class="row-card-heading"><strong><?= e($described['summary']) ?></strong> <span class="text-soft"><?= e(humanAuditModule($r['module'])) ?> &middot; <?= e($r['user_name']) ?> &middot; <?= formatDateTime($r['created_at']) ?></span></span>
          <span class="row-chevron"><?= tabIcon('chevron') ?></span>
        </summary>
        <div class="row-card-detail">
        <?php if ($described['changes']): ?>
        <div class="row-card-fields">
          <?php foreach ($described['changes'] as $c): ?>
            <div><div class="field-label"><?= e($c['label']) ?></div><div class="field-value"><?= e((string) $c['old']) ?> &rarr; <?= e((string) $c['new']) ?></div></div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
          <p class="text-soft" style="margin:0;">No further details recorded.</p>
        <?php endif; ?>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
