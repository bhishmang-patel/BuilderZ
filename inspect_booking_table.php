<?php
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance();
try {
    $columns = $db->query("SHOW COLUMNS FROM bookings")->fetchAll();
    echo "Columns in bookings table:\n";
    foreach ($columns as $col) {
        echo $col['Field'] . " - " . $col['Type'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
