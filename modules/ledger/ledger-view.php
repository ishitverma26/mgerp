<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['Plant Head']);

$where = [];
$params = [];
if (!empty($_GET['type'])) { $where[] = 'l.transaction_type = :type'; $params[':type'] = $_GET['type']; }
if (!empty($_GET['date_from'])) { $where[] = 'DATE(l.transaction_date) >= :date_from'; $params[':date_from'] = $_GET['date_from']; }
if (!empty($_GET['date_to'])) { $where[] = 'DATE(l.transaction_date) <= :date_to'; $params[':date_to'] = $_GET['date_to']; }

$sql = "SELECT l.*, u.name AS user_name FROM stock_ledger l JOIN users u ON u.id=l.user_id";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY l.id DESC LIMIT 300';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pageTitle = 'Stock Ledger';
$activeMenu = 'ledger-view';
$hideTopbar = true;
$pageHasFilter = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="masters-modal-overlay<?= !empty($_GET) ? ' is-open' : '' ?>" id="filterModalOverlay">
  <div class="masters-modal filter-modal-sm">
    <div class="masters-modal-head">
      <span>Filters</span>
      <button type="button" class="masters-modal-close" id="filterModalClose" aria-label="Close">&times;</button>
    </div>
    <div class="filter-modal-body">
      <form method="get" action="" class="form-row filter-form" style="align-items:flex-end;">
        <div>
          <label>Transaction Type</label>
          <select name="type">
            <option value="">All</option>
            <?php foreach (['inward','processing_out','packing_finish','damage','reprocessing','garbage'] as $t): ?>
              <option value="<?= $t ?>" <?= (($_GET['type'] ?? '') === $t) ? 'selected' : '' ?>><?= e(ucwords(str_replace('_',' ', $t))) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div><label>From</label><input type="date" name="date_from" value="<?= e($_GET['date_from'] ?? '') ?>"></div>
        <div><label>To</label><input type="date" name="date_to" value="<?= e($_GET['date_to'] ?? '') ?>"></div>
        <div><button type="submit" class="btn btn-outline">Filter</button></div>
      </form>
    </div>
  </div>
</div>

<div class="card">
  <h3 class="mt-0">Stock Movements (latest 300)</h3>
  <?php if (!$rows): ?>
    <p class="row-card-empty">No ledger entries found.</p>
  <?php else: ?>
  <div class="row-list">
    <?php foreach ($rows as $r): ?>
      <details class="row-card">
        <summary>
          <span class="row-card-heading"><span class="pill pill-active"><?= e(ucwords(str_replace('_',' ', $r['transaction_type']))) ?></span> <span class="text-soft"><?= e(ucfirst($r['material_type'])) ?> &middot; <?= number_format($r['quantity'], 4) ?> <?= e($r['unit']) ?> &middot; <?= formatDateTime($r['transaction_date']) ?></span></span>
          <span class="row-chevron"><?= tabIcon('chevron') ?></span>
        </summary>
        <div class="row-card-detail">
        <div class="row-card-fields">
          <div><div class="field-label">Prev Stock</div><div class="field-value"><?= number_format($r['previous_stock'], 4) ?></div></div>
          <div><div class="field-label">New Stock</div><div class="field-value"><?= number_format($r['new_stock'], 4) ?></div></div>
          <div><div class="field-label">Ref</div><div class="field-value"><?= e($r['reference_table']) ?> #<?= (int) $r['reference_id'] ?></div></div>
          <div><div class="field-label">User</div><div class="field-value"><?= e($r['user_name']) ?></div></div>
          <div><div class="field-label">Remarks</div><div class="field-value"><?= e($r['remarks']) ?: '-' ?></div></div>
        </div>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
