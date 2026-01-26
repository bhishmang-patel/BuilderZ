<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$db = Database::getInstance();

echo "<h1>Debug Payment System</h1>";

// 1. Check last 5 payments
echo "<h2>Last 5 Payments</h2>";
$payments = $db->query("SELECT * FROM payments ORDER BY id DESC LIMIT 5")->fetchAll();
echo "<pre>";
print_r($payments);
echo "</pre>";

// 2. Check a specific bill if we have a payment
if (!empty($payments)) {
    $last_pay = $payments[0];
    if ($last_pay['reference_type'] == 'bill') {
        echo "<h2>Analyzing Bill ID: {$last_pay['reference_id']}</h2>";
        
        $bill = $db->select('bills', 'id = ?', [$last_pay['reference_id']])->fetch();
        echo "<h3>Bill Record:</h3>";
        echo "<pre>";
        print_r($bill);
        echo "</pre>";
        
        // 3. Test updateBillPaidAmount logic manually
        echo "<h3>Testing Update Logic:</h3>";
        
        $sql = "SELECT COALESCE(SUM(amount), 0) as paid_amount 
                FROM payments 
                WHERE reference_type = 'bill' AND reference_id = ?";
        $stmt = $db->query($sql, [$last_pay['reference_id']]);
        $result = $stmt->fetch();
        echo "Calculated Paid Amount from Payments Table: " . $result['paid_amount'] . "<br>";
        
        $status = 'pending';
        if ($result['paid_amount'] >= $bill['amount']) {
            $status = 'paid';
        } elseif ($result['paid_amount'] > 0) {
            $status = 'partial';
        }
        echo "Calculated Status: " . $status . "<br>";
        
        // Dry run update check
        echo "Update would set paid_amount = {$result['paid_amount']} and status = {$status}<br>";
        
        if ($bill['paid_amount'] != $result['paid_amount']) {
            echo "<strong style='color:red'>MISMATCH DETECTED! Bill table has {$bill['paid_amount']}</strong>";
        }
    }
}

// 4. Check Table Schema
echo "<h2>Table Schemas</h2>";
echo "<h3>Payments Table</h3>";
$stmt = $db->query("SHOW CREATE TABLE payments");
$schema = $stmt->fetch(PDO::FETCH_ASSOC);
echo "<pre>" . htmlspecialchars($schema['Create Table']) . "</pre>";

?>
