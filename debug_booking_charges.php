<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/BookingService.php';

$db = Database::getInstance();
$service = new BookingService();

echo "Testing Booking Creation with Deduction Charges...\n";

// Mock Data
$data = [
    'customer_name' => 'Debug User ' . time(),
    'mobile' => '9999999999',
    'email' => 'debug@example.com',
    'address' => 'Debug Address',
    'project_id' => 1, // Assuming project ID 1 exists
    'flat_id' => 1,    // Assuming flat ID 1 exists, might need adjustment if booked
    'agreement_value' => 100000,
    'booking_date' => date('Y-m-d'),
    'development_charge' => 5000,
    'parking_charge' => 2000,
    'society_charge' => 3000,
    'stamp_duty_registration' => 7000,
    'rate' => 1000
];

// We need a valid flat that is available for the test to pass strictly, 
// but BookingService might not validate 'available' status on insert, only update.
// Let's try to insert.

try {
    // Find a valid project
    $project = $db->query("SELECT id FROM projects WHERE status = 'active' LIMIT 1")->fetch();
    if ($project) {
        $data['project_id'] = $project['id'];
        echo "Using Project ID: " . $project['id'] . "\n";
    } else {
        echo "No active project found, forcing Project ID 1\n";
        $data['project_id'] = 1;
    }

    // Find an available flat first to make it realistic
    $flat = $db->query("SELECT id FROM flats WHERE status = 'available' AND project_id = ?", [$data['project_id']])->fetch();
    if ($flat) {
        $data['flat_id'] = $flat['id'];
        echo "Using Flat ID: " . $flat['id'] . "\n";
    } else {
        echo "No available flats found, forcing Flat ID 1 (might fail logic but testing DB columns)\n";
        $data['flat_id'] = 1;
    }

    $result = $service->createBooking($data, 1); // User ID 1

    if ($result['success']) {
        echo "Booking Created Successfully. ID: " . $result['booking_id'] . "\n";
        
        // Verify Data
        $booking = $db->query("SELECT * FROM bookings WHERE id = ?", [$result['booking_id']])->fetch();
        echo "Verifying saved data:\n";
        echo "Development Charge: " . $booking['development_charge'] . "\n";
        echo "Parking Charge: " . $booking['parking_charge'] . "\n";
        echo "Society Charge: " . $booking['society_charge'] . "\n";
        
    } else {
        echo "Booking Creation Failed: " . $result['message'] . "\n";
    }

} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
