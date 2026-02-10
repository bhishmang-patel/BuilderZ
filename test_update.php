<?php
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance();

echo "Fetching last expense...\n";
$stmt = $db->query("SELECT * FROM expenses ORDER BY id DESC LIMIT 1");
$expense = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$expense) {
    die("No expenses found to test update.\n");
}

$id = $expense['id'];
echo "Testing update on Expense ID: $id\n";

// Prepare dummy data (similar to what form submits)
$data = [
    'project_id'     => $expense['project_id'], // Keep existing
    'category_id'    => $expense['category_id'], // Keep existing
    'date'           => date('Y-m-d'), // Today
    'amount'         => $expense['amount'], // Keep existing
    'description'    => "Updated via test script " . date('H:i:s'),
    'payment_method' => 'cash',
    'gst_included'   => 0,
    'gst_amount'     => 0,
    'reference_no'   => 'TEST-REF',
];

try {
     $fields = [];
     $params = [];
     foreach ($data as $key => $value) {
         $fields[] = "$key = ?";
         $params[] = $value;
     }
     $params[] = $id;
     
     $sql = "UPDATE expenses SET " . implode(', ', $fields) . " WHERE id = ?";
     echo "SQL: $sql\n";
     // print_r($params);
     
     $stmt = $db->prepare($sql);
     $stmt->execute($params);
     
     echo "Update SUCCESS!\n";
} catch (Exception $e) {
    echo "Update FAILED: " . $e->getMessage() . "\n";
}
?>
