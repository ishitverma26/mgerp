<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['Admin', 'Plant Head']);

$where = [];
$params = [];
if (!empty($_GET['batch_id'])) { $where[] = 'pu.batch_id = :batch_id'; $params[':batch_id'] = (int) $_GET['batch_id']; }
if (!empty($_GET['date_from'])) { $where[] = 'DATE(pu.update_datetime) >= :date_from'; $params[':date_from'] = $_GET['date_from']; }
if (!empty($_GET['date_to'])) { $where[] = 'DATE(pu.update_datetime) <= :date_to'; $params[':date_to'] = $_GET['date_to']; }

$sql = "SELECT pu.*, g.batch_no, p.name AS product_name, p.size_kg, t.token_value, u.name AS user_name
        FROM packing_production_updates pu
        JOIN production_batches b ON b.id=pu.batch_id
        JOIN batch_groups g ON g.id=b.batch_group_id
        JOIN products p ON p.id=b.product_id
        JOIN tokens t ON t.id=b.token_id
        JOIN users u ON u.id=pu.user_id";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY pu.update_datetime DESC, pu.id DESC LIMIT 1000';
$stmt = $pdo->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll();

$batches = $pdo->query(
    "SELECT b.id, g.batch_no, p.name AS product_name, p.size_kg
     FROM production_batches b JOIN batch_groups g ON g.id=b.batch_group_id JOIN products p ON p.id=b.product_id
     ORDER BY b.id DESC"
)->fetchAll();

// Group into day buckets for a daily-record view, while still holding the
// complete history (not just a recent slice) so every day is browsable.
$days = [];
foreach ($rows as $r) {
    $day = date('Y-m-d', strtotime($r['update_datetime']));
    if (!isset($days[$day])) $days[$day] = [];
    $days[$day][] = $r;
}

$totalEntries = count($rows);
$totalBagsPacked = array_sum(array_column($rows, 'increase_since_last'));
$daysCovered = count($days);

$pageTitle = 'Packing Production Report';
$activeMenu = 'r-packing';
$hideTopbar = true;
$pageHasFilter = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($rows): ?>
<div class="activity-card-row" style="margin-bottom:16px;">
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-amber"><?= tabIcon('edit') ?></span>
      <span class="activity-card-label">Entries</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= $totalEntries ?></div>
  </div>
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-green"><?= tabIcon('package') ?></span>
      <span class="activity-card-label">Bags Packed</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= number_format($totalBagsPacked) ?></div>
  </div>
  <div class="activity-card">
    <div class="activity-card-top">
      <span class="activity-card-icon activity-card-icon-blue"><?= tabIcon('clock') ?></span>
      <span class="activity-card-label">Days</span>
      <span class="activity-card-view"><?= tabIcon('eye') ?></span>
    </div>
    <div class="activity-card-value"><?= $daysCovered ?></div>
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
      <form method="get" class="form-row filter-form" style="align-items:flex-end;">
        <div><label>Batch</label><select name="batch_id"><option value="">All</option>
          <?php foreach ($batches as $b): ?><option value="<?= $b['id'] ?>" <?= (($_GET['batch_id'] ?? '')==$b['id'])?'selected':'' ?>><?= e($b['batch_no']) ?> &middot; <?= e($b['product_name']) ?> <?= e($b['size_kg']) ?>KG</option><?php endforeach; ?>
        </select></div>
        <div><label>From</label><input type="date" name="date_from" value="<?= e($_GET['date_from'] ?? '') ?>"></div>
        <div><label>To</label><input type="date" name="date_to" value="<?= e($_GET['date_to'] ?? '') ?>"></div>
        <div><button type="submit" class="btn btn-outline">Filter</button></div>
      </form>
    </div>
  </div>
</div>

<?php if (!$days): ?>
<div class="card"><p class="row-card-empty">No data.</p></div>
<?php else: ?>
<?php foreach ($days as $day => $dayRows): ?>
  <div class="card">
    <h3 class="mt-0"><span class="icon-chip icon-chip-purple" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('chart') ?></span><?= formatDate($day) ?> <span class="text-soft" style="font-weight:400;font-size:13px;">&middot; <?= count($dayRows) ?> entr<?= count($dayRows) === 1 ? 'y' : 'ies' ?></span></h3>
    <div class="row-list">
      <?php foreach ($dayRows as $r): ?>
        <details class="row-card row-card-info">
          <summary>
            <span class="row-card-heading"><strong><?= e($r['product_name']) ?> <?= e($r['size_kg']) ?>KG</strong> <span class="text-soft">Token <?= e($r['token_value']) ?> &middot; Batch <?= e($r['batch_no']) ?> &middot; <?= date('h:i A', strtotime($r['update_datetime'])) ?></span></span>
            <span class="row-chevron"><?= tabIcon('chevron') ?></span>
          </summary>
          <div class="row-card-detail">
          <div class="row-card-fields">
            <div><div class="field-label">Left</div><div class="field-value"><?= (int)$r['left_nozzle_qty'] ?></div></div>
            <div><div class="field-label">Right</div><div class="field-value"><?= (int)$r['right_nozzle_qty'] ?></div></div>
            <div><div class="field-label">Total Good</div><div class="field-value"><?= (int)$r['total_good_qty'] ?></div></div>
            <div><div class="field-label">Damage</div><div class="field-value"><?= (int)$r['damage_qty_cumulative'] ?></div></div>
            <div><div class="field-label">By</div><div class="field-value"><?= e($r['user_name']) ?></div></div>
            <?php if (!empty($r['photo'])): ?>
            <div><div class="field-label">Photo</div><div class="field-value"><a href="<?= UPLOAD_URL ?><?= e($r['photo']) ?>" target="_blank"><img src="<?= UPLOAD_URL ?><?= e($r['photo']) ?>" alt="Production photo" style="width:60px;height:60px;object-fit:cover;border-radius:8px;"></a></div></div>
            <?php endif; ?>
          </div>
          </div>
        </details>
      <?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>
<?php endif; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
