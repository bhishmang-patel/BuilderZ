<?php
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance();
    
    // Check if column exists
    $check = $db->query("SHOW COLUMNS FROM materials LIKE 'min_limit'")->fetch();
    
    if (!$check) {
        $db->exec("ALTER TABLE materials ADD COLUMN min_limit DECIMAL(10,2) DEFAULT 10.00 AFTER unit");
        echo "Successfully added 'min_limit' column to 'materials' table.";
    } else {
        echo "Column 'min_limit' already exists.";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
