<?php
require_once __DIR__ . '/../../config/db.php';

// Public, read-only reference lookup - see geo-states.php for the same
// pattern one level up (country -> state).
header('Content-Type: application/json');

$stateId = (int) ($_GET['state_id'] ?? 0);
if ($stateId <= 0) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, name FROM geo_cities WHERE state_id = :sid ORDER BY name");
    $stmt->execute([':sid' => $stateId]);
    echo json_encode($stmt->fetchAll());
} catch (PDOException $e) {
    echo json_encode([]);
}
