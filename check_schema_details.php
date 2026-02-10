<?php
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance();
$stmt = $db->query("DESCRIBE expenses");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo "Field: {$col['Field']}, Type: {$col['Type']}, Null: {$col['Null']}, Default: {$col['Default']}\n";
}
?>
