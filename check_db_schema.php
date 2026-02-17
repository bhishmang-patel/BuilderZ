<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getInstance();
    echo "BILLS TABLE COLUMNS:\n";
    $stmt = $db->query("DESCRIBE bills");
    print_r($stmt->fetchAll(PDO::FETCH_COLUMN));

    echo "\nCHALLANS TABLE COLUMNS:\n";
    $stmt = $db->query("DESCRIBE challans");
    print_r($stmt->fetchAll(PDO::FETCH_COLUMN));

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
