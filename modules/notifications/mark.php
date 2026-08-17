<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/functions.php';
requireRole(['Admin', 'Plant Head']);

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$userId = (int) $currentUser['id'];

if ($action === 'mark_read') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare(
            "INSERT INTO notification_states (user_id, audit_log_id, is_read) VALUES (:uid, :aid, 1)
             ON DUPLICATE KEY UPDATE is_read = 1"
        )->execute([':uid' => $userId, ':aid' => $id]);
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'dismiss') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare(
            "INSERT INTO notification_states (user_id, audit_log_id, is_dismissed) VALUES (:uid, :aid, 1)
             ON DUPLICATE KEY UPDATE is_dismissed = 1"
        )->execute([':uid' => $userId, ':aid' => $id]);
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'read_all') {
    $ids = json_decode($_POST['ids'] ?? '[]', true) ?: [];
    $stmt = $pdo->prepare(
        "INSERT INTO notification_states (user_id, audit_log_id, is_read) VALUES (:uid, :aid, 1)
         ON DUPLICATE KEY UPDATE is_read = 1"
    );
    foreach ($ids as $id) {
        $id = (int) $id;
        if ($id > 0) $stmt->execute([':uid' => $userId, ':aid' => $id]);
    }
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action']);
