<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
requireRole(['Admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $items = $_POST['items'] ?? [];
    $validItems = [];
    foreach ($items as $item) {
        $productId = (int) ($item['product_id'] ?? 0);
        $tokenId = (int) ($item['token_id'] ?? 0);
        $target = (int) ($item['target_bags'] ?? 0);
        if ($productId > 0 && $tokenId > 0 && $target > 0) {
            $validItems[] = ['product_id' => $productId, 'token_id' => $tokenId, 'target_bags' => $target];
        }
    }

    $batchNo = clean($_POST['batch_no'] ?? '');
    if ($batchNo === '') {
        $batchNo = generateBatchNo($pdo);
    }

    $dupCheck = $pdo->prepare("SELECT id FROM batch_groups WHERE batch_no=:bn");
    $dupCheck->execute([':bn' => $batchNo]);

    if (!$validItems) {
        setFlash('error', 'Add at least one SKU with product, token and a valid target.');
    } elseif ($dupCheck->fetch()) {
        setFlash('error', "Batch No \"$batchNo\" is already in use - pick a different one.");
    } else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO batch_groups (batch_no, created_by) VALUES (:batch_no, :created_by)")
                ->execute([':batch_no' => $batchNo, ':created_by' => $currentUser['id']]);
            $batchGroupId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare(
                "INSERT INTO production_batches (batch_group_id, product_id, token_id, target_bags, created_by)
                 VALUES (:batch_group_id, :product_id, :token_id, :target, :created_by)"
            );
            foreach ($validItems as $item) {
                $stmt->execute([
                    ':batch_group_id' => $batchGroupId, ':product_id' => $item['product_id'],
                    ':token_id' => $item['token_id'], ':target' => $item['target_bags'],
                    ':created_by' => $currentUser['id'],
                ]);
                $newItemId = (int) $pdo->lastInsertId();
                logAudit($pdo, $currentUser['id'], 'create', 'production_batches', $newItemId, null, [
                    'batch_no' => $batchNo, 'product_id' => $item['product_id'],
                    'token_id' => $item['token_id'], 'target_bags' => $item['target_bags'],
                ]);
                // Auto-fill this SKU from any Live Stock already sitting around
                // for the same product+token before asking Plant Head to pack
                // it fresh (see applyLiveStockCarryForward()).
                applyLiveStockCarryForward($pdo, $newItemId, $item['product_id'], $item['token_id'], $item['target_bags'], $currentUser['id']);
            }
            $pdo->commit();
            $count = count($validItems);
            setFlash('success', "Batch $batchNo created with $count SKU" . ($count > 1 ? 's' : '') . '.');
            redirect('/modules/packing/batch-list.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('error', 'Could not create batch: ' . $e->getMessage());
        }
    }
}

$products = $pdo->query("SELECT * FROM products WHERE status='active' ORDER BY name")->fetchAll();
$tokens = $pdo->query("SELECT * FROM tokens WHERE status='active' ORDER BY token_value")->fetchAll();
$nextBatchNo = generateBatchNo($pdo);

// product_id => [token_id, ...] - which tokens are allowed for which product.
// A product with no assignment yet falls back to showing every active token.
$productTokenIds = [];
foreach ($pdo->query("SELECT product_id, token_id FROM product_tokens") as $row) {
    $productTokenIds[(int) $row['product_id']][] = (int) $row['token_id'];
}

$pageTitle = 'New Production Batch';
$activeMenu = 'batch-create';
$hideTopbar = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<script>window.__productTokenIds = <?= json_encode($productTokenIds) ?>;</script>

<div class="card">
  <h3 class="mt-0"><span class="icon-chip icon-chip-amber" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('plus') ?></span>Create Production Batch</h3>
  <p class="help-text">A batch can hold multiple SKUs - add as many as this batch actually covers.</p>
  <form method="post" action="" id="createBatchForm">
    <details class="mini-collapse">
      <summary><?= tabIcon('hash') ?> Batch Number <span class="mini-collapse-chevron"><?= tabIcon('chevron') ?></span></summary>
      <div class="packing-selector-strip" style="text-align:center;margin-top:10px;">
        <div style="max-width:220px;margin:0 auto;">
          <label>Batch No *</label>
          <input type="text" name="batch_no" value="<?= e($nextBatchNo) ?>" required>
          <p class="help-text">Auto-suggested (next in sequence) - edit if you need to skip ahead or match an existing number.</p>
        </div>
      </div>
    </details>

    <div class="section-divider"><?= tabIcon('package') ?> SKUs in this Batch</div>
    <div id="itemsContainer"></div>
    <button type="button" id="addItemBtn" class="btn btn-tint-purple" style="margin-top:4px;width:100%;">+ Add Another SKU</button>
    <button type="submit" class="btn btn-accent" style="margin-top:14px;width:100%;">Create Batch</button>
  </form>
</div>

<template id="itemRowTemplate">
  <div class="card item-row sku-row-card" style="margin-bottom:12px;">
    <div class="sku-row-head">
      <span class="icon-chip icon-chip-purple"><?= tabIcon('package') ?></span>
      <strong>SKU <span class="sku-row-number">1</span></strong>
    </div>
    <div class="form-row filter-form" style="align-items:flex-end;">
      <div>
        <label>Product (SKU) *</label>
        <select class="item-product" required>
          <option value="">Select product</option>
          <?php foreach ($products as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['name']) ?> &middot; <?= e($p['size_kg']) ?> KG</option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Token *</label>
        <select class="item-token" required>
          <option value="">Select product first</option>
        </select>
      </div>
      <div>
        <label>Bags (Qty) *</label>
        <input type="number" step="1" min="1" class="item-target" required>
      </div>
      <div><button type="button" class="btn btn-sm btn-danger remove-item-btn">Remove</button></div>
    </div>
  </div>
</template>

<script>
(function () {
  var allTokens = <?= json_encode(array_map(fn($t) => ['id' => (int) $t['id'], 'value' => $t['token_value']], $tokens)) ?>;
  var productTokenIds = window.__productTokenIds || {};
  var container = document.getElementById('itemsContainer');
  var template = document.getElementById('itemRowTemplate');
  var addBtn = document.getElementById('addItemBtn');
  var rowIndex = 0;

  function tokensFor(productId) {
    return productId && productTokenIds[productId] ? productTokenIds[productId] : allTokens.map(function (t) { return t.id; });
  }

  function wireRow(row) {
    var productSelect = row.querySelector('.item-product');
    var tokenSelect = row.querySelector('.item-token');
    var removeBtn = row.querySelector('.remove-item-btn');

    productSelect.addEventListener('change', function () {
      var allowed = tokensFor(productSelect.value);
      tokenSelect.innerHTML = '<option value="">Select token</option>';
      allTokens.forEach(function (t) {
        if (allowed.indexOf(t.id) === -1) return;
        var opt = document.createElement('option');
        opt.value = t.id;
        opt.textContent = t.value;
        tokenSelect.appendChild(opt);
      });
    });

    removeBtn.addEventListener('click', function () {
      if (container.querySelectorAll('.item-row').length <= 1) return;
      row.remove();
      renumber();
    });
  }

  function renumber() {
    container.querySelectorAll('.item-row').forEach(function (row, i) {
      var label = row.querySelector('.sku-row-number');
      if (label) label.textContent = i + 1;
    });
  }

  function addRow() {
    var clone = template.content.cloneNode(true);
    var row = clone.querySelector('.item-row');
    var idx = rowIndex++;
    row.querySelector('.item-product').setAttribute('name', 'items[' + idx + '][product_id]');
    row.querySelector('.item-token').setAttribute('name', 'items[' + idx + '][token_id]');
    row.querySelector('.item-target').setAttribute('name', 'items[' + idx + '][target_bags]');
    wireRow(row);
    container.appendChild(row);
    renumber();
  }

  addBtn.addEventListener('click', addRow);
  addRow();
})();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
