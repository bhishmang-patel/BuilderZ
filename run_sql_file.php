<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getInstance();
    $sql = file_get_contents(__DIR__ . '/migrations/create_notifications_table.sql');
    
    // Split by semicolon? Or just run it. 
    // PDO might not support multiple statements in one go depending on driver, but usually does if configured.
    // Safest is to split.
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            $db->query($stmt);
            echo "Executed: " . substr($stmt, 0, 50) . "...\n";
        }
    }
    echo "SQL execution completed.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
