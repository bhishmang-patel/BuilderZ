<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getInstance();
    
    // Check if columns exist first to avoid errors
    $columns = $db->query("DESCRIBE contractor_bills")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('approved_by', $columns)) {
        $db->query("ALTER TABLE contractor_bills ADD COLUMN approved_by INT(11) DEFAULT NULL AFTER status");
        echo "Added approved_by column.\n";
    }
    
    if (!in_array('approved_at', $columns)) {
        $db->query("ALTER TABLE contractor_bills ADD COLUMN approved_at DATETIME DEFAULT NULL AFTER approved_by");
        echo "Added approved_at column.\n";
    }
    
    echo "Migration completed.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
