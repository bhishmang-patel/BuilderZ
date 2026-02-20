<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance();

$tables = ['payments' => 'payment_date', 'bills' => 'bill_date', 'expenses' => 'date', 'investments' => 'investment_date', 'bookings' => 'booking_date'];

foreach ($tables as $table => $dateCol) {
    try {
        $stmt = $db->query("SELECT COUNT(*) as count, MIN($dateCol) as min_date, MAX($dateCol) as max_date FROM $table");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Table: $table\n";
        echo "Count: " . $result['count'] . "\n";
        echo "Min Date: " . $result['min_date'] . "\n";
        echo "Max Date: " . $result['max_date'] . "\n";
        echo "-------------------------\n";
    } catch (Exception $e) {
        echo "Error checking table $table: " . $e->getMessage() . "\n";
    }
}
