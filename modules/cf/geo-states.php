<?php
require_once __DIR__ . '/../../config/db.php';

// Public, read-only reference lookup (same public context as onboard.php -
// no auth needed to look up which states belong to a country). Returns
// [{id, name}, ...] for the given country_id, or an empty array if the
// world geo tables aren't set up yet.
header('Content-Type: application/json');

$countryId = (int) ($_GET['country_id'] ?? 0);
if ($countryId <= 0) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, name FROM geo_states WHERE country_id = :cid ORDER BY name");
    $stmt->execute([':cid' => $countryId]);
    echo json_encode($stmt->fetchAll());
} catch (PDOException $e) {
    echo json_encode([]);
}
