<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
requireRole(['Admin', 'Plant Head']);

$isAdmin = $currentUser['role_name'] === 'Admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---------- Daily meter reading (both roles) ----------
    if ($action === 'log_reading') {
        // Plant Head can only ever log TODAY's own reading - same "date is
        // always today" convention as the rest of the app's Plant Head
        // entry forms. Admin edits an existing date via 'edit_reading'
        // below instead.
        $date = date('Y-m-d');
        $morning = clean($_POST['morning_reading'] ?? '');
        $evening = clean($_POST['evening_reading'] ?? '');

        if ($morning === '' && $evening === '') {
            setFlash('error', 'Enter at least one reading.');
        } elseif ($evening !== '' && $morning !== '' && (float) $evening < (float) $morning) {
            setFlash('error', 'Evening reading cannot be lower than the morning reading.');
        } else {
            $stmt = $pdo->prepare("SELECT * FROM electricity_readings WHERE reading_date=:d");
            $stmt->execute([':d' => $date]);
            $existing = $stmt->fetch();

            // Upsert on today's date - filling in whichever field is
            // provided without blanking out one already logged earlier
            // today (e.g. morning logged at 9am, evening added at 6pm).
            $newMorning = $morning !== '' ? $morning : ($existing['morning_reading'] ?? null);
            $newEvening = $evening !== '' ? $evening : ($existing['evening_reading'] ?? null);

            if ($existing) {
                $pdo->prepare("UPDATE electricity_readings SET morning_reading=:m, evening_reading=:e, updated_at=NOW() WHERE id=:id")
                    ->execute([':m' => $newMorning, ':e' => $newEvening, ':id' => $existing['id']]);
                logAudit($pdo, $currentUser['id'], 'update', 'electricity_readings', $existing['id'], $existing, [
                    'morning_reading' => $newMorning, 'evening_reading' => $newEvening,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO electricity_readings (reading_date, morning_reading, evening_reading, created_by) VALUES (:d, :m, :e, :uid)"
                );
                $stmt->execute([':d' => $date, ':m' => $newMorning, ':e' => $newEvening, ':uid' => $currentUser['id']]);
                logAudit($pdo, $currentUser['id'], 'create', 'electricity_readings', (int) $pdo->lastInsertId(), null, [
                    'reading_date' => $date, 'morning_reading' => $newMorning, 'evening_reading' => $newEvening,
                ]);
            }
            setFlash('success', "Today's reading saved.");
        }
        redirect('/modules/utilities/electricity.php');
    }

    if ($action === 'edit_reading' && $isAdmin) {
        $id = (int) ($_POST['id'] ?? 0);
        $date = clean($_POST['reading_date'] ?? '');
        $morning = clean($_POST['morning_reading'] ?? '');
        $evening = clean($_POST['evening_reading'] ?? '');
        $stmt = $pdo->prepare("SELECT * FROM electricity_readings WHERE id=:id");
        $stmt->execute([':id' => $id]);
        $old = $stmt->fetch();

        if (!$old || $date === '') {
            setFlash('error', 'Select a date.');
        } elseif ($evening !== '' && $morning !== '' && (float) $evening < (float) $morning) {
            setFlash('error', 'Evening reading cannot be lower than the morning reading.');
        } else {
            $pdo->prepare("UPDATE electricity_readings SET reading_date=:d, morning_reading=:m, evening_reading=:e, updated_at=NOW() WHERE id=:id")
                ->execute([
                    ':d' => $date, ':m' => $morning !== '' ? $morning : null,
                    ':e' => $evening !== '' ? $evening : null, ':id' => $id,
                ]);
            logAudit($pdo, $currentUser['id'], 'update', 'electricity_readings', $id, $old, [
                'reading_date' => $date, 'morning_reading' => $morning ?: null, 'evening_reading' => $evening ?: null,
            ]);
            setFlash('success', 'Reading updated.');
        }
        redirect('/modules/utilities/electricity.php');
    }

    if ($action === 'delete_reading' && $isAdmin) {
        $id = (int) ($_POST['id'] ?? 0);
        $old = $pdo->prepare("SELECT * FROM electricity_readings WHERE id=:id");
        $old->execute([':id' => $id]);
        $oldRow = $old->fetch();
        $pdo->prepare("DELETE FROM electricity_readings WHERE id=:id")->execute([':id' => $id]);
        logAudit($pdo, $currentUser['id'], 'delete', 'electricity_readings', $id, $oldRow, null);
        setFlash('success', 'Reading deleted.');
        redirect('/modules/utilities/electricity.php');
    }

    // ---------- Monthly bill payment (Admin only) ----------
    if ($action === 'save_bill' && $isAdmin) {
        $id = (int) ($_POST['id'] ?? 0);
        $month = clean($_POST['bill_month'] ?? '');
        $amount = clean($_POST['amount_paid'] ?? '');
        $paidDate = clean($_POST['paid_date'] ?? '');
        $remarks = clean($_POST['remarks'] ?? '');
        $monthDate = $month !== '' ? $month . '-01' : '';

        if ($monthDate === '' || !is_numeric($amount) || $amount <= 0) {
            setFlash('error', 'Select a month and enter a valid amount paid.');
        } else {
            $new = [
                ':month' => $monthDate, ':amount' => $amount,
                ':paid' => $paidDate !== '' ? $paidDate : null, ':remarks' => $remarks !== '' ? $remarks : null,
            ];
            if ($id > 0) {
                $old = $pdo->prepare("SELECT * FROM electricity_bills WHERE id=:id");
                $old->execute([':id' => $id]);
                $oldRow = $old->fetch();
                $pdo->prepare("UPDATE electricity_bills SET bill_month=:month, amount_paid=:amount, paid_date=:paid, remarks=:remarks, updated_at=NOW() WHERE id=:id")
                    ->execute($new + [':id' => $id]);
                logAudit($pdo, $currentUser['id'], 'update', 'electricity_bills', $id, $oldRow, $new);
                setFlash('success', 'Bill payment updated.');
            } else {
                try {
                    $pdo->prepare("INSERT INTO electricity_bills (bill_month, amount_paid, paid_date, remarks, created_by) VALUES (:month, :amount, :paid, :remarks, :uid)")
                        ->execute($new + [':uid' => $currentUser['id']]);
                    logAudit($pdo, $currentUser['id'], 'create', 'electricity_bills', (int) $pdo->lastInsertId(), null, $new);
                    setFlash('success', 'Bill payment logged.');
                } catch (PDOException $e) {
                    setFlash('error', 'A bill payment for this month is already logged - edit it instead.');
                }
            }
        }
        redirect('/modules/utilities/electricity.php');
    }

    if ($action === 'delete_bill' && $isAdmin) {
        $id = (int) ($_POST['id'] ?? 0);
        $old = $pdo->prepare("SELECT * FROM electricity_bills WHERE id=:id");
        $old->execute([':id' => $id]);
        $oldRow = $old->fetch();
        $pdo->prepare("DELETE FROM electricity_bills WHERE id=:id")->execute([':id' => $id]);
        logAudit($pdo, $currentUser['id'], 'delete', 'electricity_bills', $id, $oldRow, null);
        setFlash('success', 'Bill payment deleted.');
        redirect('/modules/utilities/electricity.php');
    }
}

$editReading = null;
if ($isAdmin && !empty($_GET['edit_reading'])) {
    $stmt = $pdo->prepare("SELECT * FROM electricity_readings WHERE id=:id");
    $stmt->execute([':id' => (int) $_GET['edit_reading']]);
    $editReading = $stmt->fetch();
}
$editBill = null;
if ($isAdmin && !empty($_GET['edit_bill'])) {
    $stmt = $pdo->prepare("SELECT * FROM electricity_bills WHERE id=:id");
    $stmt->execute([':id' => (int) $_GET['edit_bill']]);
    $editBill = $stmt->fetch();
}

$readings = $pdo->query("SELECT * FROM electricity_readings ORDER BY reading_date DESC")->fetchAll();
$todayReading = null;
foreach ($readings as $r) {
    if ($r['reading_date'] === date('Y-m-d')) { $todayReading = $r; break; }
}

$bills = [];
if ($isAdmin) {
    $bills = $pdo->query("SELECT * FROM electricity_bills ORDER BY bill_month DESC")->fetchAll();
}

function readingUsage($r) {
    if ($r['morning_reading'] === null || $r['evening_reading'] === null) return null;
    return (float) $r['evening_reading'] - (float) $r['morning_reading'];
}

$pageTitle = 'Electricity';
$activeMenu = 'electricity';
$hideTopbar = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<details class="card">
  <summary class="flex-between">
    <h3 class="mt-0" style="margin:0;"><span class="icon-chip icon-chip-amber" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('chart') ?></span>Today's Meter Reading</h3>
    <span class="batch-chevron"><?= tabIcon('chevron') ?></span>
  </summary>
  <div class="card-detail">
  <p class="help-text" style="margin-top:0;">Enter the reading whenever you check it - morning and evening can be logged separately, one after another.</p>
  <form method="post" action="">
    <input type="hidden" name="action" value="log_reading">
    <div class="form-row">
      <div><label>Morning Reading</label><input type="number" step="0.01" min="0" name="morning_reading" value="<?= e($todayReading['morning_reading'] ?? '') ?>"></div>
      <div><label>Evening Reading</label><input type="number" step="0.01" min="0" name="evening_reading" value="<?= e($todayReading['evening_reading'] ?? '') ?>"></div>
    </div>
    <button type="submit" class="btn btn-accent" style="margin-top:14px;width:100%;">Save Today's Reading</button>
  </form>
  </div>
</details>

<details class="card"<?= $editReading ? ' open' : '' ?>>
  <summary class="flex-between">
    <h3 class="mt-0" style="margin:0;"><span class="icon-chip icon-chip-blue" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('clock') ?></span>Reading History</h3>
    <span class="batch-chevron"><?= tabIcon('chevron') ?></span>
  </summary>
  <div class="card-detail">
  <?php if (!$readings): ?>
    <p class="row-card-empty">No readings logged yet.</p>
  <?php else: ?>
  <div class="row-list">
    <?php foreach ($readings as $r): ?>
      <?php $usage = readingUsage($r); $isEditing = $editReading && (int) $editReading['id'] === (int) $r['id']; ?>
      <details class="row-card row-card-info"<?= $isEditing ? ' open' : '' ?>>
        <summary>
          <span class="row-card-heading"><strong><?= formatDate($r['reading_date']) ?></strong> <span class="text-soft"><?= $r['morning_reading'] !== null ? number_format($r['morning_reading'], 2) : '-' ?> &rarr; <?= $r['evening_reading'] !== null ? number_format($r['evening_reading'], 2) : '-' ?></span> <?php if ($usage !== null): ?><span class="pill pill-active"><?= number_format($usage, 2) ?> units</span><?php endif; ?></span>
          <span class="row-chevron"><?= tabIcon('chevron') ?></span>
        </summary>
        <div class="row-card-detail">
        <?php if ($isEditing): ?>
          <form method="post" action="">
            <input type="hidden" name="action" value="edit_reading">
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <div class="form-row">
              <div><label>Date *</label><input type="date" name="reading_date" required value="<?= e($r['reading_date']) ?>"></div>
            </div>
            <div class="form-row">
              <div><label>Morning Reading</label><input type="number" step="0.01" min="0" name="morning_reading" value="<?= e($r['morning_reading']) ?>"></div>
              <div><label>Evening Reading</label><input type="number" step="0.01" min="0" name="evening_reading" value="<?= e($r['evening_reading']) ?>"></div>
            </div>
            <div class="row-card-actions" style="margin-top:12px;">
              <button type="submit" class="btn btn-sm btn-accent">Update</button>
              <a href="<?= APP_URL ?>/modules/utilities/electricity.php<?= $embedMode ? '?embed=1' : '' ?>" class="btn btn-sm btn-outline">Cancel</a>
            </div>
          </form>
        <?php else: ?>
        <div class="row-card-fields">
          <div><div class="field-label">Morning</div><div class="field-value"><?= $r['morning_reading'] !== null ? number_format($r['morning_reading'], 2) : '-' ?></div></div>
          <div><div class="field-label">Evening</div><div class="field-value"><?= $r['evening_reading'] !== null ? number_format($r['evening_reading'], 2) : '-' ?></div></div>
          <div><div class="field-label">Day Usage</div><div class="field-value"><?= $usage !== null ? number_format($usage, 2) : '-' ?></div></div>
        </div>
        <?php if ($isAdmin): ?>
          <div class="row-card-actions" style="margin-top:12px;">
            <a class="btn btn-sm btn-outline" href="?edit_reading=<?= (int) $r['id'] ?><?= $embedMode ? '&embed=1' : '' ?>">Edit</a>
            <form method="post" style="display:inline" data-confirm="Delete this reading? This cannot be undone.">
              <input type="hidden" name="action" value="delete_reading">
              <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger">Delete</button>
            </form>
          </div>
        <?php endif; ?>
        <?php endif; ?>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  </div>
</details>

<?php if ($isAdmin): ?>
<details class="card">
  <summary class="flex-between">
    <h3 class="mt-0" style="margin:0;"><span class="icon-chip icon-chip-green" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('dollar') ?></span>Log Monthly Bill Payment</h3>
    <span class="batch-chevron"><?= tabIcon('chevron') ?></span>
  </summary>
  <div class="card-detail">
  <p class="help-text" style="margin-top:0;">Admin-only - after paying the electricity bill, log how much was paid for that month.</p>
  <form method="post" action="">
    <input type="hidden" name="action" value="save_bill">
    <div class="form-row">
      <div><label>Month *</label><input type="month" name="bill_month" required value="<?= date('Y-m') ?>"></div>
      <div><label>Amount Paid (Rs) *</label><input type="number" step="0.01" min="0" name="amount_paid" required></div>
    </div>
    <div class="form-row">
      <div><label>Paid Date</label><input type="date" name="paid_date" value="<?= date('Y-m-d') ?>"></div>
      <div><label>Remarks</label><input type="text" name="remarks" maxlength="255"></div>
    </div>
    <button type="submit" class="btn btn-accent" style="margin-top:14px;width:100%;">Log Payment</button>
  </form>
  </div>
</details>

<details class="card"<?= $editBill ? ' open' : '' ?>>
  <summary class="flex-between">
    <h3 class="mt-0" style="margin:0;"><span class="icon-chip icon-chip-purple" style="margin-bottom:0;vertical-align:-8px;margin-right:6px;"><?= tabIcon('book') ?></span>Bill Payment History</h3>
    <span class="batch-chevron"><?= tabIcon('chevron') ?></span>
  </summary>
  <div class="card-detail">
  <?php if (!$bills): ?>
    <p class="row-card-empty">No bill payments logged yet.</p>
  <?php else: ?>
  <div class="row-list">
    <?php foreach ($bills as $b): ?>
      <?php $isEditingBill = $editBill && (int) $editBill['id'] === (int) $b['id']; ?>
      <details class="row-card row-card-good"<?= $isEditingBill ? ' open' : '' ?>>
        <summary>
          <span class="row-card-heading"><strong><?= date('F Y', strtotime($b['bill_month'])) ?></strong> <span class="pill pill-active">Rs <?= number_format($b['amount_paid'], 2) ?></span></span>
          <span class="row-chevron"><?= tabIcon('chevron') ?></span>
        </summary>
        <div class="row-card-detail">
        <?php if ($isEditingBill): ?>
          <form method="post" action="">
            <input type="hidden" name="action" value="save_bill">
            <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
            <div class="form-row">
              <div><label>Month *</label><input type="month" name="bill_month" required value="<?= e(substr($b['bill_month'], 0, 7)) ?>"></div>
              <div><label>Amount Paid (Rs) *</label><input type="number" step="0.01" min="0" name="amount_paid" required value="<?= e($b['amount_paid']) ?>"></div>
            </div>
            <div class="form-row">
              <div><label>Paid Date</label><input type="date" name="paid_date" value="<?= e($b['paid_date']) ?>"></div>
              <div><label>Remarks</label><input type="text" name="remarks" maxlength="255" value="<?= e($b['remarks']) ?>"></div>
            </div>
            <div class="row-card-actions" style="margin-top:12px;">
              <button type="submit" class="btn btn-sm btn-accent">Update</button>
              <a href="<?= APP_URL ?>/modules/utilities/electricity.php<?= $embedMode ? '?embed=1' : '' ?>" class="btn btn-sm btn-outline">Cancel</a>
            </div>
          </form>
        <?php else: ?>
        <div class="row-card-fields">
          <div><div class="field-label">Amount Paid</div><div class="field-value">Rs <?= number_format($b['amount_paid'], 2) ?></div></div>
          <div><div class="field-label">Paid Date</div><div class="field-value"><?= $b['paid_date'] ? formatDate($b['paid_date']) : '-' ?></div></div>
          <div><div class="field-label">Remarks</div><div class="field-value"><?= e($b['remarks']) ?: '-' ?></div></div>
        </div>
        <div class="row-card-actions" style="margin-top:12px;">
          <a class="btn btn-sm btn-outline" href="?edit_bill=<?= (int) $b['id'] ?><?= $embedMode ? '&embed=1' : '' ?>">Edit</a>
          <form method="post" style="display:inline" data-confirm="Delete this bill payment? This cannot be undone.">
            <input type="hidden" name="action" value="delete_bill">
            <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
          </form>
        </div>
        <?php endif; ?>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  </div>
</details>
<?php endif; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
