<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/ledger.php';
require_once __DIR__ . '/../../classes/FifoProcessor.php';
requireRole(['Plant Head']);

// This plant only ever processes one raw material, so it's fixed here
// instead of being asked on every entry - same as the Inward form.
$defaultMaterial = $pdo->query("SELECT id, name FROM raw_materials WHERE status='active' ORDER BY id LIMIT 1")->fetch();
$defaultMaterialId = $defaultMaterial ? (int) $defaultMaterial['id'] : 0;

$preview = null;
$formMode = 'fifo';
$formJumbo = '';
$formDate = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formMode = ($_POST['mode'] ?? 'fifo') === 'lifo' ? 'lifo' : 'fifo';
    $processingDate = clean($_POST['processing_date'] ?? date('Y-m-d'));
    $formDate = $processingDate;

    if ($defaultMaterialId <= 0) {
        setFlash('error', 'No active raw material is configured. Add one in Masters -> Raw Materials.');
    } elseif ($formMode === 'lifo') {
        try {
            $processingId = FifoProcessor::processLcfo($pdo, $defaultMaterialId, $processingDate, $currentUser['id']);
            logAudit($pdo, $currentUser['id'], 'create', 'processing_requests', $processingId, null, [
                'raw_material_id' => $defaultMaterialId, 'mode' => 'lifo',
            ]);
            setFlash('success', "Processing #$processingId completed - entire last-arrived lot consumed via LIFO.");
            redirect('/modules/processing/processing-list.php');
        } catch (Exception $e) {
            setFlash('error', $e->getMessage());
        }
    } else {
        $action = $_POST['action'] ?? '';
        $requirementJumbo = (int) ($_POST['requirement_jumbo'] ?? 0);
        $formJumbo = $requirementJumbo;

        if ($requirementJumbo <= 0) {
            setFlash('error', 'Enter a valid Jumbo requirement.');
        } else {
            if ($action === 'preview') {
                $preview = FifoProcessor::preview($pdo, $defaultMaterialId, $requirementJumbo);
            }

            if ($action === 'confirm') {
                try {
                    $processingId = FifoProcessor::process($pdo, $defaultMaterialId, $requirementJumbo, $processingDate, $currentUser['id']);
                    logAudit($pdo, $currentUser['id'], 'create', 'processing_requests', $processingId, null, [
                        'raw_material_id' => $defaultMaterialId, 'requirement_jumbo' => $requirementJumbo, 'mode' => 'fifo',
                    ]);
                    setFlash('success', "Processing #$processingId completed - $requirementJumbo Jumbo consumed via FIFO.");
                    redirect('/modules/processing/processing-list.php');
                } catch (Exception $e) {
                    setFlash('error', $e->getMessage());
                }
            }
        }
    }
}

$lifoPreview = $defaultMaterialId > 0 ? FifoProcessor::previewLcfo($pdo, $defaultMaterialId) : null;
$lifoLot = $lifoPreview ? $lifoPreview['allocations'][0] : null;
$isLifo = $formMode === 'lifo';

if ($isLifo) {
    $formJumbo = $lifoLot ? $lifoLot['jumbo_taken'] : '';
}

$pageTitle = 'New Processing';
$activeMenu = 'processing-entry';
$hideTopbar = true;
$extraScripts = ['/assets/js/processing.js'];
$pageHeaderExtraIcon = ['icon' => 'clock', 'url' => '/modules/processing/processing-list.php', 'title' => 'History'];
require_once __DIR__ . '/../../includes/header.php';
?>
<script>
window.__lifoInfo = <?= $lifoLot ? json_encode([
    'jumbo' => (int) $lifoLot['jumbo_taken'],
    'lotNo' => $lifoLot['lot_no'],
    'mt'    => number_format($lifoLot['mt_taken'], 6),
]) : 'null' ?>;
</script>
<div class="card">
  <h3 class="mt-0">Processing Requirement</h3>
  <p class="help-text">
    Raw material: <strong><?= $defaultMaterial ? e($defaultMaterial['name']) : 'Not configured' ?></strong>.
    FIFO consumes the oldest lot(s) first for a Jumbo amount you choose. LIFO consumes the entire most-recently-arrived lot in one click.
  </p>
  <form method="post" action="" id="processingForm">
    <input type="hidden" name="mode" id="modeInput" value="<?= $isLifo ? 'lifo' : 'fifo' ?>">
    <input type="hidden" name="action" id="formAction" value="<?= $isLifo ? 'process' : 'preview' ?>">

    <div class="section-divider"><?= tabIcon('refresh') ?> Processing Method</div>
    <div class="segmented">
      <button type="button" class="segmented-btn<?= $isLifo ? '' : ' active' ?>" id="fifoBtn">FIFO</button>
      <button type="button" class="segmented-btn<?= $isLifo ? ' active' : '' ?>" id="lifoBtn">LIFO</button>
    </div>

    <div class="section-divider"><?= tabIcon('package') ?> Requirement &amp; Date</div>
    <div class="grid grid-2">
      <div class="nozzle-box nozzle-box-left">
        <div class="nozzle-box-head"><span class="icon-chip icon-chip-blue"><?= tabIcon('package') ?></span> Requirement</div>
        <label>Jumbo *</label>
        <input type="number" step="1" min="1" name="requirement_jumbo" id="jumboInput"
          class="<?= $isLifo ? 'readonly-calc' : '' ?>" <?= $isLifo ? 'readonly' : 'required' ?>
          value="<?= e($formJumbo !== '' ? $formJumbo : '') ?>">
      </div>
      <div class="nozzle-box nozzle-box-purple">
        <div class="nozzle-box-head"><span class="icon-chip icon-chip-purple"><?= tabIcon('clock') ?></span> Processing Date</div>
        <label>Date *</label>
        <input type="date" name="processing_date" required value="<?= e($formDate) ?>">
      </div>
    </div>
    <p class="help-text" id="jumboHelp">
      <?php if ($isLifo && $lifoLot): ?>
        Auto-filled from the last-arrived lot: <strong>Lot <?= e($lifoLot['lot_no']) ?></strong> (<?= number_format($lifoLot['mt_taken'], 6) ?> MT)
      <?php elseif ($isLifo): ?>
        No active stock available to process right now.
      <?php endif; ?>
    </p>

    <button type="submit" class="btn <?= $isLifo ? 'btn-accent' : 'btn-outline' ?>" id="submitBtn" style="margin-top:12px;width:100%;"><?= $isLifo ? 'Process Last Lot (LIFO)' : 'Preview FIFO Allocation' ?></button>
  </form>
</div>

<?php if ($preview): ?>
<div class="card">
  <h3 class="mt-0">Allocation Preview - <?= e($defaultMaterial['name']) ?></h3>
  <?php if ($preview['shortfall'] > 0): ?>
    <div class="alert alert-danger">Not enough active stock - short by <?= $preview['shortfall'] ?> Jumbo. Add more inward stock or reduce the requirement.</div>
  <?php else: ?>
    <div class="row-list">
      <?php $totalMt = 0; foreach ($preview['allocations'] as $a): $totalMt += $a['mt_taken']; ?>
        <div class="row-card row-card-info">
          <div class="row-card-fields">
            <div><div class="field-label">Lot (oldest first)</div><div class="field-value"><?= e($a['lot_no']) ?></div></div>
            <div><div class="field-label">Jumbo Taken</div><div class="field-value"><?= (int) $a['jumbo_taken'] ?></div></div>
            <div><div class="field-label">Per Jumbo (MT)</div><div class="field-value"><?= number_format($a['per_jumbo_weight'], 6) ?></div></div>
            <div><div class="field-label">MT Consumed</div><div class="field-value"><?= number_format($a['mt_taken'], 6) ?></div></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="alert alert-info" style="margin-top:14px;margin-bottom:0;text-align:right;padding-right:16px;">Total MT to be consumed: <strong><?= number_format($totalMt, 6) ?></strong></div>
    <form method="post" action="" style="margin-top:14px;">
      <input type="hidden" name="mode" value="fifo">
      <input type="hidden" name="action" value="confirm">
      <input type="hidden" name="requirement_jumbo" value="<?= e($formJumbo) ?>">
      <input type="hidden" name="processing_date" value="<?= e($formDate) ?>">
      <button type="submit" class="btn btn-accent">Confirm &amp; Deduct Stock</button>
    </form>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
