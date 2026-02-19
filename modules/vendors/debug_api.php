<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

$db = Database::getInstance();
// Get first vendor
$vendor = $db->query("SELECT id, name FROM parties WHERE type = 'vendor' OR vendor_type IS NOT NULL LIMIT 1")->fetch();

if (!$vendor) {
    echo "No vendors found.\n";
    exit;
}

echo "Vendor: " . $vendor['name'] . " (ID: " . $vendor['id'] . ")\n";

// Fetch bills (simulate API logic)
$sql = "SELECT id, bill_no, bill_date, amount, status FROM bills WHERE party_id = ? LIMIT 5";
$bills = $db->query($sql, [$vendor['id']])->fetchAll();

foreach ($bills as $bill) {
    echo "Bill #{$bill['bill_no']} - Status: [{$bill['status']}]\n";
    
    // Test getStatusClass logic
    $class = 'orange'; // default
    switch ($bill['status']) {
        case 'approved': $class = 'blue'; break;
        case 'paid': $class = 'green'; break;
        case 'rejected': $class = 'red'; break;
    }
    echo "  -> Class: $class\n";
}
