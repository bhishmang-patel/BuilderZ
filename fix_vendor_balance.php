<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$db = Database::getInstance();

echo "<h1>Fixing Vendor Balances</h1>";
echo "<h3>Connected to: " . DB_HOST . " / " . DB_NAME . "</h3>";

try {
    // 1. Get all bills
    $bills = $db->query("SELECT id FROM bills")->fetchAll(PDO::FETCH_COLUMN);
    echo "<h3>Found " . count($bills) . " bills. Recalculating...</h3>";
    
    foreach ($bills as $billId) {
        updateBillPaidAmount($billId);
    }
    
    // 2. Verify results
    $billsAfter = $db->query("SELECT id, bill_no, amount, paid_amount, status FROM bills")->fetchAll(PDO::FETCH_ASSOC);
    echo "<h2>Bills Status After Fix</h2>";
    echo "<pre>";
    print_r($billsAfter);
    echo "</pre>";
    
    // 3. Verify Vendors
    echo "<h2>Vendors Status After Fix</h2>";
    // Using MasterService logic query
    $sql = "SELECT p.id, p.name, 
            (p.opening_balance + (SELECT COALESCE(SUM(b.amount - b.paid_amount), 0) FROM bills b WHERE b.party_id = p.id AND b.status != 'paid')) as outstanding_balance
            FROM parties p WHERE party_type = 'vendor'";
    $vendors = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<pre>";
    print_r($vendors);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<h3>Error: " . $e->getMessage() . "</h3>";
}
?>
