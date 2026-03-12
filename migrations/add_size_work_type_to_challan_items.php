<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance();

echo "Starting migration: Add 'size' and 'work_type' to 'challan_items'\n";

try {
    // Check if `size` column exists
    $resultSize = $db->query("SHOW COLUMNS FROM `challan_items` LIKE 'size'");
    if ($resultSize->rowCount() == 0) {
        $db->query("ALTER TABLE `challan_items` ADD COLUMN `size` VARCHAR(100) NULL AFTER `unit`");
        echo "Successfully added 'size' column to 'challan_items'.\n";
    } else {
        echo "Column 'size' already exists in 'challan_items'.\n";
    }

    // Check if `work_type` column exists
    $resultWorkType = $db->query("SHOW COLUMNS FROM `challan_items` LIKE 'work_type'");
    if ($resultWorkType->rowCount() == 0) {
        $db->query("ALTER TABLE `challan_items` ADD COLUMN `work_type` VARCHAR(100) NULL AFTER `size`");
        echo "Successfully added 'work_type' column to 'challan_items'.\n";
    } else {
        echo "Column 'work_type' already exists in 'challan_items'.\n";
    }

    echo "Migration completed successfully.\n";

} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
