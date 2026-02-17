<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance();
$sql = file_get_contents(__DIR__ . '/config/migrations/add_unique_gst_constraint.sql');

try {
    $db->query($sql);
    echo "Migration successful: Unique constraint added to gst_number.";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage();
}
