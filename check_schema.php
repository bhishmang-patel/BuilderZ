<?php
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance();
$cols = $db->query('SHOW COLUMNS FROM bookings')->fetchAll(PDO::FETCH_COLUMN);
print_r($cols);
