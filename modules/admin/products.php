<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
requireRole(['Admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'save') {
        $name = clean($_POST['name'] ?? '');
        $sizeKg = $_POST['size_kg'] ?? '';
        $tokenIds = array_map('intval', $_POST['token_ids'] ?? []);

        if ($name === '' || $sizeKg === '' || !is_numeric($sizeKg) || $sizeKg <= 0) {
            setFlash('error', 'Enter a product name and a valid size in KG.');
        } else {
            $new = ['name' => $name, 'size_kg' => $sizeKg];
            if ($id > 0) {
                $old = $pdo->prepare("SELECT * FROM products WHERE id=:id"); $old->execute([':id'=>$id]); $oldRow = $old->fetch();
                $pdo->prepare("UPDATE products SET name=:name, size_kg=:size_kg WHERE id=:id")->execute($new + [':id'=>$id]);
                logAudit($pdo, $currentUser['id'], 'update', 'products', $id, $oldRow, $new);
                $productId = $id;
                setFlash('success', 'Product updated.');
            } else {
                $pdo->prepare("INSERT INTO products (name, size_kg) VALUES (:name, :size_kg)")->execute($new);
                $productId = (int) $pdo->lastInsertId();
                logAudit($pdo, $currentUser['id'], 'create', 'products', $productId, null, $new);
                setFlash('success', 'Product added.');
            }

            $pdo->prepare("DELETE FROM product_tokens WHERE product_id=:id")->execute([':id' => $productId]);
            if ($tokenIds) {
                $stmt = $pdo->prepare("INSERT INTO product_tokens (product_id, token_id) VALUES (:p, :t)");
                foreach ($tokenIds as $tid) {
                    if ($tid > 0) $stmt->execute([':p' => $productId, ':t' => $tid]);
                }
            }
        }
    }
    if ($action === 'toggle') {
        $current = $pdo->prepare("SELECT status FROM products WHERE id=:id"); $current->execute([':id'=>$id]);
        $status = $current->fetchColumn(); $newStatus = $status === 'active' ? 'inactive' : 'active';
        $pdo->prepare("UPDATE products SET status=:s WHERE id=:id")->execute([':s'=>$newStatus, ':id'=>$id]);
        logAudit($pdo, $currentUser['id'], 'status_change', 'products', $id, ['status'=>$status], ['status'=>$newStatus]);
        setFlash('success', 'Status updated.');
    }

    if ($action === 'delete') {
        try {
            $old = $pdo->prepare("SELECT * FROM products WHERE id=:id"); $old->execute([':id'=>$id]); $oldRow = $old->fetch();
            $pdo->prepare("DELETE FROM products WHERE id=:id")->execute([':id'=>$id]);
            logAudit($pdo, $currentUser['id'], 'delete', 'products', $id, $oldRow, null);
            setFlash('success', 'Product deleted.');
        } catch (PDOException $e) {
            setFlash('error', 'Cannot delete this product - it is used in existing production batches. Deactivate it instead.');
        }
    }
    redirect('/modules/admin/products.php');
}

$editRow = null;
$editRowTokenIds = [];
if (!empty($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id=:id"); $stmt->execute([':id'=>(int)$_GET['edit']]); $editRow = $stmt->fetch();
    if ($editRow) {
        $tstmt = $pdo->prepare("SELECT token_id FROM product_tokens WHERE product_id=:id"); $tstmt->execute([':id' => $editRow['id']]);
        $editRowTokenIds = array_map('intval', $tstmt->fetchAll(PDO::FETCH_COLUMN));
    }
}
$rows = $pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll();
$tokens = $pdo->query("SELECT * FROM tokens WHERE status='active' ORDER BY token_value")->fetchAll();

$productTokenNames = [];
if ($rows) {
    $tstmt = $pdo->query(
        "SELECT pt.product_id, t.token_value FROM product_tokens pt JOIN tokens t ON t.id=pt.token_id ORDER BY t.token_value"
    );
    foreach ($tstmt->fetchAll() as $row) {
        $productTokenNames[$row['product_id']][] = $row['token_value'];
    }
}

$pageTitle = 'Products';
$activeMenu = 'm-products';
$hideTopbar = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="card">
  <h3 class="mt-0"><?= $editRow ? 'Edit Product' : 'Add Product' ?></h3>
  <p class="help-text">Each product is a full SKU - name and size together (e.g. "MG Cem" at 25 KG). Pick which tokens are valid for it below - a token can be shared across several products, and only its assigned tokens will show up when a batch is created for this product.</p>
  <form method="post" action="">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= e($editRow['id'] ?? '') ?>">
    <div class="form-row">
      <div><label>Product Name *</label><input type="text" name="name" required value="<?= e($editRow['name'] ?? '') ?>"></div>
      <div>
        <label>Size (KG) *</label>
        <input type="number" step="0.01" min="0.01" name="size_kg" id="sizeKgInput" required value="<?= e($editRow['size_kg'] ?? '') ?>">
        <p class="help-text">= <span id="sizeMtHint"><?= number_format((float) ($editRow['size_kg'] ?? 0) / 1000, 4) ?></span> MT per bag</p>
      </div>
    </div>
    <script>
    (function () {
      var input = document.getElementById('sizeKgInput');
      var hint = document.getElementById('sizeMtHint');
      if (!input || !hint) return;
      input.addEventListener('input', function () {
        var kg = parseFloat(input.value) || 0;
        hint.textContent = (kg / 1000).toFixed(4);
      });
    })();
    </script>

    <label>Allowed Tokens</label>
    <?php if (!$tokens): ?>
      <p class="help-text">No active tokens yet - add some in Masters &rarr; Tokens first.</p>
    <?php else: ?>
      <div class="form-row" style="flex-wrap:wrap;gap:8px;">
        <?php foreach ($tokens as $t): ?>
          <label style="display:flex;align-items:center;gap:6px;font-weight:400;margin:0;flex:0 0 auto;">
            <input type="checkbox" name="token_ids[]" value="<?= $t['id'] ?>" style="width:auto;" <?= in_array((int) $t['id'], $editRowTokenIds, true) ? 'checked' : '' ?>>
            <?= e($t['token_value']) ?>
          </label>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <button type="submit" class="btn btn-accent" style="margin-top:14px;"><?= $editRow ? 'Update' : 'Add' ?></button>
    <?php if ($editRow): ?><a href="<?= APP_URL ?>/modules/admin/products.php<?= $embedMode ? '?embed=1' : '' ?>" class="btn btn-outline">Cancel</a><?php endif; ?>
  </form>
</div>
<div class="card">
  <h3 class="mt-0"><span class="icon-chip icon-chip-amber" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('tag') ?></span>All Products</h3>
  <?php if (!$rows): ?>
    <p class="row-card-empty">No products yet.</p>
  <?php else: ?>
  <div class="row-list">
    <?php foreach ($rows as $r): ?>
      <details class="row-card <?= $r['status'] === 'active' ? 'row-card-good' : 'row-card-neutral' ?>">
        <summary>
          <span class="row-card-heading"><strong><?= e($r['name']) ?></strong> <span class="text-soft"><?= e($r['size_kg']) ?> KG</span> <span class="pill pill-<?= e($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span></span>
          <span class="row-chevron"><?= tabIcon('chevron') ?></span>
        </summary>
        <div class="row-card-detail">
        <div class="row-card-fields">
          <div>
            <div class="field-label">Bag Size</div>
            <div class="field-value"><?= e($r['size_kg']) ?> KG <span class="text-soft">(<?= number_format($r['size_kg'] / 1000, 4) ?> MT)</span></div>
          </div>
          <div>
            <div class="field-label">Allowed Tokens</div>
            <div class="field-value">
              <?php if (!empty($productTokenNames[$r['id']])): ?>
                <?php foreach ($productTokenNames[$r['id']] as $tv): ?><span class="pill pill-active"><?= e($tv) ?></span><?php endforeach; ?>
              <?php else: ?>
                <span class="text-soft">None assigned</span>
              <?php endif; ?>
            </div>
          </div>
          <div>
            <div class="field-label">Actions</div>
            <div class="row-card-actions">
              <a class="btn btn-sm btn-outline" href="?edit=<?= (int)$r['id'] ?><?= $embedMode ? '&embed=1' : '' ?>">Edit</a>
              <form method="post" style="display:inline" data-confirm="Change status?">
                <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline"><?= $r['status']==='active'?'Deactivate':'Activate' ?></button>
              </form>
              <form method="post" style="display:inline" data-confirm="Delete this product? This cannot be undone.">
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
              </form>
            </div>
          </div>
        </div>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
