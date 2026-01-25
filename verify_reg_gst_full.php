<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/BookingService.php';

$db = Database::getInstance();
$service = new BookingService();

echo "--- Schema Check ---\n";
$cols = $db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'builderz_erp' AND TABLE_NAME = 'bookings'")->fetchAll(PDO::FETCH_COLUMN);
// Filter for relevant columns
$relevant = ['registration_amount', 'gst_amount'];
foreach ($relevant as $col) {
    if (in_array($col, $cols)) {
        echo "Column '$col' EXISTS.\n";
    } else {
        echo "Column '$col' MISSING!\n";
    }
}

echo "\n--- Functional Test ---\n";
// Find a valid project and flat
$project = $db->query("SELECT id FROM projects WHERE status = 'active' LIMIT 1")->fetch();
$flat = $db->query("SELECT id FROM flats WHERE status = 'available' AND project_id = ?", [$project['id']])->fetch();

if (!$project || !$flat) {
    echo "Cannot run test: No active project/flat found.\n";
    exit;
}

$data = [
    'customer_name' => 'RegGST Test ' . time(),
    'mobile' => '8888888888',
    'email' => 'reggst@example.com',
    'address' => 'Test Address',
    'project_id' => $project['id'],
    'flat_id' => $flat['id'],
    'agreement_value' => 100000,
    'booking_date' => date('Y-m-d'),
    'stamp_duty_registration' => 7000,
    'registration_amount' => 1000, // 1%
    'gst_amount' => 5000,          // 5%
    'development_charge' => 0,
    'parking_charge' => 0,
    'society_charge' => 0,
    'rate' => 1000
];

try {
    $result = $service->createBooking($data, 1);
    
    if ($result['success']) {
        echo "Booking Created. ID: " . $result['booking_id'] . "\n";
        $booking = $db->query("SELECT * FROM bookings WHERE id = ?", [$result['booking_id']])->fetch();
        
        echo "Verification:\n";
        echo "Reg Amount: " . $booking['registration_amount'] . " (Expected: 1000.00)\n";
        echo "GST Amount: " . $booking['gst_amount'] . " (Expected: 5000.00)\n";
        
        if (floatval($booking['registration_amount']) == 1000 && floatval($booking['gst_amount']) == 5000) {
            echo "SUCCESS: Values match.\n";
        } else {
            echo "FAILURE: Values do not match.\n";
        }
    } else {
        echo "Booking Failed: " . $result['message'] . "\n";
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
