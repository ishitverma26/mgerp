<?php
/**
 * Renders the "All Batches" collapsible card list. Expects $groups and
 * $revisions (from buildBatchGroups()) and $isAdmin to already be set by
 * the including page. Every form posts explicitly to batch-list.php so
 * this same partial works whether it's embedded there or on a dashboard.
 * Fetches its own token data for the inline SKU edit form so it doesn't
 * depend on the including page having set that up too.
 */
$batchListUrl = APP_URL . '/modules/packing/batch-list.php';

if ($isAdmin) {
    $batchCardsTokens = $pdo->query("SELECT * FROM tokens WHERE status='active' ORDER BY token_value")->fetchAll();
    $batchCardsProducts = $pdo->query("SELECT * FROM products WHERE status='active' ORDER BY name")->fetchAll();
    $batchCardsProductTokenIds = [];
    foreach ($pdo->query("SELECT product_id, token_id FROM product_tokens") as $row) {
        $batchCardsProductTokenIds[(int) $row['product_id']][] = (int) $row['token_id'];
    }
    $batchCardsAllTokenIds = array_map(fn($t) => (int) $t['id'], $batchCardsTokens);
    if (!function_exists('allowedTokenIdsFor')) {
        function allowedTokenIdsFor(int $productId, array $productTokenIds, array $allTokenIds): array {
            return $productTokenIds[$productId] ?? $allTokenIds;
        }
    }
}
?>
<?php foreach ($groups as $groupId => $group): ?>
  <details class="card batch-card-<?= e($group['status_pill']) ?>">
    <summary class="batch-summary-header">
      <div class="token-badges">
        <?php foreach ($group['token_colors'] as $tokenValue => $color): ?>
          <span class="token-badge token-badge-<?= e($color) ?>" title="Token <?= e($tokenValue) ?>"><?= e(mb_substr($tokenValue, 0, 3)) ?></span>
        <?php endforeach; ?>
      </div>
      <div class="batch-summary-title">
        <div class="batch-summary-name"><?= e($group['batch_no']) ?> &middot; <?= number_format($group['total_mt'], 2) ?> MT</div>
        <div class="batch-summary-collapsed-sub"><?= e(implode(' + ', array_map(fn($i) => $i['product_name'] . ' ' . $i['size_kg'] . 'KG', $group['items']))) ?></div>
      </div>
      <span class="pill pill-<?= $group['status_pill'] ?>"><?= e($group['status_label']) ?></span>
      <span class="batch-chevron"><?= tabIcon('chevron') ?></span>
    </summary>

    <div class="batch-detail">
      <div class="batch-summary-sub">
        <?= $group['total_target'] !== null ? number_format($group['total_target']) . ' pcs total' : 'Target not set' ?>
        &middot; <?= count($group['items']) ?> SKU<?= count($group['items']) > 1 ? 's' : '' ?>
        &middot; <?= formatDateTime($group['created_at']) ?>
      </div>

      <?php if ($group['total_target'] !== null): ?>
      <div class="batch-summary-progress-label">
        <span><strong><?= number_format($group['total_completed']) ?></strong> / <?= number_format($group['total_target']) ?> pcs</span>
        <span><strong><?= $group['percent'] ?>%</strong></span>
      </div>
      <div class="progress-bar"><div class="progress-bar-fill" style="width:<?= $group['percent'] ?>%"></div></div>
      <?php endif; ?>

      <div class="batch-line-list">
        <?php foreach ($group['items'] as $b): $hasTarget = $b['target_bags'] !== null; $linePercent = $hasTarget && $b['target_bags'] > 0 ? min(100, round($b['completed'] / $b['target_bags'] * 100)) : 0; $lineDone = $b['status'] === 'completed'; ?>
          <div class="batch-line-row<?= $lineDone ? ' batch-line-row-done' : '' ?>">
            <span class="batch-line-name"><?= e($b['product_name']) ?> <?= e($b['size_kg']) ?>KG (&#8377;<?= e($b['token_value']) ?>)</span>
            <span class="batch-line-status batch-line-status-<?= $lineDone ? 'done' : 'progress' ?>" title="<?= $lineDone ? 'Complete' : 'In Production' ?>"><?= tabIcon($lineDone ? 'check-square' : 'refresh') ?></span>
            <span class="batch-line-progress">
              <?php if ($hasTarget): ?>
                <?= (int) $b['completed'] ?>/<?= (int) $b['target_bags'] ?> (<?= $linePercent ?>%)
              <?php else: ?>
                <span class="text-soft">Target not set</span>
              <?php endif; ?>
            </span>
            <span class="batch-line-actions">
              <?php if ($isAdmin): ?>
                <button type="button" class="btn btn-sm btn-outline btn-icon sku-edit-toggle" data-panel="skuEdit<?= (int) $b['id'] ?>" title="Edit" aria-label="Edit"><?= tabIcon('edit') ?></button>
              <?php endif; ?>
            </span>
          </div>
          <?php if ((int) $b['carried_forward_qty'] > 0): ?>
            <div class="carry-forward-box">
              <span class="carry-forward-icon"><?= tabIcon('arrow-right') ?></span>
              <div>
                <strong>Carry Forward</strong>
                <div class="carry-forward-detail">From Batch No <?= e($b['carried_forward_from_batch_no']) ?> &middot; <?= (int) $b['carried_forward_qty'] ?> pcs</div>
              </div>
            </div>
          <?php endif; ?>
          <?php if ($isAdmin): ?>
          <div class="sku-edit-panel" id="skuEdit<?= (int) $b['id'] ?>" style="display:none;">
            <?php $allowedIds = allowedTokenIdsFor((int) $b['product_id'], $batchCardsProductTokenIds, $batchCardsAllTokenIds); ?>
            <form method="post" action="<?= $batchListUrl ?>" class="form-row filter-form" style="align-items:flex-end;">
              <input type="hidden" name="action" value="edit_item">
              <input type="hidden" name="batch_id" value="<?= (int) $b['id'] ?>">
              <div>
                <label>Token *</label>
                <select name="token_id" required>
                  <?php foreach ($batchCardsTokens as $t): if (!in_array((int) $t['id'], $allowedIds, true)) continue; ?>
                    <option value="<?= $t['id'] ?>" <?= (int) $b['token_id'] === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['token_value']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div><label>Bags (Qty) *</label><input type="number" step="1" min="1" name="target_bags" required value="<?= (int) $b['target_bags'] ?>"></div>
              <div style="display:flex;gap:6px;">
                <button type="submit" class="btn btn-sm btn-accent">Save</button>
                <button type="button" class="btn btn-sm btn-outline sku-edit-cancel" data-panel="skuEdit<?= (int) $b['id'] ?>">Cancel</button>
              </div>
            </form>
          </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>

      <?php foreach ($group['items'] as $b): if (!isset($revisions[(int) $b['id']])) continue; $rev = $revisions[(int) $b['id']]; ?>
        <div class="revised-box">
          <?= tabIcon('alert') ?>
          <div>
            <strong>Revised</strong> <span class="revised-time"><?= formatDateTime($rev['at']) ?></span>
            <div class="revised-detail">
              <?= e($b['product_name']) ?> <?= e($b['size_kg']) ?>KG (Token <?= e($b['token_value']) ?>)<?php if ($rev['delta'] !== null): ?> &middot; <?= $rev['delta'] > 0 ? '+' . $rev['delta'] : $rev['delta'] ?> pcs target<?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if ($isAdmin): ?>
        <button type="button" class="btn btn-sm btn-tint-blue sku-edit-toggle" data-panel="addSku<?= (int) $groupId ?>" style="margin-top:8px;"><?= tabIcon('plus') ?> Add SKU</button>
        <div class="sku-edit-panel" id="addSku<?= (int) $groupId ?>" style="display:none;">
          <form method="post" action="<?= $batchListUrl ?>" class="form-row filter-form add-sku-form" style="align-items:flex-end;flex-wrap:wrap;">
            <input type="hidden" name="action" value="add_sku_to_batch">
            <input type="hidden" name="batch_group_id" value="<?= (int) $groupId ?>">
            <div>
              <label>Product *</label>
              <select name="product_id" class="add-sku-product" required>
                <option value="">Select product</option>
                <?php foreach ($batchCardsProducts as $p): ?>
                  <option value="<?= $p['id'] ?>"><?= e($p['name']) ?> &middot; <?= e($p['size_kg']) ?> KG</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label>Token *</label>
              <select name="token_id" class="add-sku-token" required>
                <option value="">Select product first</option>
              </select>
            </div>
            <div><label>Bags (Qty) *</label><input type="number" step="1" min="1" name="target_bags" required></div>
            <div style="display:flex;gap:6px;">
              <button type="submit" class="btn btn-sm btn-accent">Add</button>
              <button type="button" class="btn btn-sm btn-outline sku-edit-cancel" data-panel="addSku<?= (int) $groupId ?>">Cancel</button>
            </div>
          </form>
        </div>
      <?php endif; ?>

      <?php if ($group['status_label'] !== 'In Production'): ?>
        <?php
          $vStmt = $pdo->prepare("SELECT * FROM batch_vehicle_assignments WHERE batch_group_id=:id ORDER BY id DESC");
          $vStmt->execute([':id' => $groupId]);
          $vehicles = $vStmt->fetchAll();
          $vehicleStatusPill = ['assigned' => 'pending', 'accepted' => 'completed', 'loaded' => 'active', 'dispatched' => 'inactive'];
          $vehicleStatusLabel = ['assigned' => 'Assigned', 'accepted' => 'Accepted', 'loaded' => 'Loaded', 'dispatched' => 'Dispatched'];
          $vehicleRowClass = ['assigned' => 'pending', 'accepted' => 'info', 'loaded' => 'good', 'dispatched' => 'neutral'];
          $vLoadItemsStmt = $pdo->prepare(
              "SELECT li.production_batch_id, li.bags, li.mt, p.name AS product_name, p.size_kg, t.token_value
               FROM batch_vehicle_load_items li
               JOIN production_batches b ON b.id = li.production_batch_id
               JOIN products p ON p.id = b.product_id
               JOIN tokens t ON t.id = b.token_id
               WHERE li.vehicle_assignment_id = :id"
          );
          $vRevisionStmt = $pdo->prepare(
              "SELECT created_at FROM audit_logs
               WHERE module='batch_vehicle_assignments' AND record_id=:id AND action='update'
               ORDER BY id DESC LIMIT 1"
          );
        ?>
        <div class="section-divider" style="margin-top:16px;"><?= tabIcon('truck') ?> Vehicle Dispatch</div>
        <?php if (!$vehicles): ?>
          <?php if ($isAdmin): ?>
            <p class="help-text" style="margin-top:0;">Batch complete - assign a vehicle to dispatch it.</p>
          <?php else: ?>
            <div class="alert alert-info" style="margin:0;">Batch Complete - kindly contact Admin for vehicle.</div>
          <?php endif; ?>
        <?php else: ?>
          <div class="row-list">
            <?php foreach ($vehicles as $v): ?>
              <?php
                $vLoadItemsStmt->execute([':id' => $v['id']]);
                $loadItems = $vLoadItemsStmt->fetchAll();
                $vRevisionStmt->execute([':id' => (int) $v['id']]);
                $vRevisedAt = $vRevisionStmt->fetchColumn();
              ?>
              <div class="row-card row-card-<?= $vehicleRowClass[$v['status']] ?>">
                <div class="row-card-heading" style="margin-bottom:8px;">
                  <strong><?= e($v['vehicle_no']) ?></strong>
                  <?php if ($vRevisedAt): ?><span class="pill pill-pending">Revised</span><?php endif; ?>
                  <span class="pill pill-<?= $vehicleStatusPill[$v['status']] ?>"><?= $vehicleStatusLabel[$v['status']] ?></span>
                </div>
                <?php if ($vRevisedAt): ?>
                  <div class="revised-box" style="margin-bottom:10px;">
                    <?= tabIcon('alert') ?>
                    <div><strong>Revised</strong> <span class="revised-time"><?= formatDateTime($vRevisedAt) ?></span>
                      <div class="revised-detail">Vehicle details or load were changed after this was first assigned.</div>
                    </div>
                  </div>
                <?php endif; ?>
                <div class="row-card-fields">
                  <div><div class="field-label">Arrival</div><div class="field-value"><?= formatDateTime($v['arrival_time']) ?></div></div>
                  <div>
                    <div class="field-label">Load</div>
                    <div class="field-value">
                      <?php foreach ($loadItems as $li): ?>
                        <div><?= e($li['product_name']) ?> <?= e($li['size_kg']) ?>KG (Token <?= e($li['token_value']) ?>) - <?= (int) $li['bags'] ?> bags / <?= number_format($li['mt'], 3) ?> MT</div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                  <div><div class="field-label">Total Load</div><div class="field-value"><?= number_format(array_sum(array_column($loadItems, 'mt')), 3) ?> MT</div></div>
                  <div><div class="field-label">Capacity</div><div class="field-value"><?= number_format($v['capacity_mt'], 2) ?> MT</div></div>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;">
                  <?php if ($v['status'] === 'assigned'): ?>
                    <form method="post" action="<?= $batchListUrl ?>">
                      <input type="hidden" name="action" value="accept_vehicle">
                      <input type="hidden" name="vehicle_id" value="<?= (int) $v['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-accent">Accept</button>
                    </form>
                  <?php elseif ($v['status'] === 'accepted'): ?>
                    <button type="button" class="btn btn-sm btn-accent sku-edit-toggle" data-panel="vehicleLoad<?= (int) $v['id'] ?>">Mark Loading</button>
                  <?php elseif ($v['status'] === 'loaded'): ?>
                    <form method="post" action="<?= $batchListUrl ?>">
                      <input type="hidden" name="action" value="mark_dispatched">
                      <input type="hidden" name="vehicle_id" value="<?= (int) $v['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-accent">Mark Dispatched</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($isAdmin): ?>
                    <button type="button" class="btn btn-sm btn-outline sku-edit-toggle" data-panel="vehicleEdit<?= (int) $v['id'] ?>"><?= tabIcon('edit') ?> Edit</button>
                  <?php endif; ?>
                </div>
                <?php if ($v['status'] === 'accepted'): ?>
                  <div class="sku-edit-panel" id="vehicleLoad<?= (int) $v['id'] ?>" style="display:none;margin-top:10px;">
                    <form method="post" action="<?= $batchListUrl ?>" class="vehicle-assign-form">
                      <input type="hidden" name="action" value="mark_loaded">
                      <input type="hidden" name="vehicle_id" value="<?= (int) $v['id'] ?>">
                      <label style="display:block;">SKUs Loaded - edit the bags actually loaded, and log any found damaged while loading (recovered straight back into production, same as Damage Entry)</label>
                      <div class="vehicle-load-items">
                        <?php $loadAvailability = batchSkuAvailability($pdo, $groupId, (int) $v['id']); ?>
                        <?php foreach ($group['items'] as $idx => $b): ?>
                          <?php $maxForRow = $loadAvailability[(int) $b['id']]['available'] ?? 0; ?>
                          <div class="vehicle-load-row" style="margin-top:14px;">
                            <label style="display:block;font-size:12.5px;"><?= e($b['product_name']) ?> <?= e($b['size_kg']) ?>KG (Token <?= e($b['token_value']) ?>) <span class="text-soft"><?= $maxForRow ?> available</span></label>
                            <input type="hidden" name="load_items[<?= $idx ?>][production_batch_id]" value="<?= (int) $b['id'] ?>">
                            <div class="form-row" style="margin-top:6px;">
                              <div>
                                <label class="text-soft" style="font-weight:400;font-size:11.5px;">Bags Loaded</label>
                                <input type="number" step="1" min="0" max="<?= $maxForRow ?>" class="vehicle-load-bags" name="load_items[<?= $idx ?>][bags]" value="<?= $maxForRow ?>">
                              </div>
                              <div>
                                <label class="text-soft" style="font-weight:400;font-size:11.5px;">Damaged</label>
                                <input type="number" step="1" min="0" max="<?= $maxForRow ?>" name="load_items[<?= $idx ?>][damaged]" value="0">
                              </div>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      </div>
                      <div style="display:flex;gap:8px;margin-top:12px;">
                        <button type="submit" class="btn btn-sm btn-accent">Confirm Loading</button>
                        <button type="button" class="btn btn-sm btn-outline sku-edit-cancel" data-panel="vehicleLoad<?= (int) $v['id'] ?>">Cancel</button>
                      </div>
                    </form>
                  </div>
                <?php endif; ?>
                <?php if ($isAdmin): ?>
                  <div class="sku-edit-panel" id="vehicleEdit<?= (int) $v['id'] ?>" style="display:none;margin-top:10px;">
                    <form method="post" action="<?= $batchListUrl ?>" class="vehicle-assign-form">
                      <input type="hidden" name="action" value="edit_vehicle">
                      <input type="hidden" name="vehicle_id" value="<?= (int) $v['id'] ?>">
                      <label>Vehicle No *</label>
                      <input type="text" name="vehicle_no" required value="<?= e($v['vehicle_no']) ?>">
                      <label style="margin-top:10px;">ETA - Estimated Arrival Time *</label>
                      <input type="datetime-local" name="arrival_time" required value="<?= e(str_replace(' ', 'T', substr($v['arrival_time'], 0, 16))) ?>">
                      <label style="margin-top:10px;">Capacity (MT) *</label>
                      <input type="number" step="0.01" min="0.01" name="capacity_mt" required value="<?= e($v['capacity_mt']) ?>">
                      <div style="display:flex;gap:8px;margin-top:12px;">
                        <button type="submit" class="btn btn-sm btn-accent">Update</button>
                        <button type="button" class="btn btn-sm btn-outline sku-edit-cancel" data-panel="vehicleEdit<?= (int) $v['id'] ?>">Cancel</button>
                      </div>
                    </form>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if ($isAdmin && !$vehicles): ?>
          <button type="button" class="btn btn-sm btn-tint-blue sku-edit-toggle" data-panel="vehicleAssign<?= (int) $groupId ?>" style="margin-top:10px;"><?= tabIcon('plus') ?> Assign Vehicle</button>
          <div class="sku-edit-panel" id="vehicleAssign<?= (int) $groupId ?>" style="display:none;">
            <form method="post" action="<?= $batchListUrl ?>" class="vehicle-assign-form">
              <input type="hidden" name="action" value="assign_vehicle">
              <input type="hidden" name="batch_group_id" value="<?= (int) $groupId ?>">
              <label>Vehicle No *</label>
              <input type="text" name="vehicle_no" required>
              <label style="margin-top:10px;">ETA - Estimated Arrival Time *</label>
              <input type="datetime-local" name="arrival_time" required value="<?= date('Y-m-d\TH:i') ?>">
              <label style="margin-top:10px;">Capacity (MT) *</label>
              <input type="number" step="0.01" min="0.01" name="capacity_mt" required>
              <button type="submit" class="btn btn-sm btn-accent" style="margin-top:12px;">Assign</button>
            </form>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($isAdmin): ?>
        <div style="margin-top:14px;">
          <form method="post" action="<?= $batchListUrl ?>" data-confirm="Delete this whole batch and all its SKUs? This cannot be undone.">
            <input type="hidden" name="action" value="delete_group">
            <input type="hidden" name="batch_group_id" value="<?= (int) $groupId ?>">
            <button type="submit" class="btn btn-sm btn-danger">Delete Batch</button>
          </form>
        </div>
      <?php endif; ?>
    </div>
  </details>
<?php endforeach; ?>
<script>
(function () {
  // Toggles any collapsible edit/action panel on this page - used by both
  // roles now (Admin's Assign/Edit vehicle panels, Plant Head's Mark
  // Loaded panel).
  document.addEventListener('click', function (e) {
    var toggle = e.target.closest('.sku-edit-toggle, .sku-edit-cancel');
    if (!toggle) return;
    var panel = document.getElementById(toggle.getAttribute('data-panel'));
    if (!panel) return;
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
  });
})();
</script>
<?php if ($isAdmin): ?>
<script>
(function () {
  // Same product -> token filtering as batch-create.php's "Add Another
  // SKU" rows, applied to every "Add SKU" form on this page (one per
  // batch card, all present in the DOM even while hidden).
  var allTokens = <?= json_encode(array_map(fn($t) => ['id' => (int) $t['id'], 'value' => $t['token_value']], $batchCardsTokens)) ?>;
  var productTokenIds = <?= json_encode($batchCardsProductTokenIds) ?>;

  function tokensFor(productId) {
    return productId && productTokenIds[productId] ? productTokenIds[productId] : allTokens.map(function (t) { return t.id; });
  }

  document.querySelectorAll('.add-sku-form').forEach(function (form) {
    var productSelect = form.querySelector('.add-sku-product');
    var tokenSelect = form.querySelector('.add-sku-token');
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
  });
})();
</script>
<?php endif; ?>
