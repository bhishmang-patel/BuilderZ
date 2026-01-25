<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../vendor/simplexlsxgen/SimpleXLSXGen.php'; // Include Library

use Shuchkin\SimpleXLSXGen;

// Ensure user is logged in
requireAuth();

$db = Database::getInstance();

// Prepare data array for Excel
$data = [];

// Header Style: Bold, Center, Green Background, Font Size
$headerStyle = '<style bgcolor="#36DA78" font-size="14" font-family="Times New Roman"><b><center>';
$headerEnd = '</center></b></style>';

// Set Column Headers with Styling
$data[] = [
    $headerStyle . 'Project' . $headerEnd,
    $headerStyle . 'Flat No' . $headerEnd,
    $headerStyle . 'Area (sqft)' . $headerEnd,
    $headerStyle . 'Customer Name' . $headerEnd,
    $headerStyle . 'Mobile' . $headerEnd,
    $headerStyle . 'Referred By' . $headerEnd,
    $headerStyle . 'Rate' . $headerEnd,
    $headerStyle . 'Agreement Value' . $headerEnd,
    $headerStyle . 'Received' . $headerEnd,
    $headerStyle . 'Pending' . $headerEnd,
    $headerStyle . 'Status' . $headerEnd,
    $headerStyle . 'Booking Date' . $headerEnd
];

// Fetch Data
$sql = "SELECT b.*, 
               f.flat_no, f.area_sqft,
               p.name as customer_name,
               p.mobile as customer_mobile,
               pr.project_name
        FROM bookings b
        JOIN flats f ON b.flat_id = f.id
        JOIN parties p ON b.customer_id = p.id
        JOIN projects pr ON b.project_id = pr.id
        ORDER BY b.created_at DESC";

$stmt = $db->query($sql);

while ($booking = $stmt->fetch(PDO::FETCH_ASSOC)) {
    
    // Status Logic
    $status = ucfirst($booking['status']);
    if ($booking['total_pending'] <= 0 && $booking['status'] == 'active') {
        $status .= ' (Paid)';
    }

    $data[] = [
        $booking['project_name'],
        $booking['flat_no'],
        $booking['area_sqft'],
        $booking['customer_name'],
        // Use regex to ensure mobile is treated as string if needed, or SimpleXLSXGen might handle strings correctly if they don't look like numbers. 
        // But to be safe against scientific notation for large numbers, we can use an explicit string approach or space padding.
        // SimpleXLSXGen handles strings starting with ' ' or non-numeric correctly. 
        // Best approach for SimpleXLSXGen to force string for numbers is often prepending a space or using a specific format.
        // However, looking at the library code, text which is not numeric is stored as string.
        // Phone numbers can be interpreted as numbers. 
        // We will cast to string explicitly.
        (string)$booking['customer_mobile'], 
        $booking['referred_by'] ?? '-',
        $booking['rate'],
        $booking['agreement_value'],
        $booking['total_received'],
        $booking['total_pending'],
        $status,
        date('d-m-Y', strtotime($booking['booking_date']))
    ];
}

// Generate Filename
$filename = 'bookings_' . date('Y-m-d') . '.xlsx';

// Download
$xlsx = SimpleXLSXGen::fromArray($data);
$xlsx->downloadAs($filename);
exit;
