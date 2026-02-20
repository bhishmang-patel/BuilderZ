<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance();
$month = $argv[1] ?? $_GET['month'] ?? date('Y-m');
$start_date = $month . '-01';
$end_date = date('Y-m-t', strtotime($start_date));

echo "Month: $month\n";
echo "Start Date: $start_date\n";
echo "End Date: $end_date\n";
echo "--------------------------------------------------\n";

// 1. SALES
$sales_sql = "SELECT count(*) FROM payments p WHERE p.payment_type = 'customer_receipt' AND p.payment_date BETWEEN ? AND ?";
$count = $db->query($sales_sql, [$start_date, $end_date])->fetchColumn();
echo "Sales Count: $count\n";

// 2. PURCHASES
$purchases_sql = "SELECT count(*) FROM bills b WHERE b.bill_date BETWEEN ? AND ? AND b.status != 'rejected'";
$count = $db->query($purchases_sql, [$start_date, $end_date])->fetchColumn();
echo "Purchases (Bills) Count: $count\n";

// 3. EXPENSES
$exp_sql = "SELECT count(*) FROM expenses e WHERE e.date BETWEEN ? AND ?";
$count = $db->query($exp_sql, [$start_date, $end_date])->fetchColumn();
echo "Expenses Count: $count\n";

// 4. INVESTMENTS
$inv_sql = "SELECT count(*) FROM investments i WHERE i.investment_date BETWEEN ? AND ?";
$count = $db->query($inv_sql, [$start_date, $end_date])->fetchColumn();
echo "Investments Count: $count\n";

// 5. BOOKINGS
$booking_sql = "SELECT count(*) FROM bookings b WHERE b.booking_date BETWEEN ? AND ?";
$count = $db->query($booking_sql, [$start_date, $end_date])->fetchColumn();
echo "Bookings Count: $count\n";

// 6. INVENTORY
$stock_sql = "SELECT count(*) FROM materials WHERE current_stock > 0";
$count = $db->query($stock_sql)->fetchColumn();
echo "Inventory Count: $count\n";
