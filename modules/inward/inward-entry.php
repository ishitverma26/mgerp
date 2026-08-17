<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/ledger.php';
requireRole(['Plant Head']);

// This plant only ever inwards one raw material, so it's fixed here instead
// of being asked on every entry. Change it in Masters -> Raw Materials.
$defaultMaterial = $pdo->query("SELECT id, name FROM raw_materials WHERE status='active' ORDER BY id LIMIT 1")->fetch();
$defaultMaterialId = $defaultMaterial ? (int) $defaultMaterial['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vendorId = (int) ($_POST['vendor_id'] ?? 0);
    $materialId = (int) $defaultMaterialId;
    $vehicleNo = clean($_POST['vehicle_no'] ?? '');
    $inwardDate = clean($_POST['inward_date'] ?? '');
    $grossWeight = $_POST['gross_weight'] ?? '';
    $jumboQty = $_POST['jumbo_qty'] ?? '';
    $slipNo = '';
    $remarks = clean($_POST['remarks'] ?? '');

    $errors = [];
    if ($vendorId <= 0) $errors[] = 'Select a vendor.';
    if ($materialId <= 0) $errors[] = 'No active raw material is configured. Add one in Masters -> Raw Materials.';
    if ($inwardDate === '') $errors[] = 'Enter the inward date.';
    if (!is_numeric($grossWeight) || $grossWeight <= 0) $errors[] = 'Enter a valid gross weight.';
    if (!ctype_digit((string) $jumboQty) || (int) $jumboQty <= 0) $errors[] = 'Enter a valid Jumbo quantity (whole number).';

    // Optional slip photo upload
    $slipPhotoPath = null;
    if (!empty($_FILES['slip_photo']['name'])) {
        $allowed = ['image/jpeg', 'image/png', 'application/pdf'];
        if ($_FILES['slip_photo']['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Slip file must be under 5MB.';
        } elseif (!in_array(mime_content_type($_FILES['slip_photo']['tmp_name']), $allowed, true)) {
            $errors[] = 'Slip file must be JPG, PNG or PDF.';
        }
    }

    if (!$errors) {
        // Server-side recalculation - never trust a client-supplied per-jumbo value
        $grossWeight = round((float) $grossWeight, 4);
        $jumboQty = (int) $jumboQty;
        $perJumboWeight = round($grossWeight / $jumboQty, 6);

        try {
            $pdo->beginTransaction();

            $lotNo = generateLotNo($pdo);

            if (!empty($_FILES['slip_photo']['name'])) {
                $ext = pathinfo($_FILES['slip_photo']['name'], PATHINFO_EXTENSION);
                $filename = strtolower($lotNo) . '-' . time() . '.' . $ext;
                move_uploaded_file($_FILES['slip_photo']['tmp_name'], UPLOAD_PATH . $filename);
                $slipPhotoPath = $filename;
            }

            $stmt = $pdo->prepare(
                "INSERT INTO raw_material_inward
                    (lot_no, vendor_id, raw_material_id, vehicle_no, inward_date, gross_weight,
                     jumbo_qty, per_jumbo_weight, weighbridge_slip_no, slip_photo, remarks, created_by)
                 VALUES
                    (:lot_no, :vendor_id, :material_id, :vehicle_no, :inward_date, :gross_weight,
                     :jumbo_qty, :per_jumbo_weight, :slip_no, :slip_photo, :remarks, :created_by)"
            );
            $stmt->execute([
                ':lot_no' => $lotNo, ':vendor_id' => $vendorId, ':material_id' => $materialId,
                ':vehicle_no' => $vehicleNo, ':inward_date' => $inwardDate, ':gross_weight' => $grossWeight,
                ':jumbo_qty' => $jumboQty, ':per_jumbo_weight' => $perJumboWeight, ':slip_no' => $slipNo,
                ':slip_photo' => $slipPhotoPath, ':remarks' => $remarks, ':created_by' => $currentUser['id'],
            ]);
            $inwardId = (int) $pdo->lastInsertId();

            // NOTE: each placeholder is used only once, even where the value is
            // repeated (e.g. total_jumbo/remaining_jumbo both start equal to the
            // full amount) - PDO with native prepared statements (emulation off)
            // does not reliably support reusing one named placeholder twice.
            $stmt = $pdo->prepare(
                "INSERT INTO raw_material_stock
                    (inward_id, lot_no, vendor_id, raw_material_id, total_jumbo, remaining_jumbo,
                     per_jumbo_weight, total_mt, remaining_mt, status)
                 VALUES
                    (:inward_id, :lot_no, :vendor_id, :material_id, :total_jumbo, :remaining_jumbo,
                     :per_jumbo_weight, :total_mt, :remaining_mt, 'active')"
            );
            $stmt->execute([
                ':inward_id' => $inwardId, ':lot_no' => $lotNo, ':vendor_id' => $vendorId,
                ':material_id' => $materialId, ':total_jumbo' => $jumboQty, ':remaining_jumbo' => $jumboQty,
                ':per_jumbo_weight' => $perJumboWeight, ':total_mt' => $grossWeight, ':remaining_mt' => $grossWeight,
            ]);

            logStockMovement(
                $pdo, 'inward', 'raw_material_inward', $inwardId, 'raw',
                $grossWeight, 'MT', 0, $grossWeight, $currentUser['id'],
                "Lot $lotNo inward - $jumboQty Jumbo"
            );

            logAudit($pdo, $currentUser['id'], 'create', 'raw_material_inward', $inwardId, null, [
                'lot_no' => $lotNo, 'gross_weight' => $grossWeight, 'jumbo_qty' => $jumboQty,
            ]);

            $pdo->commit();
            setFlash('success', "Inward saved. Lot number: $lotNo ($jumboQty Jumbo, " . number_format($perJumboWeight, 6) . " MT/Jumbo).");
            redirect('/modules/inward/inward-list.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('error', 'Could not save inward entry: ' . $e->getMessage());
        }
    } else {
        setFlash('error', implode(' ', $errors));
    }
}

$vendors = $pdo->query("SELECT * FROM vendors WHERE status='active' ORDER BY name")->fetchAll();

$pageTitle = 'New Raw Material Inward';
$activeMenu = 'inward-entry';
$hideTopbar = true;
$extraScripts = ['/assets/js/inward.js'];
$pageHeaderExtraIcon = ['icon' => 'clock', 'url' => '/modules/inward/inward-list.php', 'title' => 'History'];
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="card">
  <h3 class="mt-0">Raw Material Inward Entry</h3>
  <p class="help-text">Per-Jumbo weight is calculated automatically - do not calculate it by hand. Each entry creates its own Lot, which stays separate in stock even if another vendor's per-Jumbo weight is different.</p>

  <form method="post" action="" enctype="multipart/form-data">
    <div class="section-divider"><?= tabIcon('clock') ?> Trip Details</div>
    <div class="grid grid-2">
      <div class="nozzle-box nozzle-box-green">
        <div class="nozzle-box-head"><span class="icon-chip icon-chip-green"><?= tabIcon('clock') ?></span> Inward Date</div>
        <label>Date *</label>
        <input type="date" name="inward_date" required value="<?= date('Y-m-d') ?>">
      </div>
      <div class="nozzle-box nozzle-box-right">
        <div class="nozzle-box-head"><span class="icon-chip icon-chip-amber"><?= tabIcon('archive') ?></span> Raw Material</div>
        <label>Fixed</label>
        <input type="text" class="readonly-calc" readonly value="<?= $defaultMaterial ? e($defaultMaterial['name']) : 'Not configured' ?>">
      </div>
    </div>

    <div class="section-divider"><?= tabIcon('briefcase') ?> Vendor &amp; Material</div>
    <div class="grid grid-2">
      <div class="nozzle-box nozzle-box-left">
        <div class="nozzle-box-head"><span class="icon-chip icon-chip-blue"><?= tabIcon('briefcase') ?></span> Vendor</div>
        <label>Select *</label>
        <select name="vendor_id" required>
          <option value="">Select vendor</option>
          <?php foreach ($vendors as $v): ?>
            <option value="<?= $v['id'] ?>"><?= e($v['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="nozzle-box nozzle-box-purple">
        <div class="nozzle-box-head"><span class="icon-chip icon-chip-purple"><?= tabIcon('truck') ?></span> Vehicle No.</div>
        <label>Number</label>
        <input type="text" name="vehicle_no" placeholder="e.g. RJ14 GB 1234">
      </div>
    </div>

    <div class="section-divider"><?= tabIcon('archive') ?> Weight &amp; Jumbo</div>
    <div class="grid grid-2">
      <div class="nozzle-box nozzle-box-left">
        <div class="nozzle-box-head"><span class="icon-chip icon-chip-blue"><?= tabIcon('truck') ?></span> Gross Weight</div>
        <label>MT *</label>
        <input type="number" step="0.0001" min="0.0001" id="gross_weight" name="gross_weight" required>
      </div>
      <div class="nozzle-box nozzle-box-right">
        <div class="nozzle-box-head"><span class="icon-chip icon-chip-amber"><?= tabIcon('package') ?></span> Total Jumbo</div>
        <label>Qty *</label>
        <input type="number" step="1" min="1" id="jumbo_qty" name="jumbo_qty" required>
      </div>
    </div>
    <div class="section-divider"><?= tabIcon('hash') ?> Summary &amp; Slip</div>
    <div class="grid grid-2">
      <div class="nozzle-box nozzle-box-green">
        <div class="nozzle-box-head"><span class="icon-chip icon-chip-green"><?= tabIcon('hash') ?></span> Per Jumbo Wt.</div>
        <label>Auto (MT)</label>
        <input type="text" id="per_jumbo_preview" class="readonly-calc" readonly placeholder="Auto">
      </div>
      <div class="nozzle-box nozzle-box-purple">
        <div class="nozzle-box-head"><span class="icon-chip icon-chip-purple"><?= tabIcon('camera') ?></span> Slip / Photo</div>
        <div class="photo-capture" id="slipPhotoCapture">
          <input type="file" accept="image/*,.pdf" name="slip_photo" id="slipPhotoInput" style="display:none">
          <button type="button" class="btn btn-sm btn-tint-purple photo-capture-btn" id="slipPhotoBtn"><?= tabIcon('camera') ?> Attach</button>
          <div class="photo-preview" id="slipPhotoPreview" style="display:none;">
            <img id="slipPhotoImg" alt="Preview">
            <span id="slipPhotoName" class="text-soft" style="font-size:11px;"></span>
            <button type="button" class="btn btn-sm btn-outline" id="slipPhotoRetake">Change</button>
          </div>
        </div>
      </div>
    </div>
    <script>
    (function () {
      var input = document.getElementById('slipPhotoInput');
      var btn = document.getElementById('slipPhotoBtn');
      var preview = document.getElementById('slipPhotoPreview');
      var img = document.getElementById('slipPhotoImg');
      var nameLabel = document.getElementById('slipPhotoName');
      var retake = document.getElementById('slipPhotoRetake');
      if (!input) return;
      btn.addEventListener('click', function () { input.click(); });
      input.addEventListener('change', function () {
        var file = input.files[0];
        if (!file) return;
        if (file.type === 'application/pdf') {
          img.style.display = 'none';
          nameLabel.textContent = file.name;
        } else {
          var reader = new FileReader();
          reader.onload = function (e) { img.src = e.target.result; img.style.display = ''; };
          reader.readAsDataURL(file);
          nameLabel.textContent = '';
        }
        preview.style.display = 'flex';
        btn.style.display = 'none';
      });
      retake.addEventListener('click', function () {
        input.value = '';
        preview.style.display = 'none';
        btn.style.display = 'inline-flex';
      });
    })();
    </script>

    <label style="margin-top:16px;">Remarks</label>
    <textarea name="remarks" rows="2" placeholder="Optional notes about this delivery"></textarea>

    <button type="submit" class="btn btn-accent" style="margin-top:16px;width:100%;"><?= tabIcon('check-square') ?> Save Inward Entry</button>
  </form>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
