<?php
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance();
$stmt = $db->query("SELECT DISTINCT unit_type FROM flats");
$types = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Types: " . implode(', ', $types);
?>
