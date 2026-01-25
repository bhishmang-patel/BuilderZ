<?php
ob_start(); // Start buffering immediately
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/pdf_excel_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();

$db = Database::getInstance();

$action = $_GET['action'] ?? '';
$id = intval($_GET['id'] ?? 0);

if ($action === 'payment_receipt' && $id > 0) {
    $result = generatePaymentReceipt($id);
    
    if ($result['success']) {
        // Clear all levels of output buffering to prevent corruption
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Use inline so it opens in browser, but set headers for a clean stream
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $result['filename'] . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        // Ensure the content length is accurate for the bytes being sent
        header('Content-Length: ' . strlen($result['content']));
        
        echo $result['content'];
        exit;
    } else {
        setFlashMessage('error', $result['message']);
        redirect('modules/payments/index.php');
    }
}

if ($action === 'cancellation_receipt' && $id > 0) {
    $result = generateCancellationReceipt($id);
    
    if ($result['success']) {
        while (ob_get_level()) ob_end_clean();
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $result['filename'] . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . strlen($result['content']));
        
        echo $result['content'];
        exit;
    } else {
        setFlashMessage('error', $result['message']);
        redirect('modules/booking/index.php');
    }
}

if ($action === 'download_report') {
    $report_type = $_GET['report'] ?? '';
    $format = $_GET['format'] ?? 'csv'; // csv or excel
    
    // Export reports based on type
    switch ($report_type) {
        case 'customer_pending':
            $sql = "SELECT p.name as 'Customer Name', p.mobile as 'Mobile', 
                           pr.project_name as 'Project', f.flat_no as 'Flat No',
                           b.booking_date as 'Booking Date', b.agreement_value as 'Agreement Value',
                           b.total_received as 'Received', b.total_pending as 'Pending'
                    FROM bookings b
                    JOIN flats f ON b.flat_id = f.id
                    JOIN parties p ON b.customer_id = p.id
                    JOIN projects pr ON b.project_id = pr.id
                    WHERE b.total_pending > 0 AND b.status = 'active'
                    ORDER BY b.total_pending DESC";
            
            $stmt = $db->query($sql);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $filename = 'customer_pending_' . date('Ymd');
            break;
            
        case 'vendor_outstanding':
            $sql = "SELECT p.name as 'Vendor Name', p.mobile as 'Mobile', p.gst_number as 'GST',
                           COUNT(c.id) as 'Total Challans',
                           SUM(c.total_amount) as 'Total Amount',
                           SUM(c.paid_amount) as 'Paid',
                           SUM(c.pending_amount) as 'Outstanding'
                    FROM parties p
                    LEFT JOIN challans c ON p.id = c.party_id AND c.challan_type = 'material'
                    WHERE p.party_type = 'vendor'
                    GROUP BY p.id
                    HAVING SUM(c.pending_amount) > 0
                    ORDER BY SUM(c.pending_amount) DESC";
            
            $stmt = $db->query($sql);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $filename = 'vendor_outstanding_' . date('Ymd');
            break;
            
        case 'labour_outstanding':
            $sql = "SELECT p.name as 'Labour Name', p.mobile as 'Mobile',
                           COUNT(c.id) as 'Total Challans',
                           SUM(c.total_amount) as 'Total Amount',
                           SUM(c.paid_amount) as 'Paid',
                           SUM(c.pending_amount) as 'Outstanding'
                    FROM parties p
                    LEFT JOIN challans c ON p.id = c.party_id AND c.challan_type = 'labour'
                    WHERE p.party_type = 'labour'
                    GROUP BY p.id
                    HAVING SUM(c.pending_amount) > 0
                    ORDER BY SUM(c.pending_amount) DESC";
            
            $stmt = $db->query($sql);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $filename = 'labour_outstanding_' . date('Ymd');
            break;
            
        case 'payment_register':
            $date_from = $_GET['date_from'] ?? date('Y-m-01');
            $date_to = $_GET['date_to'] ?? date('Y-m-d');
            
            $sql = "SELECT p.payment_date as 'Date', p.payment_type as 'Type',
                           pt.name as 'Party Name', p.payment_mode as 'Mode',
                           p.reference_no as 'Reference', p.amount as 'Amount',
                           p.remarks as 'Remarks', u.full_name as 'By'
                    FROM payments p
                    JOIN parties pt ON p.party_id = pt.id
                    LEFT JOIN users u ON p.created_by = u.id
                    WHERE p.payment_date BETWEEN ? AND ?
                    ORDER BY p.payment_date";
            
            $stmt = $db->query($sql, [$date_from, $date_to]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $filename = 'payment_register_' . date('Ymd');
            break;

        case 'project_pl':
            $sql = "SELECT p.project_name as 'Project', p.location as 'Location',
                           (SELECT COALESCE(SUM(b.agreement_value), 0) FROM bookings b JOIN flats f ON b.flat_id = f.id WHERE f.project_id = p.id AND b.status = 'active') as 'Total Sales',
                           (SELECT COALESCE(SUM(b.total_received), 0) FROM bookings b JOIN flats f ON b.flat_id = f.id WHERE f.project_id = p.id) as 'Received',
                           (SELECT COALESCE(SUM(total_amount), 0) FROM challans WHERE project_id = p.id) as 'Total Expense'
                    FROM projects p
                    ORDER BY p.project_name";
            $stmt = $db->query($sql);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $filename = 'project_pl_' . date('Ymd');
            break;

        case 'cash_flow':
            $year = $_GET['year'] ?? date('Y');
            $month = $_GET['month'] ?? date('m');
            $start = $year . '-' . sprintf('%02d', $month) . '-01';
            $end = date('Y-m-t', strtotime($start));

            $sql = "SELECT payment_date as 'Date', 
                           SUM(CASE WHEN payment_type = 'customer_receipt' THEN amount ELSE 0 END) as 'Inflow',
                           SUM(CASE WHEN payment_type IN ('vendor_payment', 'labour_payment') THEN amount ELSE 0 END) as 'Outflow'
                    FROM payments 
                    WHERE payment_date BETWEEN ? AND ?
                    GROUP BY payment_date ORDER BY payment_date";
            $stmt = $db->query($sql, [$start, $end]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $filename = 'cash_flow_' . $year . '_' . $month;
            break;
            
        default:
            setFlashMessage('error', 'Invalid report type');
            redirect('modules/reports/');
            exit;
    }
    
    if (empty($data)) {
        setFlashMessage('error', 'No data to export');
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }
    
    // Export based on format
    if ($format === 'excel') {
        $result = exportReportToExcel($data, $filename);
    } else {
        $result = exportReportToCSV($data, $filename, array_keys($data[0]));
    }
    
    if ($result['success']) {
        // Clear all levels of output buffering
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Determine extension and mime type
        $ext = pathinfo($result['filepath'], PATHINFO_EXTENSION);
        
        switch (strtolower($ext)) {
            case 'xlsx':
                $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                break;
            case 'xls':
                $mime = 'application/vnd.ms-excel';
                break;
            case 'csv':
            default:
                $mime = 'text/csv';
                break;
        }
        
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . basename($result['filepath']) . '"');
        header('Content-Length: ' . filesize($result['filepath']));
        readfile($result['filepath']);
        
        // Delete temporary file
        unlink($result['filepath']);
        exit;
    } else {
        setFlashMessage('error', 'Export failed');
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }
}

setFlashMessage('error', 'Invalid action');
redirect('modules/dashboard/index.php');
