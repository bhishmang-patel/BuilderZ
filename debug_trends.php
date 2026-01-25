<?php
require_once __DIR__ . '/includes/ReportService.php';
$db = Database::getInstance();

echo "--- BOOKING DATES ---\n";
$dates = $db->query("SELECT booking_date, agreement_value FROM bookings WHERE status = 'active' ORDER BY booking_date DESC LIMIT 20")->fetchAll();
foreach ($dates as $d) {
    echo $d['booking_date'] . " - " . $d['agreement_value'] . "\n";
}

echo "--- PAYMENT DATES ---\n";
$dates = $db->query("SELECT payment_date, amount, payment_type FROM payments WHERE payment_type = 'customer_receipt' ORDER BY payment_date DESC LIMIT 20")->fetchAll();
foreach ($dates as $d) {
    echo $d['payment_date'] . " - " . $d['amount'] . "\n";
}
