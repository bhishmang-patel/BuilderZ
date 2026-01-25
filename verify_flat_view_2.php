<?php
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance();
$flat = $db->query("SELECT id FROM flats LIMIT 1")->fetch();

if (!$flat) {
    echo "No flats to test.\n";
    exit;
}

$_GET['id'] = $flat['id'];

ob_start();
// Use relative path from root where this script runs
require __DIR__ . '/modules/masters/get_flat_details.php';
$html = ob_get_clean();

if (strpos($html, 'Property Details') !== false) {
    echo "SUCCESS: Content Generated for Flat ID " . $flat['id'] . "\n";
} else {
    echo "FAILURE: " . substr($html, 0, 100) . "\n";
}
