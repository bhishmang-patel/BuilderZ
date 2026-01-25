<?php
// PDF Helper Functions using FPDF

function generatePaymentReceipt($payment_id) {
    global $db;
    
    // Check if FPDF is available
    $fpdf_path = __DIR__ . '/../vendor/fpdf/fpdf.php';
    if (!file_exists($fpdf_path)) {
        return ['success' => false, 'message' => 'FPDF library not installed. Please see LIBRARY_INSTALL.md'];
    }
    
    require_once($fpdf_path);
    
    // Fetch payment details
    $sql = "SELECT p.*, 
                   pt.name as party_name, pt.address, pt.mobile,
                   b.agreement_value, f.flat_no, pr.project_name,
                   u.full_name as received_by
            FROM payments p
            JOIN parties pt ON p.party_id = pt.id
            LEFT JOIN bookings b ON p.reference_id = b.id AND p.reference_type = 'booking'
            LEFT JOIN flats f ON b.flat_id = f.id
            LEFT JOIN projects pr ON b.project_id = pr.id
            LEFT JOIN users u ON p.created_by = u.id
            WHERE p.id = ?";
    
    $stmt = $db->query($sql, [$payment_id]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        return ['success' => false, 'message' => 'Payment not found'];
    }
    
    // Get company details
    $settings = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Create PDF
    $pdf = new FPDF();
    $pdf->AddPage();
    
    // Header
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, utf8_decode($settings['company_name'] ?? 'Builderz'), 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 5, utf8_decode($settings['company_address'] ?? ''), 0, 1, 'C');
    $pdf->Cell(0, 5, utf8_decode('Phone: ' . ($settings['company_phone'] ?? '') . ' | Email: ' . ($settings['company_email'] ?? '')), 0, 1, 'C');
    $pdf->Cell(0, 5, utf8_decode('GST: ' . ($settings['gst_number'] ?? '')), 0, 1, 'C');
    
    $pdf->Ln(5);
    
    // Title
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetFillColor(102, 126, 234);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 10, 'PAYMENT RECEIPT', 0, 1, 'C', true);
    $pdf->SetTextColor(0, 0, 0);
    
    $pdf->Ln(5);
    
    // Receipt details
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(50, 6, 'Receipt No:', 0, 0);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, 'PMT/' . str_pad($payment['id'], 6, '0', STR_PAD_LEFT), 0, 1);
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(50, 6, 'Date:', 0, 0);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, date('d-m-Y', strtotime($payment['payment_date'])), 0, 1);
    
    $pdf->Ln(5);
    
    // Customer details
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 6, 'Received From:', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, utf8_decode($payment['party_name']), 0, 1);
    $pdf->Cell(0, 6, utf8_decode($payment['address']), 0, 1);
    $pdf->Cell(0, 6, utf8_decode('Mobile: ' . $payment['mobile']), 0, 1);
    
    $pdf->Ln(5);
    
    // Payment details
    if ($payment['payment_type'] === 'customer_receipt') {
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 6, 'Payment For:', 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, utf8_decode('Project: ' . $payment['project_name']), 0, 1);
        $pdf->Cell(0, 6, utf8_decode('Flat No: ' . $payment['flat_no']), 0, 1);
        $pdf->Cell(0, 6, utf8_decode('Agreement Value: Rs. ' . number_format($payment['agreement_value'], 2)), 0, 1);
    }
    
    $pdf->Ln(5);
    
    // Amount details box
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(60, 10, 'Amount Received:', 1, 0, 'L', true);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetTextColor(40, 167, 69);
    $pdf->Cell(0, 10, 'Rs. ' . number_format($payment['amount'], 2), '1', 1, 'R', true);
    $pdf->SetTextColor(0, 0, 0);
    
    $pdf->Ln(3);
    
    // Payment mode
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(50, 6, 'Payment Mode:', 0, 0);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, strtoupper($payment['payment_mode']), 0, 1);
    
    if ($payment['reference_no']) {
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(50, 6, 'Reference No:', 0, 0);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 6, utf8_decode($payment['reference_no']), 0, 1);
    }
    
    if ($payment['remarks']) {
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(50, 6, 'Remarks:', 0, 0);
        $pdf->Cell(0, 6, utf8_decode($payment['remarks']), 0, 1);
    }
    
    $pdf->Ln(10);
    
    // Footer
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->Cell(0, 6, 'Received By: ' . $payment['received_by'], 0, 1);
    $pdf->Cell(0, 6, 'Generated on: ' . date('d-m-Y H:i:s'), 0, 1);
    
    $pdf->Ln(15);
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, 'Authorized Signature', 0, 1, 'R');
    
    // Output PDF
    $filename = 'receipt_' . $payment['id'] . '_' . date('Ymd') . '.pdf';
    $filepath = __DIR__ . '/../uploads/' . $filename;
    
    // Create uploads directory if not exists
    if (!is_dir(__DIR__ . '/../uploads/')) {
        mkdir(__DIR__ . '/../uploads/', 0777, true);
    }
    
    // Return PDF as string instead of saving to file
    $content = $pdf->Output('S');
    
    return ['success' => true, 'filename' => $filename, 'content' => $content];
}

function exportReportToExcel($data, $filename, $headers = []) {
    $filepath = __DIR__ . '/../uploads/' . $filename . '.xls';
    
    // Create uploads directory if not exists
    if (!is_dir(__DIR__ . '/../uploads/')) {
        mkdir(__DIR__ . '/../uploads/', 0777, true);
    }

    $html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    $html .= '<head><meta http-equiv="content-type" content="application/vnd.ms-excel; charset=UTF-8">';
    $html .= '<style>';
    $html .= 'body { font-family: Arial, sans-serif; }';
    $html .= 'table { border-collapse: collapse; width: 100%; }';
    $html .= 'th { background-color: #4f46e5; color: #ffffff; border: 1px solid #000; padding: 10px; font-weight: bold; text-align: center; }';
    $html .= 'td { border: 1px solid #cccccc; padding: 8px; vertical-align: middle; }';
    $html .= 'tr:nth-child(even) { background-color: #f9fafb; }';
    $html .= '</style>';
    $html .= '</head><body>';
    
    $html .= '<table>';
    
    // Add headers if provided, otherwise using keys from first row of data
    if (empty($headers) && !empty($data)) {
        $headers = array_keys($data[0]);
    }

    if (!empty($headers)) {
        $html .= '<thead><tr>';
        foreach ($headers as $header) {
            $html .= '<th>' . htmlspecialchars($header) . '</th>';
        }
        $html .= '</tr></thead>';
    }

    $html .= '<tbody>';
    foreach ($data as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
             // Basic type checking for alignment
             $align = is_numeric(str_replace([',', ' '], '', $cell)) ? 'right' : 'left';
             $html .= '<td style="text-align: '.$align.';">' . htmlspecialchars($cell) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody>';
    $html .= '</table>';
    
    $html .= '</body></html>';

    if (file_put_contents($filepath, $html) !== false) {
         return ['success' => true, 'filepath' => $filepath];
    } else {
         return ['success' => false, 'message' => 'Could not save file'];
    }
}

function exportReportToCSV($data, $filename, $headers = []) {
    $filepath = __DIR__ . '/../uploads/' . $filename . '.csv';
    
    // Create uploads directory if not exists
    if (!is_dir(__DIR__ . '/../uploads/')) {
        mkdir(__DIR__ . '/../uploads/', 0777, true);
    }
    
    $fp = fopen($filepath, 'w');
    
    // Add BOM for proper Excel UTF-8 support
    fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if (!empty($headers)) {
        fputcsv($fp, $headers);
    }
    
    foreach ($data as $row) {
        fputcsv($fp, $row);
    }
    
    fclose($fp);
    
    return ['success' => true, 'filepath' => $filepath];
}

function generateChallanPDF($challan_id) {
    // Similar to payment receipt but for challans
    // Implementation similar to generatePaymentReceipt
    return ['success' => false, 'message' => 'Challan PDF generation coming soon'];
}

function generateCancellationReceipt($cancellation_id) {
    global $db;
    
    // Check if FPDF is available
    $fpdf_path = __DIR__ . '/../vendor/fpdf/fpdf.php';
    if (!file_exists($fpdf_path)) {
        return ['success' => false, 'message' => 'FPDF library not installed.'];
    }
    
    require_once($fpdf_path);
    
    // Fetch cancellation details
    $sql = "SELECT bc.*, 
                   b.agreement_value, b.booking_date,
                   f.flat_no, pr.project_name,
                   p.name as customer_name, p.address as customer_address, p.mobile as customer_mobile,
                   u.full_name as processed_by_name
            FROM booking_cancellations bc
            JOIN bookings b ON bc.booking_id = b.id
            JOIN flats f ON b.flat_id = f.id
            JOIN projects pr ON b.project_id = pr.id
            JOIN parties p ON b.customer_id = p.id
            LEFT JOIN users u ON bc.processed_by = u.id
            WHERE bc.id = ?";
    
    $stmt = $db->query($sql, [$cancellation_id]);
    $cancellation = $stmt->fetch();
    
    if (!$cancellation) {
        return ['success' => false, 'message' => 'Cancellation record not found'];
    }
    
    // Get company details
    $settings = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Create PDF
    $pdf = new FPDF();
    $pdf->AddPage();
    
    // Header
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, utf8_decode($settings['company_name'] ?? 'Builderz'), 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 5, utf8_decode($settings['company_address'] ?? ''), 0, 1, 'C');
    $pdf->Cell(0, 5, utf8_decode('Phone: ' . ($settings['company_phone'] ?? '') . ' | Email: ' . ($settings['company_email'] ?? '')), 0, 1, 'C');
    
    $pdf->Ln(5);
    
    // Title
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetFillColor(220, 53, 69); // Red for cancellation
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 10, 'CANCELLATION RECEIPT', 0, 1, 'C', true);
    $pdf->SetTextColor(0, 0, 0);
    
    $pdf->Ln(5);
    
    // Receipt Info
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(50, 6, 'Cancellation No:', 0, 0);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, 'CNL/' . str_pad($cancellation['id'], 6, '0', STR_PAD_LEFT), 0, 1);
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(50, 6, 'Date:', 0, 0);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, date('d-m-Y', strtotime($cancellation['cancellation_date'])), 0, 1);
    
    $pdf->Ln(5);
    
    // Customer details
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 6, 'Customer Details:', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, utf8_decode($cancellation['customer_name']), 0, 1);
    if (!empty($cancellation['customer_address'])) {
        $pdf->Cell(0, 6, utf8_decode($cancellation['customer_address']), 0, 1);
    }
    $pdf->Cell(0, 6, utf8_decode('Mobile: ' . $cancellation['customer_mobile']), 0, 1);
    
    $pdf->Ln(5);
    
    // Property details
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 6, 'Property Details:', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, utf8_decode('Project: ' . $cancellation['project_name']), 0, 1);
    $pdf->Cell(0, 6, utf8_decode('Flat No: ' . $cancellation['flat_no']), 0, 1);
    
    $pdf->Ln(5);
    
    // Financials
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, 'Financial Summary', 1, 1, 'L', true);
    
    $pdf->SetFont('Arial', '', 10);
    
    // Helper for rows
    $row = function($label, $value, $boldValue = false) use ($pdf) {
        $pdf->Cell(100, 7, $label, 1);
        if ($boldValue) $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 7, $value, 1, 1, 'R');
        $pdf->SetFont('Arial', '', 10);
    };
    
    $row('Total Paid Amount', 'Rs. ' . number_format($cancellation['total_paid'], 2));
    $row('Deduction Amount', 'Rs. ' . number_format($cancellation['deduction_amount'], 2));
    $row('Refund Amount', 'Rs. ' . number_format($cancellation['refund_amount'], 2), true);
    
    $pdf->Ln(5);
    
    // Refund Details
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 6, 'Refund Details:', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(50, 6, 'Mode:', 0, 0);
    $pdf->Cell(0, 6, ucfirst($cancellation['refund_mode']), 0, 1);
    
    if ($cancellation['refund_reference']) {
        $pdf->Cell(50, 6, 'Reference / Chq No:', 0, 0);
        $pdf->Cell(0, 6, $cancellation['refund_reference'], 0, 1);
    }
    
    if ($cancellation['cancellation_reason']) {
        $pdf->Ln(3);
        $pdf->Cell(50, 6, 'Reason:', 0, 0);
        $pdf->MultiCell(0, 6, utf8_decode($cancellation['cancellation_reason']));
    }

    $pdf->Ln(15);
    
    // Footer
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 5, 'This is a computer generated receipt.', 0, 1, 'C');
    $pdf->Cell(0, 5, 'Processed By: ' . $cancellation['processed_by_name'], 0, 1, 'C');
    
    $content = $pdf->Output('S');
    $filename = 'cancellation_' . $cancellation['id'] . '.pdf';
    
    return ['success' => true, 'filename' => $filename, 'content' => $content];
}
