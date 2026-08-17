<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';
requireRole(['Admin']);

$todayInward = $pdo->query("SELECT COUNT(*) AS c, COALESCE(SUM(gross_weight),0) AS mt FROM raw_material_inward WHERE inward_date = CURDATE()")->fetch();
$todayProcessing = $pdo->query("SELECT COUNT(*) AS c, COALESCE(SUM(requirement_jumbo),0) AS jumbo, COALESCE(SUM(total_mt_consumed),0) AS mt FROM processing_requests WHERE processing_date = CURDATE()")->fetch();

// A "batch" is a batch_group (can hold several SKUs) - count groups, not the
// underlying production_batches rows, or a single 3-SKU batch shows as "3".
['groups' => $groups, 'revisions' => $revisions] = buildBatchGroups($pdo);
$activeGroups = array_filter($groups, fn($g) => $g['status_label'] === 'In Production');
$activeBatches = count($activeGroups);

$datePreset = clean($_GET['date_preset'] ?? '');
$dateFrom = clean($_GET['date_from'] ?? '');
$dateTo = clean($_GET['date_to'] ?? '');
if ($datePreset === 'today') {
    $dateFrom = $dateTo = date('Y-m-d');
} elseif ($datePreset === 'yesterday') {
    $dateFrom = $dateTo = date('Y-m-d', strtotime('-1 day'));
}
if ($dateFrom !== '' || $dateTo !== '') {
    $groups = array_filter($groups, function ($g) use ($dateFrom, $dateTo) {
        $d = substr($g['created_at'], 0, 10);
        if ($dateFrom !== '' && $d < $dateFrom) return false;
        if ($dateTo !== '' && $d > $dateTo) return false;
        return true;
    });
}

$totalStockJumbo = $pdo->query("SELECT COALESCE(SUM(remaining_jumbo),0) FROM raw_material_stock WHERE status='active'")->fetchColumn();
$totalProcessedMt = (float) $pdo->query("SELECT COALESCE(SUM(total_mt_consumed),0) FROM processing_requests")->fetchColumn() + getRecoveredDamageMt($pdo);
$liveStock = buildLiveStock($pdo);
$totalLiveStock = array_sum(array_column($liveStock, 'qty'));

// Both kinds of "damage" the app tracks - packed (finished) bags damaged
// during packing, and empty jumbo bags damaged in Utilities - counted
// together for the one Damage Record link on this dashboard (see
// modules/reports/damage-report.php, which merges both sources).
$totalDamageCount = (int) $pdo->query("SELECT COALESCE(SUM(damage_qty),0) FROM damage_entries")->fetchColumn()
    + (int) $pdo->query("SELECT COALESCE(SUM(bags),0) FROM empty_jumbo_transactions WHERE transaction_type='damaged'")->fetchColumn();

$notifications = getRecentNotifications($pdo, 25, true, $currentUser['id']);
$unreadNotifCount = count(array_filter($notifications, fn($n) => !$n['is_read']));

$dailyMtTarget = (float) getSetting($pdo, 'daily_mt_target', 0);
$monthlyMtTarget = (float) getSetting($pdo, 'monthly_mt_target', 0);
$todayMtPacked = getPackedMt($pdo, date('Y-m-d'), date('Y-m-d'));
$monthMtPacked = getPackedMt($pdo, date('Y-m-01'), date('Y-m-d'));
$dailyTargetPct = $dailyMtTarget > 0 ? min(100, round($todayMtPacked / $dailyMtTarget * 100)) : 0;
$monthlyTargetPct = $monthlyMtTarget > 0 ? min(100, round($monthMtPacked / $monthlyMtTarget * 100)) : 0;

$pageTitle = 'Admin Dashboard';
$activeMenu = 'dashboard';
$hideTopbar = true;
$customHero = true; // this page builds its own welcome-hero below instead of the generic page-header
require_once __DIR__ . '/../includes/header.php';
?>
<div class="welcome-hero">
  <div class="welcome-hero-top">
    <div class="welcome-avatar"><?= e(strtoupper(substr($currentUser['role_name'], 0, 1))) ?></div>
    <div>
      <div class="welcome-hero-greeting">Welcome to</div>
      <div class="welcome-hero-name"><?= e($currentUser['role_name']) ?> Panel</div>
    </div>
    <div class="welcome-hero-actions">
      <a href="<?= APP_URL ?>/modules/admin/settings.php" class="welcome-hero-icon-btn" title="Settings"><?= tabIcon('settings') ?></a>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/partials/notification-panel.php'; ?>

<div class="section-head"><h3><span class="icon-chip icon-chip-green"><?= tabIcon('chart') ?></span>Production Targets</h3></div>
<div class="card">
  <div class="batch-summary-progress-label"><span>Daily Target &middot; <?= number_format($todayMtPacked, 1) ?> / <?= number_format($dailyMtTarget, 1) ?> MT</span><strong><?= $dailyTargetPct ?>%</strong></div>
  <div class="progress-bar"><div class="progress-bar-fill" style="width:<?= $dailyTargetPct ?>%"></div></div>
  <div class="batch-summary-progress-label" style="margin-top:18px;"><span>Monthly Target &middot; <?= number_format($monthMtPacked, 1) ?> / <?= number_format($monthlyMtTarget, 1) ?> MT</span><strong><?= $monthlyTargetPct ?>%</strong></div>
  <div class="progress-bar"><div class="progress-bar-fill" style="width:<?= $monthlyTargetPct ?>%"></div></div>
  <?php if ($dailyMtTarget <= 0 && $monthlyMtTarget <= 0): ?>
    <p class="help-text" style="margin-bottom:0;margin-top:12px;"><em>No targets set yet - set them in Settings.</em></p>
  <?php endif; ?>
</div>

<div class="section-head">
  <h3><span class="icon-chip icon-chip-blue"><?= tabIcon('truck') ?></span>Today's Snapshot</h3>
  <button type="button" class="section-notif-btn" id="notifBellBtn" title="Notifications">
    <?= tabIcon('bell') ?>
    <?php if ($unreadNotifCount > 0): ?><span class="notif-badge"><?= $unreadNotifCount > 9 ? '9+' : $unreadNotifCount ?></span><?php endif; ?>
  </button>
</div>
<div class="activity-card-row">
  <a href="<?= APP_URL ?>/modules/inward/inward-list.php" class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-purple"><?= tabIcon('truck') ?></span>
      <span class="activity-card-label">Inward</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= (int) $todayInward['c'] ?></div>
  </a>
  <a href="<?= APP_URL ?>/modules/reports/processing-report.php" class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-red"><?= tabIcon('refresh') ?></span>
      <span class="activity-card-label">Processing</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= (int) $todayProcessing['c'] ?></div>
  </a>
  <a href="<?= APP_URL ?>/modules/packing/batch-list.php" class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-blue"><?= tabIcon('package') ?></span>
      <span class="activity-card-label">Batches</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= (int) $activeBatches ?></div>
  </a>
</div>

<div class="section-head"><h3><span class="icon-chip icon-chip-purple"><?= tabIcon('archive') ?></span>Stock Overview</h3></div>
<div class="activity-card-row">
  <a href="<?= APP_URL ?>/modules/stock/stock-list.php" class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-blue"><?= tabIcon('archive') ?></span>
      <span class="activity-card-label">Raw Jumbo</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= (int) $totalStockJumbo ?></div>
  </a>
  <a href="<?= APP_URL ?>/modules/reports/packing-report.php" class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-green"><?= tabIcon('check-square') ?></span>
      <span class="activity-card-label">MT Processed</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= number_format($totalProcessedMt, 1) ?></div>
  </a>
  <a href="<?= APP_URL ?>/modules/packing/live-stock.php" class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-amber"><?= tabIcon('layers') ?></span>
      <span class="activity-card-label">Live Stock</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= number_format($totalLiveStock) ?></div>
  </a>
  <a href="<?= APP_URL ?>/modules/reports/damage-report.php" class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-red"><?= tabIcon('alert') ?></span>
      <span class="activity-card-label">Damage Record</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= number_format($totalDamageCount) ?></div>
  </a>
</div>

<div class="section-head"><h3><span class="icon-chip icon-chip-amber"><?= tabIcon('plus') ?></span>Quick Actions</h3></div>
<div class="quick-actions-grid">
  <a href="<?= APP_URL ?>/modules/admin/labour-status.php" class="action-tile action-tile-green">
    <span class="action-tile-icon"><?= tabIllustration('team-hardhat') ?></span>
    <span class="action-tile-bottom"><span class="action-tile-label">Labour Attendance</span><?= tabIcon('arrow-right') ?></span>
  </a>
  <a href="<?= APP_URL ?>/modules/packing/batch-create.php" class="action-tile action-tile-amber">
    <span class="action-tile-icon"><?= tabIllustration('factory') ?></span>
    <span class="action-tile-bottom"><span class="action-tile-label">New Production Batch</span><?= tabIcon('arrow-right') ?></span>
  </a>
  <a href="<?= APP_URL ?>/modules/admin/task-status.php" class="action-tile action-tile-purple">
    <span class="action-tile-icon"><?= tabIllustration('checklist') ?></span>
    <span class="action-tile-bottom"><span class="action-tile-label">Tasks Status</span><?= tabIcon('arrow-right') ?></span>
  </a>
  <a href="<?= APP_URL ?>/modules/audit/audit-log.php" class="action-tile action-tile-red">
    <span class="action-tile-icon"><?= tabIllustration('report') ?></span>
    <span class="action-tile-bottom"><span class="action-tile-label">Audit Log</span><?= tabIcon('arrow-right') ?></span>
  </a>
</div>

<div class="section-head">
  <h3><span class="icon-chip icon-chip-purple"><?= tabIcon('package') ?></span>All Batches</h3>
  <div style="display:flex;align-items:center;gap:10px;">
    <span class="section-head-link"><?= count($groups) ?> total</span>
    <button type="button" class="page-header-settings" id="filterTriggerBtn" title="Filters" style="margin-left:0;"><?= tabIcon('filter') ?></button>
  </div>
</div>

<div class="masters-modal-overlay<?= (isset($_GET['date_preset']) || isset($_GET['date_from']) || isset($_GET['date_to'])) ? ' is-open' : '' ?>" id="filterModalOverlay">
  <div class="masters-modal filter-modal-sm">
    <div class="masters-modal-head">
      <span>Filters</span>
      <button type="button" class="masters-modal-close" id="filterModalClose" aria-label="Close">&times;</button>
    </div>
    <div class="filter-modal-body">
      <label>Quick Select</label>
      <div style="display:flex;gap:8px;margin:6px 0 16px;flex-wrap:wrap;">
        <a href="?date_preset=today" class="btn btn-sm <?= $datePreset === 'today' ? 'btn-accent' : 'btn-outline' ?>">Today</a>
        <a href="?date_preset=yesterday" class="btn btn-sm <?= $datePreset === 'yesterday' ? 'btn-accent' : 'btn-outline' ?>">Yesterday</a>
        <a href="<?= APP_URL ?>/dashboard/admin.php" class="btn btn-sm <?= $dateFrom === '' && $dateTo === '' ? 'btn-accent' : 'btn-outline' ?>">All</a>
      </div>
      <form method="get" action="" class="form-row filter-form" style="align-items:flex-end;">
        <div><label>From</label><input type="date" name="date_from" value="<?= e($dateFrom) ?>"></div>
        <div><label>To</label><input type="date" name="date_to" value="<?= e($dateTo) ?>"></div>
        <div><button type="submit" class="btn btn-outline">Filter</button></div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/partials/batch-cards.php'; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
