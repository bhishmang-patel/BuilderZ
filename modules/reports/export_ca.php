<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// requireAuth();
// checkPermission(['admin', 'accountant']);

$db = Database::getInstance();
$month = $_GET['month'] ?? date('Y-m');
$start_date = $month . '-01';
$end_date = date('Y-m-t', strtotime($start_date));
$month_name = date('F_Y', strtotime($start_date));

// Default to last month
file_put_contents(__DIR__ . '/../../debug_export.txt', "Export started for Month: $month\nStart: $start_date\nEnd: $end_date\n", FILE_APPEND);

// Clean temp directory
$tempDir = __DIR__ . '/../../uploads/temp/audit_' . time();
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0777, true);
}

try {
    // ---------------------------------------------------------
    // 1. SALES (GSTR-1) - Customer Receipts
    // ---------------------------------------------------------
    $sales_sql = "SELECT 
        DATE_FORMAT(p.payment_date, '%d-%m-%Y') as 'Date',
        p.reference_no as 'Receipt No',
        pt.name as 'Customer Name',
        b.flat_id,
        f.flat_no as 'Unit',
        pr.project_name as 'Project',
        p.amount as 'Amount Received',
        p.payment_mode as 'Mode',
        p.remarks as 'Remarks'
    FROM payments p
    JOIN parties pt ON p.party_id = pt.id
    LEFT JOIN bookings b ON p.reference_id = b.id AND p.reference_type = 'booking'
    LEFT JOIN flats f ON b.flat_id = f.id
    LEFT JOIN projects pr ON f.project_id = pr.id
    WHERE p.payment_type = 'customer_receipt' 
    AND p.payment_date BETWEEN ? AND ?
    ORDER BY p.payment_date";

    $salesData = $db->query($sales_sql, [$start_date, $end_date])->fetchAll(PDO::FETCH_ASSOC);
    file_put_contents(__DIR__ . '/../../debug_export.txt', "Sales Count: " . count($salesData) . "\n", FILE_APPEND);

    $fp = fopen($tempDir . "/Sales_Collections_{$month_name}.csv", 'w');
    fputcsv($fp, ['Date', 'Receipt No', 'Customer Name', 'Unit', 'Project', 'Amount Received', 'Mode', 'Remarks']);
    foreach ($salesData as $row) {
        unset($row['flat_id']); // Remove internal ID
        fputcsv($fp, $row);
    }
    fclose($fp);


    // ---------------------------------------------------------
    // 2. PURCHASES (GSTR-2/ITC)
    // ---------------------------------------------------------
    $purchases_sql = "
    SELECT 
        'Material' as 'Type',
        DATE_FORMAT(b.bill_date, '%d-%m-%Y') as 'Date',
        b.bill_no as 'Bill No',
        p.name as 'Vendor Name',
        p.gst_number as 'GSTIN',
        b.taxable_amount as 'Taxable Value',
        b.tax_amount as 'GST Amount',
        b.amount as 'Total Amount'
    FROM bills b
    JOIN parties p ON b.party_id = p.id
    WHERE b.bill_date BETWEEN ? AND ? AND b.status != 'rejected'
    
    UNION ALL
    
    SELECT 
        'Service/Labour' as 'Type',
        DATE_FORMAT(cb.bill_date, '%d-%m-%Y') as 'Date',
        cb.bill_no as 'Bill No',
        p.name as 'Contractor Name',
        p.gst_number as 'GSTIN',
        cb.basic_amount as 'Taxable Value',
        cb.gst_amount as 'GST Amount',
        cb.total_payable as 'Total Amount'
    FROM contractor_bills cb
    JOIN parties p ON cb.contractor_id = p.id
    WHERE cb.bill_date BETWEEN ? AND ? AND cb.status != 'rejected'
    
    ORDER BY Date";

    $purchaseData = $db->query($purchases_sql, [$start_date, $end_date, $start_date, $end_date])->fetchAll(PDO::FETCH_ASSOC);

    $fp = fopen($tempDir . "/Purchases_Input_Tax_{$month_name}.csv", 'w');
    fputcsv($fp, ['Type', 'Date', 'Bill No', 'Party Name', 'GSTIN', 'Taxable Value', 'GST Amount', 'Total Amount']);
    foreach ($purchaseData as $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);


    // ---------------------------------------------------------
    // 3. TDS LIABILITY
    // ---------------------------------------------------------
    $tds_sql = "SELECT 
        DATE_FORMAT(cb.bill_date, '%d-%m-%Y') as 'Date',
        p.name as 'Contractor Name',
        p.pan_number as 'PAN',
        cb.bill_no as 'Bill No',
        cb.basic_amount as 'Bill Amount',
        cb.tds_percentage as 'Rate (%)',
        cb.tds_amount as 'TDS Deducted'
    FROM contractor_bills cb
    JOIN parties p ON cb.contractor_id = p.id
    WHERE cb.bill_date BETWEEN ? AND ? 
    AND cb.tds_amount > 0 
    AND cb.status != 'rejected'
    ORDER BY cb.bill_date";

    $tdsData = $db->query($tds_sql, [$start_date, $end_date])->fetchAll(PDO::FETCH_ASSOC);

    $fp = fopen($tempDir . "/TDS_Liability_{$month_name}.csv", 'w');
    fputcsv($fp, ['Date', 'Contractor Name', 'PAN', 'Bill No', 'Bill Amount', 'Rate (%)', 'TDS Deducted']);
    foreach ($tdsData as $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);

    // ---------------------------------------------------------
    // 4. GENERAL EXPENSES
    // ---------------------------------------------------------
    $exp_sql = "SELECT 
        DATE_FORMAT(e.date, '%d-%m-%Y') as 'Date',
        ec.name as 'Category',
        e.description as 'Description',
        COALESCE(p.project_name, 'Head Office') as 'Project',
        e.amount as 'Amount',
        COALESCE(u.full_name, 'Unknown') as 'Paid By',
        e.payment_method as 'Mode'
    FROM expenses e
    LEFT JOIN expense_categories ec ON e.category_id = ec.id
    LEFT JOIN projects p ON e.project_id = p.id
    LEFT JOIN users u ON e.created_by = u.id
    WHERE e.date BETWEEN ? AND ?
    ORDER BY e.date";

    $expData = $db->query($exp_sql, [$start_date, $end_date])->fetchAll(PDO::FETCH_ASSOC);
    file_put_contents(__DIR__ . '/../../debug_export.txt', "Expenses Count: " . count($expData) . "\n", FILE_APPEND);

    $fp = fopen($tempDir . "/General_Expenses_{$month_name}.csv", 'w');
    fputcsv($fp, ['Date', 'Category', 'Description', 'Project', 'Amount', 'Paid By', 'Mode']);
    foreach ($expData as $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);


    // ---------------------------------------------------------
    // 5. INVESTMENTS (Capital Receipts & Repayments)
    // ---------------------------------------------------------
    $inv_sql = "
    SELECT 
        'Receipt' as 'Type',
        DATE_FORMAT(i.investment_date, '%d-%m-%Y') as 'Date',
        i.investor_name as 'Party Name',
        p.project_name as 'Project',
        i.investment_type as 'Category',
        i.amount as 'Amount',
        'Received' as 'Status'
    FROM investments i
    LEFT JOIN projects p ON i.project_id = p.id
    WHERE i.investment_date BETWEEN ? AND ?
    
    UNION ALL
    
    SELECT 
        'Repayment' as 'Type',
        DATE_FORMAT(ir.return_date, '%d-%m-%Y') as 'Date',
        i.investor_name as 'Party Name',
        p.project_name as 'Project',
        'Return' as 'Category',
        ir.amount as 'Amount',
        'Paid' as 'Status'
    FROM investment_returns ir
    JOIN investments i ON ir.investment_id = i.id
    LEFT JOIN projects p ON i.project_id = p.id
    WHERE ir.return_date BETWEEN ? AND ?
    
    ORDER BY Date";

    $invData = $db->query($inv_sql, [$start_date, $end_date, $start_date, $end_date])->fetchAll(PDO::FETCH_ASSOC);

    $fp = fopen($tempDir . "/Investments_Capital_{$month_name}.csv", 'w');
    fputcsv($fp, ['Type', 'Date', 'Party Name', 'Project', 'Category', 'Amount', 'Status']);
    foreach ($invData as $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);


    // ---------------------------------------------------------
    // 6. BOOKINGS (Revenue Recognition / Order Book)
    // ---------------------------------------------------------
    $booking_sql = "SELECT 
        DATE_FORMAT(b.booking_date, '%d-%m-%Y') as 'Date',
        p.name as 'Customer Name',
        pr.project_name as 'Project',
        f.flat_no as 'Unit No',
        b.agreement_value as 'Agreement Value',
        b.total_received as 'Received',
        (b.agreement_value - b.total_received) as 'Pending',
        b.status as 'Status'
    FROM bookings b
    JOIN parties p ON b.customer_id = p.id
    JOIN flats f ON b.flat_id = f.id
    JOIN projects pr ON b.project_id = pr.id
    WHERE b.booking_date BETWEEN ? AND ?
    ORDER BY b.booking_date";

    $bookingData = $db->query($booking_sql, [$start_date, $end_date])->fetchAll(PDO::FETCH_ASSOC);

    $fp = fopen($tempDir . "/Bookings_OrderBook_{$month_name}.csv", 'w');
    fputcsv($fp, ['Date', 'Customer Name', 'Project', 'Unit No', 'Agreement Value', 'Received', 'Pending', 'Status']);
    foreach ($bookingData as $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);


    // ---------------------------------------------------------
    // 7. INVENTORY (Stock Valuation)
    // ---------------------------------------------------------
    // Note: Inventory is a snapshot, not date-range specific usually, 
    // but for audit we export current standing stock.
    $stock_sql = "SELECT 
        material_name as 'Material',
        unit as 'Unit',
        current_stock as 'Qty in Stock',
        default_rate as 'Est Rate',
        (current_stock * default_rate) as 'Est Value'
    FROM materials
    WHERE current_stock > 0
    ORDER BY material_name";

    $stockData = $db->query($stock_sql)->fetchAll(PDO::FETCH_ASSOC);

    $fp = fopen($tempDir . "/Inventory_Stock_Snapshot.csv", 'w');
    fputcsv($fp, ['Material', 'Unit', 'Qty in Stock', 'Est Rate', 'Est Value']);
    foreach ($stockData as $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);


    // ---------------------------------------------------------
    // ZIP IT UP
    // ---------------------------------------------------------
    $zipFile = "Audit_Pack_{$month_name}.zip";
    $zipPath = $tempDir . '/' . $zipFile;

    $files = glob($tempDir . '/*.csv');
    
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            foreach ($files as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();
        } else {
            throw new Exception("Could not create ZIP using ZipArchive");
        }
    } elseif (class_exists('PharData')) {
        try {
            // PharData works with tar, then convert/compress? 
            // Actually direct .zip support via PharData depends on php settings (phar.readonly=0).
            // Safer to use execution if available or just return error gently.
            // Let's try Taring then Zipping or just Tar.
            $tarFile = "Audit_Pack_{$month_name}.tar";
            $tarPath = $tempDir . '/' . $tarFile;
            
            $phar = new PharData($tarPath);
            foreach ($files as $file) {
                $phar->addFile($file, basename($file));
            }
            // Compress to .tar.gz
            $phar->compress(Phar::GZ);
            
            $zipFile = "Audit_Pack_{$month_name}.tar.gz";
            $zipPath = $tarPath . '.gz';
            
            // Remove the uncompressed tar
            unlink($tarPath);
        } catch (Exception $e) {
            throw new Exception("Could not create Archive using PharData: " . $e->getMessage());
        }
    } else {
        throw new Exception("No ZIP library available (ZipArchive or PharData). Please enable php_zip extension.");
    }

    // ---------------------------------------------------------
    // DOWNLOAD
    // ---------------------------------------------------------
    if (file_exists($zipPath)) {
        // Clear buffer
        while (ob_get_level()) ob_end_clean();

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($zipPath) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);
        
        // Cleanup
        foreach ($files as $file) unlink($file);
        unlink($zipPath);
        rmdir($tempDir);
        exit;
    } else {
        throw new Exception("Archive file not created");
    }

} catch (Exception $e) {
    // Cleanup if failed
    if (isset($tempDir) && is_dir($tempDir)) {
        array_map('unlink', glob("$tempDir/*.*"));
        rmdir($tempDir);
    }
    setFlashMessage('error', 'Export Failed: ' . $e->getMessage());
    // Use JS redirect to preserve history or simple PHP header
    header("Location: compliance.php");
    exit;
}
