<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$contractor_id = $_GET['contractor_id'] ?? 0;

if (!$contractor_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid Contractor ID']);
    exit;
}

try {
    $db = Database::getInstance();
    
    $sql = "SELECT c.id, c.bill_no as challan_no, c.bill_date as challan_date, c.basic_amount, c.total_payable as final_payable_amount, c.paid_amount, c.status, c.payment_status,
                   pr.project_name, wo.work_order_no
            FROM contractor_bills c
            LEFT JOIN projects pr ON c.project_id = pr.id
            LEFT JOIN work_orders wo ON c.work_order_id = wo.id
            WHERE c.contractor_id = ? AND c.status != 'rejected'
            ORDER BY c.bill_date DESC, c.id DESC";

    $stmt = $db->query($sql, [$contractor_id]);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate stats
    $total_billed = 0;
    $total_paid   = 0;

    // Format data for display
    $formatted_bills = array_map(function($bill) use (&$total_billed, &$total_paid) {
        $payable = $bill['final_payable_amount']; // Already aliased or direct column
        $total_billed += $payable;
        $total_paid   += $bill['paid_amount'];

        return [
            'id' => $bill['id'],
            'challan_no' => $bill['challan_no'],
            'date' => formatDate($bill['challan_date']),
            'project_name' => $bill['project_name'],
            'work_order_no' => $bill['work_order_no'] ?? '-',
            'amount' => formatCurrency($payable),
            'status' => ucfirst($bill['status']),       // Approval Status (e.g., Approved)
            'status_class' => getStatusClass($bill['status']),
            'payment_status' => ucfirst($bill['payment_status']), // Payment Status (e.g., Paid)
            'payment_class' => getPaymentClass($bill['payment_status'])
        ];
    }, $bills);

    $stats = [
        'total'   => formatCurrency($total_billed),
        'paid'    => formatCurrency($total_paid),
        'pending' => formatCurrency($total_billed - $total_paid)
    ];

    echo json_encode(['success' => true, 'bills' => $formatted_bills, 'stats' => $stats]);

} catch (Exception $e) {
    error_log("Error fetching contractor bills: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

function getStatusClass($status) {
    switch ($status) {
        case 'approved': return 'blue';
        case 'rejected': return 'red';
        default: return 'orange'; // pending
    }
}

function getPaymentClass($status) {
    switch ($status) {
        case 'paid': return 'green';
        case 'partial': return 'purple';
        default: return 'gray'; // pending
    }
}
