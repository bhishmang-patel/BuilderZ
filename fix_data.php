<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$db = Database::getInstance();

echo "<h1>Fixing Corrupted Data</h1>";

try {
    // 1. Update incorrect payments
    // We assume any payment with empty type and reference_type is a vendor_bill_payment
    // (Since that was the only one failing due to ENUM constraint)
    $sql = "UPDATE payments 
            SET payment_type = 'vendor_bill_payment', reference_type = 'bill' 
            WHERE payment_type = '' AND reference_type = ''";
    $result = $db->query($sql);
    echo "Fixed payments data. Rows affected: " . $result->rowCount() . "<br>";
    
    // 2. Recalculate all bills that might have been affected
    // Get all bills
    $bills = $db->query("SELECT id FROM bills")->fetchAll();
    echo "Recalculating " . count($bills) . " bills...<br>";
    
    foreach ($bills as $bill) {
        updateBillPaidAmount($bill['id']);
    }
    
    echo "<h2 style='color:green'>Success! Data fixed and bills updated.</h2>";
    
} catch (Exception $e) {
    echo "<h2 style='color:red'>Error: " . $e->getMessage() . "</h2>";
}
?>
