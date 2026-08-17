<?php
require_once __DIR__ . '/constants.php';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed. Check config/constants.php. (' . $e->getMessage() . ')');
}

// Keep MySQL's own NOW()/CURRENT_TIMESTAMP columns in IST too, independent
// of whatever timezone the MySQL server itself is configured with.
$pdo->exec("SET time_zone = '+05:30'");
