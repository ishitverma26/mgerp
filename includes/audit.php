<?php
/**
 * Writes one row to audit_logs. Call this after any create/update/critical
 * action - especially target changes, batch reopening, and FIFO overrides.
 *
 * $old / $new should be plain PHP arrays (or null) - they get JSON encoded.
 */
function logAudit(PDO $pdo, $userId, $action, $module, $recordId, $old = null, $new = null) {
    $stmt = $pdo->prepare(
        "INSERT INTO audit_logs (user_id, action, module, record_id, old_value, new_value)
         VALUES (:user_id, :action, :module, :record_id, :old_value, :new_value)"
    );
    $stmt->execute([
        ':user_id'    => $userId,
        ':action'     => $action,
        ':module'     => $module,
        ':record_id'  => $recordId,
        ':old_value'  => $old === null ? null : json_encode($old),
        ':new_value'  => $new === null ? null : json_encode($new),
    ]);
}

/**
 * Small id => label lookups so describeAuditLog() can turn raw IDs in the
 * old/new JSON into plain names instead of "#12". Shared by the Audit Log
 * page and the notification bell, which both describe audit_logs rows.
 */
function buildAuditLookups(PDO $pdo): array {
    $productLookup = [];
    foreach ($pdo->query("SELECT id, name, size_kg FROM products") as $p) {
        $productLookup[(int) $p['id']] = $p['name'] . ' ' . $p['size_kg'] . 'KG';
    }
    return [
        'tokens'        => $pdo->query("SELECT id, token_value FROM tokens")->fetchAll(PDO::FETCH_KEY_PAIR),
        'products'      => $productLookup,
        'vendors'       => $pdo->query("SELECT id, name FROM vendors")->fetchAll(PDO::FETCH_KEY_PAIR),
        'raw_materials' => $pdo->query("SELECT id, name FROM raw_materials")->fetchAll(PDO::FETCH_KEY_PAIR),
        'users'         => $pdo->query("SELECT id, name FROM users")->fetchAll(PDO::FETCH_KEY_PAIR),
    ];
}

/** Friendly module name for the Audit Log page - falls back to a title-cased table name. */
function humanAuditModule(string $module): string {
    $map = [
        'production_batches'         => 'Batch SKU',
        'packing_production_updates' => 'Packing Update',
        'raw_material_inward'        => 'Raw Material Inward',
        'products'                   => 'Product',
        'app_settings'                => 'Company Settings',
        'users'                       => 'User',
        'raw_materials'               => 'Raw Material',
        'damage_entries'              => 'Damage Entry',
        'vendors'                     => 'Vendor',
        'tokens'                      => 'Token',
        'processing_requests'         => 'Processing',
        'nozzle_reset_log'            => 'Nozzle Reset',
        'batch_groups'                => 'Batch',
        'tasks'                       => 'Task',
        'labour'                      => 'Labour',
        'labour_attendance'           => 'Labour Attendance',
    ];
    return $map[$module] ?? ucwords(str_replace('_', ' ', $module));
}

/** Friendly label for a raw JSON field name, used in the changed-fields list. */
function humanAuditField(string $field): string {
    $map = [
        'target_bags' => 'Target (Bags)', 'token_id' => 'Token', 'product_id' => 'Product',
        'status' => 'Status', 'gross_weight' => 'Gross Weight (MT)', 'jumbo_qty' => 'Jumbo Qty',
        'lot_no' => 'Lot No', 'batch_no' => 'Batch No', 'freight_payment_status' => 'Freight Payment',
        'material_payment_status' => 'Material Payment', 'company_name' => 'Company Name',
        'name' => 'Name', 'username' => 'Username', 'role_id' => 'Role', 'unit' => 'Unit',
        'qty' => 'Quantity', 'reason' => 'Reason', 'contact_no' => 'Contact No', 'address' => 'Address',
        'gst_no' => 'GST No', 'token_value' => 'Token Value', 'size_kg' => 'Size (KG)',
        'raw_material_id' => 'Raw Material', 'mode' => 'Mode', 'requirement_jumbo' => 'Jumbo Required',
        'batch_id' => 'Batch', 'left' => 'Left Nozzle', 'right' => 'Right Nozzle', 'total_good' => 'Total Good',
        'nozzle' => 'Nozzle', 'reset_datetime' => 'Reset Time', 'action_status' => 'Action Status',
        'carried_forward_qty' => 'Carried Forward Qty', 'carried_forward_from_batch_no' => 'Carried Forward From',
    ];
    return $map[$field] ?? ucwords(str_replace('_', ' ', $field));
}

/** Shared phrasing for the common create/update/status_change/delete shape used by most masters. */
function describeAuditCrud(string $action, string $label, ?array $old, ?array $new, callable $nameOf, ?string $fallbackName = null): string {
    switch ($action) {
        case 'create': return "$label added - " . $nameOf($new ?? []) . '.';
        case 'update': return "$label updated - " . $nameOf($new ?? []) . '.';
        case 'delete': return "$label deleted - was " . $nameOf($old ?? []) . '.';
        case 'status_change':
            $name = $fallbackName ? " ($fallbackName)" : '';
            return "$label$name status changed: " . ucfirst($old['status'] ?? '?') . ' -> ' . ucfirst($new['status'] ?? '?') . '.';
        default: return "$label $action.";
    }
}

/**
 * Turns one audit_logs row into a plain-English summary sentence plus a
 * human-labelled list of what changed, instead of showing the raw
 * action/module/record_id and JSON blobs on the Audit Log page.
 * $lookups holds id => label maps: 'tokens', 'products', 'vendors',
 * 'raw_materials', 'users' - built once by the caller (cheap master tables).
 */
function describeAuditLog(array $row, array $lookups): array {
    $old = $row['old_value'] ? json_decode($row['old_value'], true) : null;
    $new = $row['new_value'] ? json_decode($row['new_value'], true) : null;
    $module = $row['module'];
    $action = $row['action'];
    $rid = (int) $row['record_id'];

    $tokenName = fn($id) => $lookups['tokens'][(int) $id] ?? "Token #$id";
    $productName = fn($id) => $lookups['products'][(int) $id] ?? "Product #$id";
    $userLabel = fn($id) => $lookups['users'][(int) $id] ?? "User #$id";

    $summary = null;

    switch ($module) {
        case 'production_batches':
            if ($action === 'create') {
                $summary = 'New SKU added' . (isset($new['batch_no']) ? " to Batch {$new['batch_no']}" : '')
                    . (isset($new['product_id']) ? ' - ' . $productName($new['product_id']) : '')
                    . (isset($new['target_bags']) ? ', target ' . number_format($new['target_bags']) . ' bags' : '') . '.';
            } elseif ($action === 'update') {
                $parts = [];
                if (($old['target_bags'] ?? null) !== ($new['target_bags'] ?? null)) {
                    $parts[] = 'target ' . number_format($old['target_bags'] ?? 0) . ' -> ' . number_format($new['target_bags'] ?? 0) . ' bags';
                }
                if (($old['token_id'] ?? null) !== ($new['token_id'] ?? null)) {
                    $parts[] = 'token ' . $tokenName($old['token_id'] ?? 0) . ' -> ' . $tokenName($new['token_id'] ?? 0);
                }
                $summary = 'SKU updated' . ($parts ? ' - ' . implode(', ', $parts) : '') . '.';
            } elseif ($action === 'delete') {
                $summary = 'SKU removed from a batch' . (isset($old['target_bags']) ? ' (was target ' . number_format($old['target_bags']) . ' bags)' : '') . '.';
            } elseif ($action === 'auto_complete') {
                $summary = 'Batch SKU automatically marked Completed (target reached).';
            } elseif ($action === 'reopen_batch') {
                $summary = 'Batch SKU reopened' . (isset($new['target_bags']) ? ' - new target ' . number_format($new['target_bags']) . ' bags' : '') . '.';
            } elseif ($action === 'add_sku_to_batch') {
                $summary = 'SKU added' . (isset($new['batch_no']) ? " to Batch {$new['batch_no']}" : '')
                    . (isset($new['product_id']) ? ' - ' . $productName($new['product_id']) : '')
                    . (isset($new['target_bags']) ? ', target ' . number_format($new['target_bags']) . ' bags' : '') . '.';
            } elseif ($action === 'carry_forward') {
                $summary = 'Live Stock carried forward - ' . number_format($new['carried_forward_qty'] ?? 0)
                    . ' bags from Batch ' . ($new['carried_forward_from_batch_no'] ?? '?') . '.';
            }
            break;

        case 'packing_production_updates':
            if ($action === 'create') {
                $summary = 'Packing production logged - Left ' . number_format($new['left'] ?? 0)
                    . ', Right ' . number_format($new['right'] ?? 0) . ', Total ' . number_format($new['total_good'] ?? 0) . ' bags.';
            }
            break;

        case 'raw_material_inward':
            if ($action === 'create') {
                $summary = 'Inward recorded - Lot ' . ($new['lot_no'] ?? '?') . ', ' . number_format($new['gross_weight'] ?? 0, 3)
                    . ' MT, ' . number_format($new['jumbo_qty'] ?? 0) . ' Jumbo.';
            } elseif ($action === 'payment_update') {
                $summary = 'Payment status updated' . (isset($old['lot_no']) ? ' for Lot ' . $old['lot_no'] : '')
                    . ' - Freight: ' . ucfirst($new['freight_payment_status'] ?? '-') . ', Material: ' . ucfirst($new['material_payment_status'] ?? '-') . '.';
            }
            break;

        case 'products':
            $summary = describeAuditCrud($action, 'Product', $old, $new,
                fn($v) => ($v['name'] ?? '?') . (isset($v['size_kg']) ? ' ' . $v['size_kg'] . 'KG' : ''),
                $lookups['products'][$rid] ?? null);
            break;

        case 'raw_materials':
            $summary = describeAuditCrud($action, 'Raw material', $old, $new, fn($v) => $v['name'] ?? '?', $lookups['raw_materials'][$rid] ?? null);
            break;

        case 'vendors':
            $summary = describeAuditCrud($action, 'Vendor', $old, $new, fn($v) => $v['name'] ?? '?', $lookups['vendors'][$rid] ?? null);
            break;

        case 'tokens':
            $summary = describeAuditCrud($action, 'Token', $old, $new, fn($v) => $v['token_value'] ?? '?', $lookups['tokens'][$rid] ?? null);
            break;

        case 'users':
            if ($action === 'change_password') {
                $summary = 'Password changed for ' . $userLabel($rid) . '.';
            } else {
                $summary = describeAuditCrud($action, 'User', $old, $new,
                    fn($v) => ($v['name'] ?? '?') . (isset($v['username']) ? ' (' . $v['username'] . ')' : ''),
                    $lookups['users'][$rid] ?? null);
            }
            break;

        case 'app_settings':
            $summary = 'Company name changed: ' . ($old['company_name'] ?? '?') . ' -> ' . ($new['company_name'] ?? '?') . '.';
            break;

        case 'damage_entries':
            if ($action === 'create') {
                $summary = 'Damage recorded - ' . number_format($new['qty'] ?? 0) . ' units' . (isset($new['reason']) ? ', reason: ' . $new['reason'] : '') . '.';
            } elseif ($action === 'send_reprocessing') {
                $summary = 'Damage sent to Reprocessing - ' . number_format($new['qty'] ?? 0) . ' units.';
            } elseif ($action === 'send_garbage') {
                $summary = 'Damage marked as Garbage - ' . number_format($new['qty'] ?? 0) . ' units.';
            }
            break;

        case 'processing_requests':
            if ($action === 'create') {
                $mode = strtoupper($new['mode'] ?? 'fifo');
                $summary = 'Processing logged - ' . $mode . (isset($new['requirement_jumbo']) ? ' (' . number_format($new['requirement_jumbo']) . ' Jumbo)' : ' (full lot)') . '.';
            }
            break;

        case 'nozzle_reset_log':
            if ($action === 'create') {
                $summary = ucfirst($new['nozzle'] ?? '') . ' nozzle reset logged.';
            }
            break;

        case 'batch_groups':
            if ($action === 'delete') {
                $summary = 'Batch deleted.';
            }
            break;

        case 'tasks':
            $summary = describeAuditCrud($action, 'Task', $old, $new, fn($v) => $v['title'] ?? '?');
            break;

        case 'labour':
            $summary = describeAuditCrud($action, 'Labour', $old, $new, fn($v) => $v['name'] ?? '?');
            break;

        case 'labour_attendance':
            if ($action === 'mark_attendance') {
                $summary = 'Attendance marked for ' . number_format($new['count'] ?? 0) . ' labour'
                    . (($new['count'] ?? 0) === 1 ? '' : 's') . (isset($new['date']) ? ' on ' . formatDate($new['date']) : '') . '.';
            }
            break;
    }

    if ($summary === null) {
        $verbs = ['create' => 'created', 'update' => 'updated', 'delete' => 'deleted', 'status_change' => 'changed status of'];
        $verb = $verbs[$action] ?? str_replace('_', ' ', $action);
        $summary = ucfirst($verb) . ' ' . humanAuditModule($module) . ' #' . $rid . '.';
    }

    $changes = [];
    if (is_array($old) || is_array($new)) {
        $keys = array_unique(array_merge(array_keys($old ?? []), array_keys($new ?? [])));
        foreach ($keys as $k) {
            $ov = $old[$k] ?? null;
            $nv = $new[$k] ?? null;
            if ($ov === $nv) continue;
            if ($k === 'token_id') { $ov = $ov !== null ? $tokenName($ov) : '-'; $nv = $nv !== null ? $tokenName($nv) : '-'; }
            if ($k === 'product_id') { $ov = $ov !== null ? $productName($ov) : '-'; $nv = $nv !== null ? $productName($nv) : '-'; }
            $changes[] = ['label' => humanAuditField($k), 'old' => $ov ?? '-', 'new' => $nv ?? '-'];
        }
    }

    return ['summary' => $summary, 'changes' => $changes];
}
