<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/ledger.php';
requireRole(['Plant Head']);

function getLastSnapshot(PDO $pdo, int $batchId) {
    $stmt = $pdo->prepare("SELECT * FROM packing_production_updates WHERE batch_id=:id ORDER BY id DESC LIMIT 1");
    $stmt->execute([':id' => $batchId]);
    return $stmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_nozzle') {
    $batchId = (int) ($_POST['batch_id'] ?? 0);
    $nozzle = $_POST['nozzle'] ?? '';
    $resetTime = clean($_POST['reset_time'] ?? '');
    // Date is always today - only the time of day is ever asked for on this form.
    $resetDatetime = $resetTime !== '' ? date('Y-m-d') . ' ' . $resetTime : '';

    $stmt = $pdo->prepare("SELECT id FROM production_batches WHERE id=:id"); $stmt->execute([':id' => $batchId]); $batchExists = $stmt->fetchColumn();

    if (!$batchExists || !in_array($nozzle, ['left', 'right', 'both'], true) || $resetDatetime === '') {
        setFlash('error', 'Select a batch, nozzle and reset time.');
    } else {
        try {
            $photo = handlePhotoUpload('photo', 'reset-' . $batchId);
            $stmt = $pdo->prepare(
                "INSERT INTO nozzle_reset_log (batch_id, nozzle, reset_datetime, photo, user_id) VALUES (:batch_id, :nozzle, :dt, :photo, :user_id)"
            );
            $stmt->execute([':batch_id' => $batchId, ':nozzle' => $nozzle, ':dt' => $resetDatetime, ':photo' => $photo, ':user_id' => $currentUser['id']]);
            logAudit($pdo, $currentUser['id'], 'create', 'nozzle_reset_log', (int) $pdo->lastInsertId(), null, [
                'batch_id' => $batchId, 'nozzle' => $nozzle, 'reset_datetime' => $resetDatetime,
            ]);
            setFlash('success', ucfirst($nozzle) . ' nozzle reset logged at ' . formatDateTime($resetDatetime) . '.');
        } catch (InvalidArgumentException $e) {
            setFlash('error', $e->getMessage());
        }
    }
    redirect('/modules/packing/packing-update.php?batch_id=' . $batchId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $batchId = (int) ($_POST['batch_id'] ?? 0);
    $left = (int) ($_POST['left_nozzle_qty'] ?? -1);
    $right = (int) ($_POST['right_nozzle_qty'] ?? -1);
    $damage = (int) ($_POST['damage_qty_cumulative'] ?? 0);
    $updateTime = clean($_POST['update_time'] ?? '');
    // Date is always today - only the time of day is ever asked for on this form.
    $updateDatetime = $updateTime !== '' ? date('Y-m-d') . ' ' . $updateTime : '';

    if ($batchId <= 0) {
        // No Admin-created batch exists for this product/token - only ever
        // create one here, at the moment real production is actually being
        // saved, never just from picking a Bag/Token in the selector above
        // (that used to silently leave empty "batch" records behind just
        // from browsing). target_bags stays NULL (an already-supported "no
        // target" state - see buildBatchGroups()); everything logged
        // against it counts straight as Live Stock as it's logged, not
        // just once "completed" (see buildLiveStock()). Admin can still
        // give it a real target later from Batch Management.
        $productId = (int) ($_POST['product_id'] ?? 0);
        $tokenId = (int) ($_POST['token_id'] ?? 0);
        $existing = ($productId > 0 && $tokenId > 0) ? findActiveBatch($pdo, $productId, $tokenId) : 0;
        if ($existing > 0) {
            $batchId = $existing;
        } elseif ($productId > 0 && $tokenId > 0) {
            $pdo->beginTransaction();
            try {
                $batchNo = generateBatchNo($pdo);
                $pdo->prepare("INSERT INTO batch_groups (batch_no, created_by) VALUES (:no, :uid)")
                    ->execute([':no' => $batchNo, ':uid' => $currentUser['id']]);
                $groupId = (int) $pdo->lastInsertId();
                $pdo->prepare(
                    "INSERT INTO production_batches (batch_group_id, product_id, token_id, target_bags, created_by) VALUES (:g, :p, :t, NULL, :uid)"
                )->execute([':g' => $groupId, ':p' => $productId, ':t' => $tokenId, ':uid' => $currentUser['id']]);
                $batchId = (int) $pdo->lastInsertId();
                logAudit($pdo, $currentUser['id'], 'create', 'production_batches', $batchId, null, [
                    'batch_no' => $batchNo, 'product_id' => $productId, 'token_id' => $tokenId, 'target_bags' => null, 'auto_created' => true,
                ]);
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                setFlash('error', 'Could not start packing for this product/token: ' . $e->getMessage());
                redirect('/modules/packing/packing-update.php');
            }
        }
    }

    $stmt = $pdo->prepare(
        "SELECT b.*, g.batch_no FROM production_batches b JOIN batch_groups g ON g.id=b.batch_group_id WHERE b.id=:id"
    );
    $stmt->execute([':id' => $batchId]); $batch = $stmt->fetch();

    if (!$batch || !in_array($batch['status'], ['active', 'reopened'], true)) {
        setFlash('error', 'Select an active batch. Completed batches must be reopened by an Admin first.');
        redirect('/modules/packing/packing-update.php');
    }

    try {
        $photo = handlePhotoUpload('photo', 'entry-' . $batchId);
        $result = recordPackingProduction($pdo, $batch, $left, $right, $damage, $updateDatetime, $currentUser['id'], $photo);
        setFlash('success', 'Production entry saved.'
            . ($result['completed'] ? ' Target reached - batch marked COMPLETED.' : '')
            . ($result['rolled_over'] ? ' Switched to the next open batch for this product/token.' : ''));
        redirect('/modules/packing/packing-update.php?batch_id=' . $result['next_batch_id'] . '&nozzle_prompt=1');
    } catch (InvalidArgumentException $e) {
        setFlash('error', $e->getMessage());
        redirect('/modules/packing/packing-update.php?batch_id=' . $batchId);
    } catch (Exception $e) {
        setFlash('error', 'Could not save update: ' . $e->getMessage());
        redirect('/modules/packing/packing-update.php?batch_id=' . $batchId);
    }
}

$products = $pdo->query("SELECT * FROM products WHERE status='active' ORDER BY name")->fetchAll();
$tokens = $pdo->query("SELECT * FROM tokens WHERE status='active' ORDER BY token_value")->fetchAll();

// product_id => [token_id, ...] - which tokens are allowed for which product,
// used to filter the Token dropdown once a Product is picked.
$productTokenIds = [];
foreach ($pdo->query("SELECT product_id, token_id FROM product_tokens") as $row) {
    $productTokenIds[(int) $row['product_id']][] = (int) $row['token_id'];
}

$selectedBatchId = (int) ($_GET['batch_id'] ?? 0);
$pendingProductId = 0;
$pendingTokenId = 0;
if ($selectedBatchId <= 0 && !empty($_GET['product_id']) && !empty($_GET['token_id'])) {
    $productId = (int) $_GET['product_id'];
    $tokenId = (int) $_GET['token_id'];
    $found = findActiveBatch($pdo, $productId, $tokenId);
    if ($found > 0) {
        redirect('/modules/packing/packing-update.php?batch_id=' . $found);
    }
    // No Admin-created batch exists for this product/token yet - that's
    // fine, the Production Entry form below can still render against this
    // pending pair. Nothing is written to the database just from picking
    // it here though - the batch record only actually gets created once a
    // real entry is saved (see the POST handler above), so browsing the
    // Bag/Token selector never leaves empty "batch" records behind.
    $pendingProductId = $productId;
    $pendingTokenId = $tokenId;
}

function fetchBatchWithNames(PDO $pdo, int $id) {
    $stmt = $pdo->prepare(
        "SELECT b.*, g.batch_no, p.name AS product_name, p.size_kg, t.token_value
         FROM production_batches b
         JOIN batch_groups g ON g.id = b.batch_group_id
         JOIN products p ON p.id = b.product_id
         JOIN tokens t ON t.id = b.token_id
         WHERE b.id = :id"
    );
    $stmt->execute([':id' => $id]);
    return $stmt->fetch() ?: null;
}

$selectedBatch = $selectedBatchId > 0 ? fetchBatchWithNames($pdo, $selectedBatchId) : null;
if (!$selectedBatch && $pendingProductId > 0) {
    // Not a real batch yet - just picked in the selector above. Build a
    // stand-in row (id 0) so the Production Entry form below can render
    // against it; nothing exists in the database until that form is saved.
    $stmt = $pdo->prepare("SELECT name AS product_name, size_kg FROM products WHERE id=:id");
    $stmt->execute([':id' => $pendingProductId]);
    $productRow = $stmt->fetch();
    $stmt = $pdo->prepare("SELECT token_value FROM tokens WHERE id=:id");
    $stmt->execute([':id' => $pendingTokenId]);
    $tokenRow = $stmt->fetch();
    if ($productRow && $tokenRow) {
        $selectedBatch = [
            'id' => 0, 'product_id' => $pendingProductId, 'token_id' => $pendingTokenId,
            'target_bags' => null, 'status' => 'active',
            'product_name' => $productRow['product_name'], 'size_kg' => $productRow['size_kg'],
            'token_value' => $tokenRow['token_value'],
        ];
    }
}
if (!$selectedBatch && $pendingProductId <= 0) {
    $stmt = $pdo->query(
        "SELECT b.*, g.batch_no, p.name AS product_name, p.size_kg, t.token_value
         FROM production_batches b
         JOIN batch_groups g ON g.id = b.batch_group_id
         JOIN products p ON p.id = b.product_id
         JOIN tokens t ON t.id = b.token_id
         WHERE b.status IN ('active','reopened')
         ORDER BY b.id DESC LIMIT 1"
    );
    $selectedBatch = $stmt->fetch() ?: null;
}
if ($selectedBatch) $selectedBatchId = (int) $selectedBatch['id'];

$lastSnapshot = null;
$history = [];
if ($selectedBatch) {
    $lastSnapshot = getLastSnapshot($pdo, $selectedBatchId);
    // Today's activity across every SKU/batch being packed - not just this
    // one - so Plant Head can see the whole day's picture in one place.
    $histStmt = $pdo->query(
        "SELECT pu.*, g.batch_no, p.name AS product_name, p.size_kg, t.token_value, u.name AS user_name
         FROM packing_production_updates pu
         JOIN production_batches b ON b.id = pu.batch_id
         JOIN batch_groups g ON g.id = b.batch_group_id
         JOIN products p ON p.id = b.product_id
         JOIN tokens t ON t.id = b.token_id
         JOIN users u ON u.id = pu.user_id
         WHERE DATE(pu.update_datetime) = CURDATE()
         ORDER BY pu.id DESC"
    );
    $history = $histStmt->fetchAll();
}

$todayBagsPacked = array_sum(array_column($history, 'increase_since_last'));
$todayMtPacked = array_sum(array_map(fn($h) => $h['increase_since_last'] * $h['size_kg'] / 1000, $history));

$pageTitle = 'Packing Production Update';
$activeMenu = 'packing-update';
$hideTopbar = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<script>window.__productTokenIds = <?= json_encode($productTokenIds) ?>;</script>
<div class="live-clock-bar">
  <span class="live-clock-icon"><?= tabIcon('clock') ?></span>
  <span id="liveClockText">&nbsp;</span>
</div>
<script>
(function () {
  var clockEl = document.getElementById('liveClockText');
  var liveInputs = document.querySelectorAll('.live-datetime');
  var touched = {};
  liveInputs.forEach(function (inp) {
    inp.addEventListener('input', function () { touched[inp.name] = true; });
  });
  function pad(n) { return n < 10 ? '0' + n : n; }
  function toLocalTimeValue(d) {
    return pad(d.getHours()) + ':' + pad(d.getMinutes());
  }
  function tick() {
    var now = new Date();
    if (clockEl) {
      clockEl.textContent = now.toLocaleDateString('en-IN', { weekday: 'short', day: '2-digit', month: 'short' })
        + '  ·  ' + now.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }
    liveInputs.forEach(function (inp) {
      if (!touched[inp.name]) inp.value = toLocalTimeValue(now);
    });
  }
  tick();
  setInterval(tick, 1000);
})();
</script>
<div class="card">
  <h3 class="mt-0"><span class="icon-chip icon-chip-purple" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('package') ?></span>Packing Update</h3>
  <?php if (!$products): ?>
    <p class="text-soft">No Products configured yet. Ask an Admin to add them in Masters.</p>
  <?php elseif (!$tokens): ?>
    <p class="text-soft">No Tokens configured yet. Ask an Admin to add them in Masters.</p>
  <?php else: ?>
    <div class="packing-selector-strip">
      <form method="get" action="" id="selectPackingForm">
        <div class="form-row">
          <div>
            <label>Bag (Product) *</label>
            <select name="product_id" id="productSelect" required>
              <?php foreach ($products as $p): ?>
                <option value="<?= $p['id'] ?>" <?= ($selectedBatch && (int) $selectedBatch['product_id'] === (int) $p['id']) ? 'selected' : '' ?>><?= e($p['name']) ?> &middot; <?= e($p['size_kg']) ?> KG</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Token *</label>
            <select name="token_id" id="tokenSelect" required>
              <?php foreach ($tokens as $t): ?>
                <option value="<?= $t['id'] ?>" <?= ($selectedBatch && (int) $selectedBatch['token_id'] === (int) $t['id']) ? 'selected' : '' ?>><?= e($t['token_value']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </form>
    </div>
    <script>
    (function () {
      var allTokens = <?= json_encode(array_map(fn($t) => ['id' => (int) $t['id'], 'value' => $t['token_value']], $tokens)) ?>;
      var productTokenIds = window.__productTokenIds || {};
      var form = document.getElementById('selectPackingForm');
      var productSelect = document.getElementById('productSelect');
      var tokenSelect = document.getElementById('tokenSelect');
      if (!productSelect || !tokenSelect) return;

      function refreshTokens(preserveValue) {
        var allowed = productTokenIds[productSelect.value] || allTokens.map(function (t) { return t.id; });
        var current = preserveValue ? tokenSelect.value : null;
        tokenSelect.innerHTML = '';
        allTokens.forEach(function (t) {
          if (allowed.indexOf(t.id) === -1) return;
          var opt = document.createElement('option');
          opt.value = t.id;
          opt.textContent = t.value;
          if (String(t.id) === current) opt.selected = true;
          tokenSelect.appendChild(opt);
        });
      }

      // Selecting a bag/token starts (or switches to) that batch right away -
      // no separate Start/Switch button to tap.
      productSelect.addEventListener('change', function () { refreshTokens(false); form.submit(); });
      tokenSelect.addEventListener('change', function () { form.submit(); });
      refreshTokens(true);
    })();
    </script>
    <?php if ($selectedBatch && $selectedBatch['target_bags'] === null): ?>
      <p class="help-text"><em>Admin hasn't set a target for this batch yet - keep logging, it'll reconcile automatically once they do.</em></p>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($selectedBatch): ?>
  <p class="help-text">Enter what you're packing right now, as a cumulative total (not today's increment).</p>
  <form method="post" action="" enctype="multipart/form-data">
    <input type="hidden" name="batch_id" value="<?= (int) $selectedBatch['id'] ?>">
    <input type="hidden" name="product_id" value="<?= (int) $selectedBatch['product_id'] ?>">
    <input type="hidden" name="token_id" value="<?= (int) $selectedBatch['token_id'] ?>">
    <div class="grid grid-2">
      <div class="nozzle-box nozzle-box-left">
        <div class="nozzle-box-head"><span class="icon-chip icon-chip-blue"><?= tabIcon('arrow-left') ?></span> Left Nozzle</div>
        <label>Cumulative Qty *</label>
        <input type="number" min="0" name="left_nozzle_qty" required value="<?= $lastSnapshot ? (int) $lastSnapshot['left_nozzle_qty'] : '' ?>">
      </div>
      <div class="nozzle-box nozzle-box-right">
        <div class="nozzle-box-head"><span class="icon-chip icon-chip-amber"><?= tabIcon('arrow-right') ?></span> Right Nozzle</div>
        <label>Cumulative Qty *</label>
        <input type="number" min="0" name="right_nozzle_qty" required value="<?= $lastSnapshot ? (int) $lastSnapshot['right_nozzle_qty'] : '' ?>">
      </div>
    </div>
    <div class="form-row filter-form" style="margin-top:14px;">
      <div><label>Damage (cumulative)</label><input type="number" min="0" name="damage_qty_cumulative" value="<?= $lastSnapshot ? (int) $lastSnapshot['damage_qty_cumulative'] : 0 ?>"></div>
      <div><label>Time *</label><input type="time" name="update_time" class="live-datetime" required value="<?= date('H:i') ?>"></div>
    </div>

    <div class="photo-capture" id="entryPhotoCapture" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
      <input type="file" accept="image/*" capture="environment" name="photo" id="entryPhotoInput" style="display:none">
      <button type="button" class="btn btn-outline photo-capture-btn" id="entryPhotoBtn"><?= tabIcon('camera') ?> Take Photo</button>
      <div class="photo-preview" id="entryPhotoPreview" style="display:none;">
        <img id="entryPhotoImg" alt="Preview">
        <button type="button" class="btn btn-sm btn-outline" id="entryPhotoRetake">Retake</button>
      </div>
      <button type="submit" class="btn btn-accent">Save Entry</button>
    </div>
  </form>
  <script>
  (function () {
    var input = document.getElementById('entryPhotoInput');
    var btn = document.getElementById('entryPhotoBtn');
    var preview = document.getElementById('entryPhotoPreview');
    var img = document.getElementById('entryPhotoImg');
    var retake = document.getElementById('entryPhotoRetake');
    if (!input) return;
    btn.addEventListener('click', function () { input.click(); });
    input.addEventListener('change', function () {
      var file = input.files[0];
      if (!file) return;
      var reader = new FileReader();
      reader.onload = function (e) {
        img.src = e.target.result;
        preview.style.display = 'flex';
        btn.style.display = 'none';
      };
      reader.readAsDataURL(file);
    });
    retake.addEventListener('click', function () {
      input.value = '';
      preview.style.display = 'none';
      btn.style.display = 'inline-flex';
    });
  })();
  </script>
  <?php endif; ?>
</div>

<?php if (!empty($_GET['nozzle_prompt']) && $selectedBatch): ?>
<div class="modal-overlay" id="nozzleResetModal">
  <div class="modal-box" style="text-align:left;">
    <div class="modal-box-head">
      <span class="icon-chip icon-chip-lg icon-chip-amber"><?= tabIcon('rotate-ccw') ?></span>
      <h3>Reset Nozzle to Zero</h3>
    </div>
    <p>Entry saved. Physically reset both nozzle counters to 0 on the machine, then confirm below to continue.</p>
    <form method="post" action="">
      <input type="hidden" name="action" value="reset_nozzle">
      <input type="hidden" name="batch_id" value="<?= (int) $selectedBatch['id'] ?>">
      <input type="hidden" name="nozzle" value="both">
      <label>Time *</label>
      <input type="time" name="reset_time" class="live-datetime" required value="<?= date('H:i') ?>">
      <button type="submit" class="btn btn-accent" style="margin-top:14px;">Confirm Both Reset to 0</button>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if ($selectedBatch): ?>
<div class="card card-accent-purple">
  <div class="flex-between" style="margin-bottom:14px;">
    <h3 class="mt-0"><span class="icon-chip icon-chip-purple" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('chart') ?></span>Today's Production - All SKUs</h3>
    <a href="<?= APP_URL ?>/modules/reports/packing-report.php" class="btn btn-sm btn-highlight-purple">Full History &rarr;</a>
  </div>
  <?php if (!$history): ?>
    <p class="row-card-empty">No production logged yet today.</p>
  <?php else: ?>
  <div class="activity-card-row" style="margin-bottom:16px;">
    <div class="activity-card">
      <div class="activity-card-top">
        <span class="activity-card-icon activity-card-icon-green"><?= tabIcon('package') ?></span>
        <span class="activity-card-label">Bags Packed Today</span>
        <span class="activity-card-view"><?= tabIcon('eye') ?></span>
      </div>
      <div class="activity-card-value"><?= number_format($todayBagsPacked) ?></div>
    </div>
    <div class="activity-card">
      <div class="activity-card-top">
        <span class="activity-card-icon activity-card-icon-purple"><?= tabIcon('archive') ?></span>
        <span class="activity-card-label">MT Packed Today</span>
        <span class="activity-card-view"><?= tabIcon('eye') ?></span>
      </div>
      <div class="activity-card-value"><?= number_format($todayMtPacked, 2) ?></div>
    </div>
  </div>
  <div class="row-list">
    <?php foreach ($history as $h): ?>
      <details class="row-card row-card-info">
        <summary>
          <span class="row-card-heading"><strong><?= e($h['product_name']) ?> <?= e($h['size_kg']) ?>KG</strong> <span class="text-soft">Token <?= e($h['token_value']) ?> &middot; <?= date('h:i A', strtotime($h['update_datetime'])) ?></span></span>
          <span class="row-chevron"><?= tabIcon('chevron') ?></span>
        </summary>
        <div class="row-card-detail">
        <div class="row-card-fields">
          <div><div class="field-label">Left</div><div class="field-value"><?= (int) $h['left_nozzle_qty'] ?></div></div>
          <div><div class="field-label">Right</div><div class="field-value"><?= (int) $h['right_nozzle_qty'] ?></div></div>
          <div><div class="field-label">Total Good</div><div class="field-value"><?= (int) $h['total_good_qty'] ?></div></div>
          <div><div class="field-label">Metric Tons</div><div class="field-value"><?= number_format($h['total_good_qty'] * $h['size_kg'] / 1000, 3) ?></div></div>
          <div><div class="field-label">Damage</div><div class="field-value"><?= (int) $h['damage_qty_cumulative'] ?></div></div>
          <div><div class="field-label">By</div><div class="field-value"><?= e($h['user_name']) ?></div></div>
          <?php if (!empty($h['photo'])): ?>
          <div><div class="field-label">Photo</div><div class="field-value"><a href="<?= UPLOAD_URL ?><?= e($h['photo']) ?>" target="_blank"><img src="<?= UPLOAD_URL ?><?= e($h['photo']) ?>" alt="Production photo" style="width:60px;height:60px;object-fit:cover;border-radius:8px;"></a></div></div>
          <?php endif; ?>
        </div>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
