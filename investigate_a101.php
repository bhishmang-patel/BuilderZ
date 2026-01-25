<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
$db = Database::getInstance();

echo "=== Investigation for Flat A-101 ===\n";

// 1. Get Flat Details
$flat = $db->query("SELECT * FROM flats WHERE flat_no = 'A-101'")->fetch();
if (!$flat) {
    die("Flat A-101 not found.\n");
}
echo "Flat ID: {$flat['id']}\n";
echo "Current Flat Status: " . strtoupper($flat['status']) . "\n";
echo "--------------------------------\n";

// 2. Get All Bookings for this Flat
echo "Booking History:\n";
$bookings = $db->query("SELECT b.*, p.name as customer_name 
                        FROM bookings b 
                        JOIN parties p ON b.customer_id = p.id 
                        WHERE b.flat_id = {$flat['id']}
                        ORDER BY b.id ASC")->fetchAll();

if (empty($bookings)) {
    echo "No bookings found for this flat.\n";
} else {
    foreach ($bookings as $b) {
        $paid_status = ($b['total_received'] >= $b['agreement_value']) ? "FULLY PAID" : "PARTIAL";
        echo "[ID: {$b['id']}] Date: {$b['booking_date']} | Customer: {$b['customer_name']} | Status: " . strtoupper($b['status']) . "\n";
        echo "       Value: {$b['agreement_value']} | Received: {$b['total_received']} ($paid_status)\n";
        echo "--------------------------------\n";
    }
}
