<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance();
$duplicates = $db->query("
    SELECT gst_number, COUNT(*) as count 
    FROM parties 
    WHERE gst_number IS NOT NULL AND gst_number != '' 
    GROUP BY gst_number 
    HAVING COUNT(*) > 1
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($duplicates)) {
    echo "No duplicate GST numbers found.";
} else {
    echo "Duplicate GST numbers found:\n";
    print_r($duplicates);
}
