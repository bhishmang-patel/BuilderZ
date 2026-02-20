<?php
// debug_db_check.php
// Diagnostic script to check database connection and row counts
// Writes output to debug_output.txt

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$output = "Diagnostic Report\n";
$output .= "=================\n";
$output .= "Time: " . date('Y-m-d H:i:s') . "\n";
$output .= "DB Host: " . DB_HOST . "\n";
$output .= "DB Name: " . DB_NAME . "\n";
$output .= "DB User: " . DB_USER . "\n";
$output .= "-----------------\n";

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $output .= "[OK] Database connection successful.\n";

    // List Tables
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($tables)) {
        $output .= "[WARN] No tables found in database '" . DB_NAME . "'.\n";
    } else {
        $output .= "[INFO] Found " . count($tables) . " tables:\n";
        foreach ($tables as $table) {
            $countStmt = $conn->query("SELECT COUNT(*) FROM `$table`");
            $count = $countStmt->fetchColumn();
            $output .= sprintf(" - %-20s : %d rows\n", $table, $count);
        }
    }

} catch (Exception $e) {
    $output .= "[ERROR] " . $e->getMessage() . "\n";
}
$output .= "\nEnd of Report.\n";

file_put_contents(__DIR__ . '/debug_output.txt', $output);
echo "Output written to debug_output.txt\n";
