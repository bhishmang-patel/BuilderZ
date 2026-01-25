<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance();

// 1. Total Received
$stmt = $db->query("SELECT COALESCE(SUM(amount), 0) as total_received FROM payments WHERE payment_type = 'customer_receipt'");
$total_received = $stmt->fetch()['total_received'];

// 2. Cancellation Income
$stmt = $db->query("SELECT COALESCE(SUM(amount), 0) as canc_income FROM financial_transactions WHERE transaction_type = 'income' AND category = 'cancellation_charges'");
$cancellation_income = $stmt->fetch()['canc_income'];

// 3. Total Expenses (Cash Basis)
$stmt = $db->query("SELECT COALESCE(SUM(amount), 0) as total_expenses FROM payments WHERE payment_type IN ('vendor_payment', 'labour_payment', 'customer_refund')");
$total_expenses = $stmt->fetch()['total_expenses'];

// 4. Breakdown of Expenses
$stmt = $db->query("SELECT payment_type, SUM(amount) as amt FROM payments WHERE payment_type IN ('vendor_payment', 'labour_payment', 'customer_refund') GROUP BY payment_type");
$expense_breakdown = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);


$net_profit = ($total_received + $cancellation_income) - $total_expenses;

echo "RECEIVED:" . $total_received . "\n";
echo "CANC_INC:" . $cancellation_income . "\n";
echo "EXPENSES:" . $total_expenses . "\n";
echo "PROFIT:" . $net_profit . "\n";
foreach($expense_breakdown as $k => $v) { echo "EXP_$k:" . $v . "\n"; }
