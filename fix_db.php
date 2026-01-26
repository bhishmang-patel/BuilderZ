<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance();

echo "<h1>Updating Database Schema</h1>";

try {
    // 1. Get current schema to be safe
    $stmt = $db->query("SHOW COLUMNS FROM payments LIKE 'payment_type'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Current payment_type check: " . $col['Type'] . "<br>";
    
    // 2. Alter payment_type
    // We need to keep existing values and add new ones
    // Expected existing: 'customer_receipt','vendor_payment','labour_payment','customer_refund'
    // New: 'vendor_bill_payment'
    $sql = "ALTER TABLE payments MODIFY COLUMN payment_type ENUM('customer_receipt', 'vendor_payment', 'labour_payment', 'customer_refund', 'vendor_bill_payment') NOT NULL";
    $db->query($sql);
    echo "Updated payment_type ENUM.<br>";
    
    // 3. Alter reference_type
    // Expected existing: 'booking','challan'
    // New: 'bill'
    $sql = "ALTER TABLE payments MODIFY COLUMN reference_type ENUM('booking', 'challan', 'bill') NOT NULL";
    $db->query($sql);
    echo "Updated reference_type ENUM.<br>";
    
    echo "<h2 style='color:green'>Success! Schema updated.</h2>";
    
} catch (Exception $e) {
    echo "<h2 style='color:red'>Error: " . $e->getMessage() . "</h2>";
}
?>
