<?php
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance();
$columns = $db->query("DESCRIBE expenses")->fetchAll(PDO::FETCH_COLUMN);
echo "Columns in expenses table:\n";
print_r($columns);
?>
