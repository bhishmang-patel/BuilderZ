<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

echo "Diagnostics Started...\n";

try {
    $db = Database::getInstance();
    
    // Check expenses columns
    echo "Checking 'expenses' table columns:\n";
    $stm = $db->query("SHOW COLUMNS FROM expenses");
    $columns = $stm->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('project_id', $columns)) {
        echo "[OK] 'project_id' column found in 'expenses'.\n";
    } else {
        echo "[ERROR] 'project_id' column NOT found in 'expenses'.\n";
    }

    // Check projects table
    echo "Checking 'projects' table:\n";
    $stm = $db->query("SELECT count(*) FROM projects");
    echo "[OK] Projects table accessible. Count: " . $stm->fetchColumn() . "\n";

} catch (Exception $e) {
    echo "[CRITICAL ERROR] " . $e->getMessage() . "\n";
}
