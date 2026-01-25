<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/ReportService.php';

$db = Database::getInstance();

$p_id = 5; // Assuming ID 5 based on previous output or use 1
// check project id 
$p_id = $db->query("SELECT id FROM projects LIMIT 1")->fetchColumn();

// 1. Total Received
$received = $db->query("SELECT COALESCE(SUM(pay.amount), 0) FROM payments pay JOIN bookings b ON pay.reference_type = 'booking' AND pay.reference_id = b.id JOIN flats f ON b.flat_id = f.id WHERE f.project_id = ? AND pay.payment_type = 'customer_receipt'", [$p_id])->fetchColumn();

// 2. Canc Income
$canc = $db->query("SELECT COALESCE(SUM(ft.amount), 0) FROM financial_transactions ft WHERE ft.project_id = ? AND ft.transaction_type = 'income' AND ft.category = 'cancellation_charges'", [$p_id])->fetchColumn();

// 3. Vendor Payments
$vendor = $db->query("SELECT COALESCE(SUM(pay.amount), 0) FROM payments pay JOIN challans c ON pay.reference_type = 'challan' AND pay.reference_id = c.id WHERE c.project_id = ? AND pay.payment_type = 'vendor_payment'", [$p_id])->fetchColumn();

// 4. Labour Payments
$labour = $db->query("SELECT COALESCE(SUM(pay.amount), 0) FROM payments pay JOIN challans c ON pay.reference_type = 'challan' AND pay.reference_id = c.id WHERE c.project_id = ? AND pay.payment_type = 'labour_payment'", [$p_id])->fetchColumn();

// 5. Refunds
$refunds = $db->query("SELECT COALESCE(SUM(pay.amount), 0) FROM payments pay JOIN booking_cancellations bc ON pay.reference_type = 'booking_cancellation' AND pay.reference_id = bc.id JOIN bookings b ON bc.booking_id = b.id JOIN flats f ON b.flat_id = f.id WHERE f.project_id = ? AND pay.payment_type = 'customer_refund'", [$p_id])->fetchColumn();

// 6. Other Expenses
$other = $db->query("SELECT COALESCE(SUM(ft.amount), 0) FROM financial_transactions ft WHERE ft.project_id = ? AND ft.transaction_type = 'expenditure'", [$p_id])->fetchColumn();

$total_income = $received + $canc;
$total_expense = $vendor + $labour + $refunds + $other;
$net = $total_income - $total_expense;

$data = [
    'project_id' => $p_id,
    'received' => $received,
    'canc_income' => $canc,
    'total_income' => $total_income,
    'vendor_payments' => $vendor,
    'labour_payments' => $labour,
    'refunds' => $refunds,
    'other_expenses' => $other,
    'total_expense' => $total_expense,
    'net_profit' => $net
];

echo json_encode($data, JSON_PRETTY_PRINT);
?>
