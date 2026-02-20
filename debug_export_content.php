<?php
// debug_export_content.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance();
$month = '2026-02';
$start_date = $month . '-01';
$end_date = date('Y-m-t', strtotime($start_date));

echo "Debug Export Content for $month\n";
echo "Range: $start_date to $end_date\n\n";

// 1. SALES
echo "--- 1. SALES ---\n";
$sales_sql = "SELECT p.payment_date, p.amount FROM payments p 
    JOIN parties pt ON p.party_id = pt.id
    LEFT JOIN bookings b ON p.reference_id = b.id AND p.reference_type = 'booking'
    WHERE p.payment_type = 'customer_receipt' 
    AND p.payment_date BETWEEN ? AND ?";
$sales = $db->query($sales_sql, [$start_date, $end_date])->fetchAll(PDO::FETCH_ASSOC);
echo "Count: " . count($sales) . "\n";
print_r($sales);

// 2. EXPENSES
echo "\n--- 2. EXPENSES ---\n";
$exp_sql = "SELECT e.date, e.amount, ec.name as category FROM expenses e
    LEFT JOIN expense_categories ec ON e.category_id = ec.id
    WHERE e.date BETWEEN ? AND ?";
$exps = $db->query($exp_sql, [$start_date, $end_date])->fetchAll(PDO::FETCH_ASSOC);
echo "Count: " . count($exps) . "\n";
print_r($exps);

// 3. BOOKINGS
echo "\n--- 3. BOOKINGS ---\n";
// Original Query uses JOINs
$booking_sql = "SELECT b.booking_date, b.agreement_value 
    FROM bookings b
    JOIN parties p ON b.customer_id = p.id
    JOIN flats f ON b.flat_id = f.id
    JOIN projects pr ON b.project_id = pr.id
    WHERE b.booking_date BETWEEN ? AND ?";
$bookings = $db->query($booking_sql, [$start_date, $end_date])->fetchAll(PDO::FETCH_ASSOC);
echo "Count (with JOINs): " . count($bookings) . "\n";
print_r($bookings);

// Check if raw bookings exist
$raw_booking_sql = "SELECT * FROM bookings WHERE booking_date BETWEEN ? AND ?";
$raw_bookings = $db->query($raw_booking_sql, [$start_date, $end_date])->fetchAll(PDO::FETCH_ASSOC);
echo "Raw Bookings Count: " . count($raw_bookings) . "\n";
if (count($bookings) < count($raw_bookings)) {
    echo "[WARN] Some bookings are missing due to JOINs!\n";
    print_r($raw_bookings);
}
