<?php
// Simulate HTTP Request for testing
$_GET['id'] = 7; // Use an ID likely to exist (or loop to find one)
if (!isset($_GET['id'])) { echo "ID needed"; exit; }

// Output buffer to capture HTML
ob_start();
require_once __DIR__ . '/modules/masters/get_flat_details.php';
$html = ob_get_clean();

if (strpos($html, 'Property Details') !== false) {
    echo "SUCCESS: 'Property Details' section found.\n";
} else {
    echo "FAILURE: HTML output invalid.\n";
}

if (strpos($html, 'Booking Details') !== false || strpos($html, 'Not Booked Yet') !== false) {
    echo "SUCCESS: Booking status section found.\n";
} else {
    echo "FAILURE: Booking section missing.\n";
}
echo "\n--- First 200 chars ---\n";
echo substr(strip_tags($html), 0, 200);
