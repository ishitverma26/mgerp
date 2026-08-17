<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['Admin']);

$where = [];
$params = [];
if (!empty($_GET['status'])) { $where[] = 'b.status = :status'; $params[':status'] = $_GET['status']; }

$sql = "SELECT b.*, g.batch_no, p.name AS product_name, p.size_kg, t.token_value,
        COALESCE((SELECT total_good_qty FROM packing_production_updates WHERE batch_id=b.id ORDER BY id DESC LIMIT 1),0) AS completed
        FROM production_batches b
        JOIN batch_groups g ON g.id=b.batch_group_id
        JOIN products p ON p.id=b.product_id JOIN tokens t ON t.id=b.token_id";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY b.id DESC';
$stmt = $pdo->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll();

$pageTitle = 'Production Batch Report';
$activeMenu = 'r-batch';
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
      <form method="get" class="form-row filter-form" style="align-items:flex-end;">
        <div><label>Status</label><select name="status"><option value="">All</option>
          <option value="active" <?= (($_GET['status'] ?? '')==='active')?'selected':'' ?>>Active</option>
          <option value="completed" <?= (($_GET['status'] ?? '')==='completed')?'selected':'' ?>>Completed</option>
          <option value="reopened" <?= (($_GET['status'] ?? '')==='reopened')?'selected':'' ?>>Reopened</option>
        </select></div>
        <div><button type="submit" class="btn btn-outline">Filter</button></div>
      </form>
    </div>
  </div>
</div>
<div class="card">
  <h3 class="mt-0">Production Batches</h3>
  <?php if (!$rows): ?>
    <p class="row-card-empty">No data.</p>
  <?php else: ?>
  <div class="row-list">
    <?php foreach ($rows as $r): $hasTarget = $r['target_bags'] !== null; $pending = $hasTarget ? max(0, $r['target_bags']-$r['completed']) : null; ?>
      <details class="row-card">
        <summary>
          <span class="row-card-heading"><strong><?= e($r['batch_no']) ?></strong> <span class="text-soft"><?= e($r['product_name']) ?> &middot; <?= e($r['size_kg']) ?> KG</span> <span class="pill pill-<?= $r['status']==='completed'?'completed':'active' ?>"><?= e(ucfirst($r['status'])) ?></span></span>
          <span class="row-chevron"><?= tabIcon('chevron') ?></span>
        </summary>
        <div class="row-card-detail">
        <div class="row-card-fields">
          <div><div class="field-label">Token</div><div class="field-value"><?= e($r['token_value']) ?></div></div>
          <div><div class="field-label">Bags (Qty)</div><div class="field-value"><?= $hasTarget ? (int)$r['target_bags'] : '<span class="text-soft">Not set</span>' ?></div></div>
          <div><div class="field-label">Completed</div><div class="field-value"><?= (int)$r['completed'] ?></div></div>
          <div><div class="field-label">Pending</div><div class="field-value"><?= $hasTarget ? $pending : '<span class="text-soft">-</span>' ?></div></div>
        </div>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
