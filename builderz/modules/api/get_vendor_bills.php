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

$vendor_id = $_GET['vendor_id'] ?? 0;

if (!$vendor_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid Vendor ID']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Vendor bills are in 'bills' table, unlike contractor bills in 'challans'
    $sql = "SELECT id, bill_no, bill_date, amount, paid_amount, status, payment_status, file_path
            FROM bills
            WHERE party_id = ? AND status != 'rejected'
            ORDER BY bill_date DESC, id DESC";

    $stmt = $db->query($sql, [$vendor_id]);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate stats
    $stats = [
        'total_bills' => count($bills),
        'total_amount' => 0,
        'total_paid' => 0,
        'total_pending' => 0
    ];

    // Format data for display
    $formatted_bills = array_map(function($bill) use (&$stats, $db) {
        $stats['total_amount'] += $bill['amount'];
        $stats['total_paid'] += $bill['paid_amount'];
        $stats['total_pending'] += ($bill['amount'] - $bill['paid_amount']);

        // Fetch project names linked to this bill via challans
        $projs = $db->query("SELECT DISTINCT p.id, p.project_name 
                             FROM challans c 
                             JOIN projects p ON c.project_id = p.id 
                             WHERE c.bill_id = ?", [$bill['id']])->fetchAll();
        
        $project_display = '—';
        $project_id = 0;
        
        if (!empty($projs)) {
             // For simplicity, use the first project for the badge if multiple exist
             $project_display = $projs[0]['project_name'];
             $project_id = $projs[0]['id'];
             
             if (count($projs) > 1) {
                 $project_display .= ' +' . (count($projs) - 1);
             }
        }
        
        return [
            'id' => $bill['id'],
            'bill_no' => $bill['bill_no'],
            'date' => formatDate($bill['bill_date']),
            'amount' => formatCurrency($bill['amount']),
            'status' => ucfirst($bill['status'] ?: 'pending'),
            'status_class' => getStatusClass($bill['status'] ?: 'pending'),
            'payment_status' => ucfirst($bill['payment_status']),
            'payment_class' => getPaymentClass($bill['payment_status']),
            'project_name' => $project_display,
            'project_id' => $project_id,
            'file_path' => $bill['file_path'] ? BASE_URL . $bill['file_path'] : null
        ];
    }, $bills);

    echo json_encode(['success' => true, 'bills' => $formatted_bills, 'stats' => [
        'total'   => formatCurrency($stats['total_amount']),
        'paid'    => formatCurrency($stats['total_paid']),
        'pending' => formatCurrency($stats['total_pending'])
    ]]);

} catch (Exception $e) {
    error_log("Error fetching vendor bills: " . $e->getMessage());
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
