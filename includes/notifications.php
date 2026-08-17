<?php
require_once __DIR__ . '/audit.php';

/** Where clicking a notification about this module should take the given role. */
function notificationUrl(string $module, bool $isAdmin): string {
    if ($isAdmin) {
        $map = [
            'raw_material_inward'        => '/modules/inward/inward-list.php',
            'production_batches'         => '/modules/packing/batch-list.php',
            'batch_groups'                => '/modules/packing/batch-list.php',
            'packing_production_updates'  => '/modules/reports/packing-report.php',
            'processing_requests'         => '/modules/reports/processing-report.php',
            'nozzle_reset_log'            => '/modules/packing/batch-list.php',
            'damage_entries'              => '/modules/reports/damage-report.php',
            'vendors'                     => '/modules/admin/vendors.php',
            'products'                    => '/modules/admin/products.php',
            'tokens'                      => '/modules/admin/tokens.php',
            'raw_materials'               => '/modules/admin/raw-materials.php',
            'users'                       => '/modules/admin/users.php',
            'tasks'                       => '/modules/admin/tasks.php',
            'app_settings'                => '/modules/admin/settings.php',
            'labour'                      => '/modules/admin/labour.php',
            'labour_attendance'           => '/modules/admin/labour-status.php',
        ];
        return $map[$module] ?? '/dashboard/admin.php';
    }
    $map = [
        'raw_material_inward'        => '/modules/inward/inward-list.php',
        'production_batches'         => '/modules/packing/batch-list.php',
        'batch_groups'               => '/modules/packing/batch-list.php',
        'packing_production_updates' => '/modules/packing/batch-list.php',
        'processing_requests'        => '/modules/processing/processing-list.php',
        'nozzle_reset_log'           => '/modules/packing/batch-list.php',
        'tasks'                      => '/modules/tasks/task-list.php',
        'labour_attendance'          => '/modules/labour/attendance.php',
    ];
    return $map[$module] ?? '/dashboard/plant-head.php';
}

/**
 * Recent activity across the whole app, for the notification bell + the
 * one-line ticker on both dashboards. Reuses the audit trail (every
 * significant create/update already calls logAudit()) and the same
 * plain-English descriptions the Audit Log page shows, so there's one
 * source of truth for "what happened" instead of a second logging system.
 * $isAdmin picks a role-safe redirect target - Plant Head can't land on an
 * Admin-only master page. $userId is whichever user is viewing the bell -
 * read/dismissed state is per-user (notification_states), not global, so
 * dismissing one never touches the underlying audit_logs row.
 */
function getRecentNotifications(PDO $pdo, int $limit, bool $isAdmin, int $userId): array {
    $stmt = $pdo->prepare(
        "SELECT a.*, u.name AS user_name, ns.is_read
         FROM audit_logs a
         JOIN users u ON u.id = a.user_id
         LEFT JOIN notification_states ns ON ns.audit_log_id = a.id AND ns.user_id = :uid
         WHERE ns.is_dismissed IS NULL OR ns.is_dismissed = 0
         ORDER BY a.id DESC LIMIT :lim"
    );
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $lookups = buildAuditLookups($pdo);
    $notifications = [];
    foreach ($rows as $r) {
        $described = describeAuditLog($r, $lookups);
        $notifications[] = [
            'id'         => (int) $r['id'],
            'summary'    => $described['summary'],
            'module'     => humanAuditModule($r['module']),
            'user_name'  => $r['user_name'],
            'created_at' => $r['created_at'],
            'url'        => APP_URL . notificationUrl($r['module'], $isAdmin),
            'is_read'    => !empty($r['is_read']),
        ];
    }
    return $notifications;
}
