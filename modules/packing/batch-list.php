<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/ledger.php';
requireRole(['Admin', 'Plant Head']);

$isAdmin = $currentUser['role_name'] === 'Admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $batchId = (int) ($_POST['batch_id'] ?? 0);

    if ($action === 'reopen') {
        $stmt = $pdo->prepare("SELECT * FROM production_batches WHERE id=:id"); $stmt->execute([':id'=>$batchId]); $batch = $stmt->fetch();
        if ($batch) {
            $newTarget = (int) ($_POST['new_target'] ?? $batch['target_bags']);
            if ($newTarget < 1) $newTarget = $batch['target_bags'];
            $pdo->prepare("UPDATE production_batches SET status='reopened', target_bags=:t, completed_at=NULL WHERE id=:id")
                ->execute([':t' => $newTarget, ':id' => $batchId]);
            logAudit($pdo, $currentUser['id'], 'reopen_batch', 'production_batches', $batchId,
                ['status' => $batch['status'], 'target_bags' => $batch['target_bags']],
                ['status' => 'reopened', 'target_bags' => $newTarget]
            );
            setFlash('success', 'Batch reopened' . ($newTarget != $batch['target_bags'] ? " with new target $newTarget bags." : '.'));
        }
        redirect('/modules/packing/batch-list.php');
    }

    if ($action === 'edit_item' && $isAdmin) {
        $tokenId = (int) ($_POST['token_id'] ?? 0);
        $target = (int) ($_POST['target_bags'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM production_batches WHERE id=:id"); $stmt->execute([':id'=>$batchId]); $batch = $stmt->fetch();

        if (!$batch || $tokenId <= 0 || $target <= 0) {
            setFlash('error', 'Select a token and enter a valid target.');
        } else {
            $completedStmt = $pdo->prepare("SELECT total_good_qty FROM packing_production_updates WHERE batch_id=:id ORDER BY id DESC LIMIT 1");
            $completedStmt->execute([':id' => $batchId]);
            $completed = (int) ($completedStmt->fetchColumn() ?: 0) + (int) $batch['carried_forward_qty'];

            $pdo->prepare("UPDATE production_batches SET token_id=:token_id, target_bags=:target, updated_at=NOW() WHERE id=:id")
                ->execute([':token_id' => $tokenId, ':target' => $target, ':id' => $batchId]);

            // Bidirectional: a lowered target can auto-complete an
            // already-fulfilled SKU, but a RAISED target must just as
            // reliably reopen one that was only "complete" against the old
            // (lower) target - otherwise editing the target back up leaves
            // it stuck showing COMPLETED even though completed < target.
            $isFulfilled = $completed >= $target;
            $statusNote = '';
            if ($isFulfilled && $batch['status'] !== 'completed') {
                $pdo->prepare("UPDATE production_batches SET status='completed', completed_at=NOW() WHERE id=:id")->execute([':id' => $batchId]);
                $statusNote = ' Already fulfilled - marked COMPLETED.';
            } elseif (!$isFulfilled && $batch['status'] === 'completed') {
                $pdo->prepare("UPDATE production_batches SET status='active', completed_at=NULL WHERE id=:id")->execute([':id' => $batchId]);
                $statusNote = ' Target raised above what\'s completed - reopened for packing.';
            }
            logAudit($pdo, $currentUser['id'], 'update', 'production_batches', $batchId,
                ['token_id' => $batch['token_id'], 'target_bags' => $batch['target_bags']],
                ['token_id' => $tokenId, 'target_bags' => $target]
            );
            setFlash('success', 'SKU updated.' . $statusNote);
        }
        redirect('/modules/packing/batch-list.php');
    }

    if ($action === 'add_sku_to_batch' && $isAdmin) {
        $groupId = (int) ($_POST['batch_group_id'] ?? 0);
        $productId = (int) ($_POST['product_id'] ?? 0);
        $tokenId = (int) ($_POST['token_id'] ?? 0);
        $target = (int) ($_POST['target_bags'] ?? 0);
        $groupCheck = $pdo->prepare("SELECT id, batch_no FROM batch_groups WHERE id=:id");
        $groupCheck->execute([':id' => $groupId]);
        $group = $groupCheck->fetch();

        if (!$group || $productId <= 0 || $tokenId <= 0 || $target <= 0) {
            setFlash('error', 'Select a product, token and enter a valid target.');
        } else {
            $pdo->prepare(
                "INSERT INTO production_batches (batch_group_id, product_id, token_id, target_bags, created_by)
                 VALUES (:batch_group_id, :product_id, :token_id, :target, :created_by)"
            )->execute([
                ':batch_group_id' => $groupId, ':product_id' => $productId,
                ':token_id' => $tokenId, ':target' => $target, ':created_by' => $currentUser['id'],
            ]);
            $newItemId = (int) $pdo->lastInsertId();
            logAudit($pdo, $currentUser['id'], 'add_sku_to_batch', 'production_batches', $newItemId, null, [
                'batch_no' => $group['batch_no'], 'product_id' => $productId,
                'token_id' => $tokenId, 'target_bags' => $target,
            ]);
            applyLiveStockCarryForward($pdo, $newItemId, $productId, $tokenId, $target, $currentUser['id']);
            setFlash('success', 'SKU added to batch ' . $group['batch_no'] . '.');
        }
        redirect('/modules/packing/batch-list.php');
    }

    if ($action === 'delete_item' && $isAdmin) {
        $pdo->beginTransaction();
        try {
            $old = $pdo->prepare("SELECT * FROM production_batches WHERE id=:id"); $old->execute([':id'=>$batchId]); $oldRow = $old->fetch();
            // Give back any Live Stock this item claimed via carry-forward
            // BEFORE deleting/releasing it, or that claimed stock would
            // stay stuck as permanently unavailable even though nothing
            // here needs it anymore.
            reverseLiveStockClaims($pdo, $batchId);
            $released = releaseOrDeleteBatchItem($pdo, $oldRow);
            logAudit($pdo, $currentUser['id'], $released ? 'release_to_live_stock' : 'delete',
                'production_batches', $batchId, $oldRow, null);
            $pdo->commit();
            setFlash('success', $released
                ? 'SKU removed from batch - its packed stock is now available in Live Stock.'
                : 'SKU removed from batch.');
        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlash('error', 'Could not remove this SKU.');
        }
        redirect('/modules/packing/batch-list.php');
    }

    if ($action === 'delete_group' && $isAdmin) {
        $groupId = (int) ($_POST['batch_group_id'] ?? 0);
        $pdo->beginTransaction();
        try {
            $itemStmt = $pdo->prepare("SELECT * FROM production_batches WHERE batch_group_id=:id");
            $itemStmt->execute([':id' => $groupId]);
            $items = $itemStmt->fetchAll();
            $anyReleased = false;
            foreach ($items as $item) {
                // Same Live Stock give-back as delete_item, for every SKU
                // in the group being deleted.
                reverseLiveStockClaims($pdo, (int) $item['id']);
                $released = releaseOrDeleteBatchItem($pdo, $item);
                logAudit($pdo, $currentUser['id'], $released ? 'release_to_live_stock' : 'delete',
                    'production_batches', (int) $item['id'], $item, null);
                if ($released) $anyReleased = true;
            }
            if (!$anyReleased) {
                // Only safe to remove the group container itself once every
                // item in it was actually deleted, not released (a released
                // item still needs its batch_group_id to exist).
                $pdo->prepare("DELETE FROM batch_groups WHERE id=:id")->execute([':id' => $groupId]);
                logAudit($pdo, $currentUser['id'], 'delete', 'batch_groups', $groupId, null, null);
            }
            $pdo->commit();
            setFlash('success', $anyReleased
                ? 'Batch removed - any packed stock is now available in Live Stock.'
                : 'Batch deleted.');
        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlash('error', 'Could not delete this batch.');
        }
        redirect('/modules/packing/batch-list.php');
    }

    // ---------- Vehicle dispatch (shown on a batch card once every SKU in
    // the group is Completed - see includes/partials/batch-cards.php) ----------
    // Admin only ever decides the vehicle itself (No/arrival/capacity) - not
    // which SKUs go on it. Plant Head decides that, at the point they
    // actually load it (see mark_loaded below), since that's the only
    // moment anyone actually knows what's really going on the truck.
    if (($action === 'assign_vehicle' || $action === 'edit_vehicle') && $isAdmin) {
        $groupId = (int) ($_POST['batch_group_id'] ?? 0);
        $vehicleNo = clean($_POST['vehicle_no'] ?? '');
        $arrivalTime = clean($_POST['arrival_time'] ?? '');
        $capacity = clean($_POST['capacity_mt'] ?? '');
        $editingVehicleId = $action === 'edit_vehicle' ? (int) ($_POST['vehicle_id'] ?? 0) : 0;

        $oldVehicle = null;
        if ($action === 'edit_vehicle') {
            $oldStmt = $pdo->prepare("SELECT * FROM batch_vehicle_assignments WHERE id=:id");
            $oldStmt->execute([':id' => $editingVehicleId]);
            $oldVehicle = $oldStmt->fetch();
            if ($oldVehicle) $groupId = (int) $oldVehicle['batch_group_id'];
        }

        if ($action === 'edit_vehicle' && !$oldVehicle) {
            setFlash('error', 'Vehicle not found.');
        } elseif ($vehicleNo === '' || $arrivalTime === '' || !is_numeric($capacity) || $capacity <= 0) {
            setFlash('error', 'Fill in the vehicle number, arrival time and a valid capacity.');
        } else {
            $newValue = ['vehicle_no' => $vehicleNo, 'arrival_time' => $arrivalTime, 'capacity_mt' => $capacity];
            if ($action === 'edit_vehicle') {
                $pdo->prepare("UPDATE batch_vehicle_assignments SET vehicle_no=:v, arrival_time=:a, capacity_mt=:c WHERE id=:id")
                    ->execute([':v' => $vehicleNo, ':a' => $arrivalTime, ':c' => $capacity, ':id' => $editingVehicleId]);
                logAudit($pdo, $currentUser['id'], 'update', 'batch_vehicle_assignments', $editingVehicleId, $oldVehicle, $newValue);
                setFlash('success', 'Vehicle ' . $vehicleNo . ' updated.');
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO batch_vehicle_assignments (batch_group_id, vehicle_no, arrival_time, capacity_mt, assigned_by)
                     VALUES (:g, :v, :a, :c, :uid)"
                );
                $stmt->execute([':g' => $groupId, ':v' => $vehicleNo, ':a' => $arrivalTime, ':c' => $capacity, ':uid' => $currentUser['id']]);
                $newId = (int) $pdo->lastInsertId();
                logAudit($pdo, $currentUser['id'], 'create', 'batch_vehicle_assignments', $newId, null, ['batch_group_id' => $groupId] + $newValue);
                setFlash('success', 'Vehicle ' . $vehicleNo . ' assigned.');
            }
        }
        redirect('/modules/packing/batch-list.php');
    }

    if (in_array($action, ['accept_vehicle', 'mark_dispatched'], true)) {
        $vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM batch_vehicle_assignments WHERE id=:id");
        $stmt->execute([':id' => $vehicleId]);
        $vehicle = $stmt->fetch();

        $steps = ['accept_vehicle' => ['assigned', 'accepted'], 'mark_dispatched' => ['loaded', 'dispatched']];
        [$fromStatus, $toStatus] = $steps[$action];

        if (!$vehicle || $vehicle['status'] !== $fromStatus) {
            setFlash('error', 'This vehicle is not at the right stage for that action.');
        } else {
            $col = $toStatus . '_at';
            $pdo->prepare("UPDATE batch_vehicle_assignments SET status=:s, $col=NOW() WHERE id=:id")
                ->execute([':s' => $toStatus, ':id' => $vehicleId]);
            logAudit($pdo, $currentUser['id'], $action, 'batch_vehicle_assignments', $vehicleId,
                ['status' => $fromStatus], ['status' => $toStatus]);
            setFlash('success', 'Vehicle ' . $vehicle['vehicle_no'] . ' marked ' . $toStatus . '.');
        }
        redirect('/modules/packing/batch-list.php');
    }

    // Plant Head confirms what actually went on the truck once it's being
    // loaded - which SKU, how many bags, and how many were found damaged in
    // the process. Damage here reuses the exact same mechanism as
    // modules/damage/damage-entry.php (same damage_entries/
    // reprocessing_entries tables, same carried_forward_qty recovery) - not
    // a separate damage record, just triggered from this form too. Whatever
    // was available but neither loaded nor damaged just stays uncommitted -
    // see buildLiveStock()'s vehicleAwareSpokenFor(), which is what turns it
    // into visible Live Stock once every vehicle for the batch is past this
    // step (not left dangling while another truck might still need it).
    if ($action === 'mark_loaded') {
        $vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
        $loadItems = $_POST['load_items'] ?? [];

        $vStmt = $pdo->prepare("SELECT * FROM batch_vehicle_assignments WHERE id=:id");
        $vStmt->execute([':id' => $vehicleId]);
        $vehicle = $vStmt->fetch();

        if (!$vehicle || $vehicle['status'] !== 'accepted') {
            setFlash('error', 'This vehicle is not at the right stage for that action.');
            redirect('/modules/packing/batch-list.php');
        }

        $batchSkus = batchSkuAvailability($pdo, (int) $vehicle['batch_group_id'], $vehicleId);

        $validLoads = [];
        $validDamage = [];
        $itemErrors = [];
        foreach ($loadItems as $item) {
            $skuId = (int) ($item['production_batch_id'] ?? 0);
            $bags = (int) ($item['bags'] ?? 0);
            $damaged = (int) ($item['damaged'] ?? 0);
            if ($skuId <= 0 || !isset($batchSkus[$skuId])) continue;
            $sku = $batchSkus[$skuId];
            if ($bags + $damaged > $sku['available']) {
                $itemErrors[] = $sku['label'] . ': only ' . $sku['available'] . ' bags available, cannot account for ' . ($bags + $damaged) . '.';
                continue;
            }
            if ($bags > 0) $validLoads[] = ['production_batch_id' => $skuId, 'bags' => $bags, 'mt' => round($bags * $sku['size_kg'] / 1000, 3)];
            if ($damaged > 0) $validDamage[] = ['production_batch_id' => $skuId, 'qty' => $damaged];
        }

        if ($itemErrors) {
            setFlash('error', implode(' ', $itemErrors));
        } elseif (!$validLoads && !$validDamage) {
            setFlash('error', 'Enter at least one SKU with bags loaded or damaged.');
        } else {
            $pdo->beginTransaction();
            try {
                $itemStmt = $pdo->prepare(
                    "INSERT INTO batch_vehicle_load_items (vehicle_assignment_id, production_batch_id, bags, mt) VALUES (:v, :b, :bags, :mt)"
                );
                foreach ($validLoads as $item) {
                    $itemStmt->execute([':v' => $vehicleId, ':b' => $item['production_batch_id'], ':bags' => $item['bags'], ':mt' => $item['mt']]);
                }

                foreach ($validDamage as $dmg) {
                    $deStmt = $pdo->prepare(
                        "INSERT INTO damage_entries (batch_id, nozzle, damage_qty, damage_date, damage_time, reason, remarks, user_id, action_status)
                         VALUES (:batch_id, 'na', :qty, CURDATE(), CURTIME(), 'Damaged while loading vehicle', :remarks, :user_id, 'reprocessing')"
                    );
                    $deStmt->execute([
                        ':batch_id' => $dmg['production_batch_id'], ':qty' => $dmg['qty'],
                        ':remarks' => 'Vehicle ' . $vehicle['vehicle_no'], ':user_id' => $currentUser['id'],
                    ]);
                    $damageId = (int) $pdo->lastInsertId();

                    $reprocessStmt = $pdo->prepare("INSERT INTO reprocessing_entries (damage_id, reprocessing_qty, created_by) VALUES (:did, :qty, :uid)");
                    $reprocessStmt->execute([':did' => $damageId, ':qty' => $dmg['qty'], ':uid' => $currentUser['id']]);
                    $refId = (int) $pdo->lastInsertId();

                    $pdo->prepare("UPDATE production_batches SET carried_forward_qty = carried_forward_qty + :qty WHERE id=:id")
                        ->execute([':qty' => $dmg['qty'], ':id' => $dmg['production_batch_id']]);

                    logStockMovement($pdo, 'reprocessing', 'reprocessing_entries', $refId, 'finished', $dmg['qty'], 'Bags', $dmg['qty'], 0, $currentUser['id'], 'Damage #' . $damageId . ' found loading vehicle ' . $vehicle['vehicle_no'] . ' - recovered to production');
                    logAudit($pdo, $currentUser['id'], 'create', 'damage_entries', $damageId, null, [
                        'batch_id' => $dmg['production_batch_id'], 'qty' => $dmg['qty'], 'action_status' => 'reprocessing',
                    ]);
                }

                $pdo->prepare("UPDATE batch_vehicle_assignments SET status='loaded', loaded_at=NOW() WHERE id=:id")->execute([':id' => $vehicleId]);
                logAudit($pdo, $currentUser['id'], 'mark_loaded', 'batch_vehicle_assignments', $vehicleId,
                    ['status' => 'accepted'], ['status' => 'loaded', 'load_items' => $validLoads, 'damaged' => $validDamage]);

                $pdo->commit();
                $msg = 'Vehicle ' . $vehicle['vehicle_no'] . ' marked loading.';
                if ($validDamage) $msg .= ' ' . array_sum(array_column($validDamage, 'qty')) . ' damaged bags recovered to production.';
                setFlash('success', $msg);
            } catch (Exception $e) {
                $pdo->rollBack();
                setFlash('error', 'Could not save loaded quantities: ' . $e->getMessage());
            }
        }
        redirect('/modules/packing/batch-list.php');
    }
}

['groups' => $groups, 'revisions' => $revisions] = buildBatchGroups($pdo);

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

$pageTitle = 'Production Batches';
$activeMenu = 'batch-list';
$hideTopbar = true;
$pageHasFilter = true;
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="card">
  <div class="flex-between">
    <h3 class="mt-0">All Batches (<?= count($groups) ?>)</h3>
    <?php if ($isAdmin): ?>
      <a href="<?= APP_URL ?>/modules/packing/batch-create.php" class="btn btn-accent btn-sm">+ New Batch</a>
    <?php else: ?>
      <a href="<?= APP_URL ?>/modules/packing/packing-update.php" class="btn btn-accent btn-sm"><?= tabIcon('edit') ?> Packing Update</a>
    <?php endif; ?>
  </div>
  <?php if (!$groups): ?>
    <p class="text-soft mb-0">No batches match this filter.</p>
  <?php endif; ?>
</div>

<div class="masters-modal-overlay<?= !empty($_GET) ? ' is-open' : '' ?>" id="filterModalOverlay">
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
        <a href="?" class="btn btn-sm <?= $dateFrom === '' && $dateTo === '' ? 'btn-accent' : 'btn-outline' ?>">All</a>
      </div>
      <form method="get" action="" class="form-row filter-form" style="align-items:flex-end;">
        <div><label>From</label><input type="date" name="date_from" value="<?= e($dateFrom) ?>"></div>
        <div><label>To</label><input type="date" name="date_to" value="<?= e($dateTo) ?>"></div>
        <div><button type="submit" class="btn btn-outline">Filter</button></div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/partials/batch-cards.php'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
