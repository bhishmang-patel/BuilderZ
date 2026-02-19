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
                   pt.name as party_name, pt.address, pt.mobile, pt.email,
                   b.agreement_value, f.flat_no, f.floor, f.block,
                   pr.project_name, pr.location as project_location,
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
    $company_name = $settings['company_name'] ?? 'Builderz';
    $company_address = $settings['company_address'] ?? '123 Business Park, Sector 15, Mumbai - 400001';
    $company_phone = $settings['company_phone'] ?? '+91-12345678';
    $company_email = $settings['company_email'] ?? 'bookings@builderz.com';
    $company_website = $settings['company_website'] ?? 'www.builderz.com';
    $company_logo = $settings['company_logo'] ?? null;
    
    // Create PDF
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 10);
    $pdf->SetMargins(15, 10, 15);
    
    // Defines
    $primaryColor = [16, 25, 60]; // Navy Blue
    
    // --- Header Section ---
    
    // Logo (Centered Top)
    $y = 10;
    if ($company_logo && file_exists(__DIR__ . '/../' . $company_logo)) {
        $pdf->Image(__DIR__ . '/../' . $company_logo, 90, $y, 30); 
        $y += 25;
    } else {
        $y += 5;
    }
    
    // Company Name
    $pdf->SetY($y);
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->Cell(0, 8, strtoupper($company_name), 0, 1, 'C');
    
    // Address & Contact
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 5, 'Registered Office: ' . $company_address, 0, 1, 'C');
    $contact_line = "Tel: $company_phone | Email: $company_email | $company_website";
    $pdf->Cell(0, 5, $contact_line, 0, 1, 'C');
    
    $pdf->Ln(5);
    
    // --- Title Bar ---
    $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'PAYMENT RECEIPT', 0, 1, 'C', true);
    
    $pdf->Ln(5);
    
    // Receipt No & Date Row
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(30, 6, 'Receipt No:', 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(60, 6, 'PMT/' . str_pad($payment['id'], 6, '0', STR_PAD_LEFT), 0, 0);
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(70, 6, 'Date:', 0, 0, 'R');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(30, 6, date('d F Y', strtotime($payment['payment_date'])), 0, 1, 'R');
    
    $pdf->Ln(5);
    
    // Helper function for sections
    $drawSectionHeader = function($pdf, $title) use ($primaryColor) {
        $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 8, strtoupper($title), 0, 1, 'L');
        $pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetX() + 180, $pdf->GetY());
        $pdf->Ln(2);
    };

    $drawRow = function($pdf, $label, $value) {
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(50, 6, $label, 0, 0);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 6, $value, 0, 1);
    };
    
    // --- Received From Section ---
    $drawSectionHeader($pdf, 'RECEIVED FROM');
    $drawRow($pdf, 'Name:', $payment['party_name']);
    if ($payment['mobile']) $drawRow($pdf, 'Contact:', $payment['mobile']);
    if (!empty($payment['address'])) $drawRow($pdf, 'Address:', $payment['address']);
    
    $pdf->Ln(5);
    
    // --- Payment Details ---
    $drawSectionHeader($pdf, 'PAYMENT DETAILS');
    
    // Table
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(140, 8, 'Description', 1, 0, 'L', true);
    $pdf->Cell(40, 8, 'Amount (INR)', 1, 1, 'R', true);
    
    $pdf->SetFont('Arial', '', 9);
    
    // Determine Description
    $description = '';
    if ($payment['payment_type'] === 'customer_receipt') {
        $description = "Towards booking of Unit " . ($payment['flat_no'] ?? 'N/A') . " in " . ($payment['project_name'] ?? 'N/A');
    } else {
        $type_map = [
            'vendor_payment' => 'Vendor Payment',
            'vendor_bill_payment' => 'Bill Payment',
            'labour_payment' => 'Labour Payment',
            'labour_account_payment' => 'Labour Account Payment'
        ];
        $description = ($type_map[$payment['payment_type']] ?? 'Payment') . ' - Ref #' . $payment['reference_no'];
    }
    
    $pdf->Cell(140, 8, $description, 1, 0);
    $pdf->Cell(40, 8, number_format($payment['amount'], 2), 1, 1, 'R');
    
    $pdf->Ln(0); // Next line
    
    // Total Row
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(255, 255, 230); // Light Yellow
    $pdf->Cell(140, 8, 'Total Amount Received', 1, 0, 'R', true);
    $pdf->Cell(40, 8, number_format($payment['amount'], 2), 1, 1, 'R', true);
    
    $pdf->Ln(8);
    
    // Mode Info
    $drawRow($pdf, 'Payment Mode:', ucfirst($payment['payment_mode']));
    if (!empty($payment['transaction_id'])) $drawRow($pdf, 'Cheque/Transaction No:', $payment['transaction_id']);
    if (!empty($payment['bank_name'])) $drawRow($pdf, 'Bank Name:', $payment['bank_name']);
    if (!empty($payment['remarks'])) $drawRow($pdf, 'Remarks:', $payment['remarks']);
    
    $pdf->Ln(20);
    
    // --- Signatures ---
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(90, 5, 'Received By', 0, 0, 'L');
    $pdf->Cell(90, 5, 'Authorized Signatory', 0, 1, 'R');
    
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(90, 5, $payment['received_by'] ?? 'Admin', 0, 0, 'L');
    $pdf->Cell(90, 5, 'For ' . utf8_decode($company_name), 0, 1, 'R');
    
    $pdf->Ln(20);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->SetTextColor(128, 128, 128);
    $pdf->Cell(0, 5, 'This is a computer-generated receipt.', 0, 1, 'C');
    
    // Output PDF
    $filename = 'receipt_' . $payment['id'] . '_' . date('Ymd') . '.pdf';
    $filepath = __DIR__ . '/../uploads/' . $filename;
    
    // Create uploads directory if not exists
    if (!is_dir(__DIR__ . '/../uploads/')) {
        mkdir(__DIR__ . '/../uploads/', 0777, true);
    }
    
    // Return PDF as string
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
                   f.flat_no, pr.project_name, pr.location as project_location,
                   p.name as customer_name, p.address as customer_address, p.mobile as customer_mobile, p.email as customer_email,
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
    $company_name = $settings['company_name'] ?? 'Builderz';
    $company_address = $settings['company_address'] ?? '123 Business Park, Sector 15, Mumbai - 400001';
    $company_phone = $settings['company_phone'] ?? '+91-12345678';
    $company_email = $settings['company_email'] ?? 'bookings@builderz.com';
    $company_website = $settings['company_website'] ?? 'www.builderz.com';
    $company_logo = $settings['company_logo'] ?? null;
    
    // Create PDF
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 10);
    $pdf->SetMargins(15, 10, 15);
    
    // Defines
    $primaryColor = [16, 25, 60]; // Navy Blue
    $alertColor = [220, 53, 69]; // Red for Cancellation
    
    // --- Header Section ---
    
    // Logo (Centered Top)
    $y = 10;
    if ($company_logo && file_exists(__DIR__ . '/../' . $company_logo)) {
        $pdf->Image(__DIR__ . '/../' . $company_logo, 90, $y, 30); 
        $y += 25;
    } else {
        $y += 5;
    }
    
    // Company Name
    $pdf->SetY($y);
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->Cell(0, 8, strtoupper($company_name), 0, 1, 'C');
    
    // Address & Contact
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 5, 'Registered Office: ' . $company_address, 0, 1, 'C');
    $contact_line = "Tel: $company_phone | Email: $company_email | $company_website";
    $pdf->Cell(0, 5, $contact_line, 0, 1, 'C');
    
    $pdf->Ln(5);
    
    // --- Title Bar (Red for Cancellation) ---
    $pdf->SetFillColor($alertColor[0], $alertColor[1], $alertColor[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'CANCELLATION RECEIPT', 0, 1, 'C', true);
    
    $pdf->Ln(5);
    
    // Ref No & Date Row
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(30, 6, 'Ref No:', 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(60, 6, 'CNL/' . str_pad($cancellation['id'], 6, '0', STR_PAD_LEFT), 0, 0);
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(70, 6, 'Date:', 0, 0, 'R');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(30, 6, date('d F Y', strtotime($cancellation['cancellation_date'])), 0, 1, 'R');
    
    $pdf->Ln(5);
    
    // Helper function for sections
    $drawSectionHeader = function($pdf, $title) use ($primaryColor) {
        $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 8, strtoupper($title), 0, 1, 'L');
        $pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetX() + 180, $pdf->GetY());
        $pdf->Ln(2);
    };

    $drawRow = function($pdf, $label, $value) {
        if(!$value) return; // Skip empty
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(50, 6, $label, 0, 0);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 6, $value, 0, 1);
    };
    
    // --- Cancelled Unit Details ---
    $drawSectionHeader($pdf, 'CANCELLED UNIT DETAILS');
    $drawRow($pdf, 'Project:', $cancellation['project_name']);
    $drawRow($pdf, 'Unit No:', $cancellation['flat_no']);
    $drawRow($pdf, 'Customer Name:', $cancellation['customer_name']);
    $drawRow($pdf, 'Original Booking Date:', date('d F Y', strtotime($cancellation['booking_date'])));
    
    $pdf->Ln(5);
    
    // --- Financial Summary Table ---
    $drawSectionHeader($pdf, 'REFUND CALCULATION');
    
    // Table Header
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetTextColor(0,0,0);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(140, 8, 'Description', 1, 0, 'L', true);
    $pdf->Cell(40, 8, 'Amount (INR)', 1, 1, 'R', true);
    
    $pdf->SetFont('Arial', '', 9);
    
    // Row 1: Total Paid
    $pdf->Cell(140, 8, 'Total Amount Paid by Customer', 1, 0);
    $pdf->Cell(40, 8, number_format($cancellation['total_paid'], 2), 1, 1, 'R');
    
    // Row 2: Deduction
    $pdf->Cell(140, 8, 'Less: Cancellation Charges / Deductions', 1, 0);
    $pdf->SetTextColor(220, 53, 69);
    $pdf->Cell(40, 8, '-' . number_format($cancellation['deduction_amount'], 2), 1, 1, 'R');
    $pdf->SetTextColor(0, 0, 0);
    
    // Net Refund
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(255, 240, 240); // Light Red
    $pdf->Cell(140, 10, 'Net Refund Payable', 1, 0, 'R', true);
    $pdf->Cell(40, 10, number_format($cancellation['refund_amount'], 2), 1, 1, 'R', true);
    
    $pdf->Ln(8);
    
    // Payment Mode Info
    $refund_info = 'Refund Mode: ' . ucfirst($cancellation['refund_mode']);
    if ($cancellation['refund_reference']) {
        $refund_info .= ' | Ref Number: ' . $cancellation['refund_reference'];
    }
    
    $drawRow($pdf, 'Refund Details:', $refund_info);
    if ($cancellation['cancellation_reason']) {
        $pdf->Ln(2);
        $pdf->MultiCell(0, 5, 'Reason for Cancellation: ' . utf8_decode($cancellation['cancellation_reason']));
    }
    
    $pdf->Ln(20);
    
    // --- Signatures ---
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(90, 5, 'Processed By', 0, 0, 'L');
    $pdf->Cell(90, 5, 'Authorized Signatory', 0, 1, 'R');
    
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(90, 5, $cancellation['processed_by_name'] ?? 'Admin', 0, 0, 'L');
    $pdf->Cell(90, 5, 'For ' . utf8_decode($company_name), 0, 1, 'R');
    
    $pdf->Ln(20);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->SetTextColor(128, 128, 128);
    $pdf->Cell(0, 5, 'This is a computer-generated document.', 0, 1, 'C');
    
    // Output
    $filename = 'cancellation_' . $cancellation['id'] . '.pdf';
    $content = $pdf->Output('S');
    
    return ['success' => true, 'filename' => $filename, 'content' => $content];
}

function generateBookingConfirmationPDF($booking_id) {
    global $db;
    
    // Check if FPDF is available
    $fpdf_path = __DIR__ . '/../vendor/fpdf/fpdf.php';
    if (!file_exists($fpdf_path)) {
        return ['success' => false, 'message' => 'FPDF library not installed.'];
    }
    
    require_once($fpdf_path);
    
    // Fetch Booking Details
    $sql = "SELECT b.*, 
                   f.flat_no, f.floor, f.area_sqft,
                   p.name as customer_name, p.mobile, p.email, p.address as customer_address,
                   pr.project_name, pr.location as project_location,
                   u.full_name as created_by_name
            FROM bookings b
            JOIN flats f ON b.flat_id = f.id
            JOIN parties p ON b.customer_id = p.id
            JOIN projects pr ON b.project_id = pr.id
            LEFT JOIN users u ON b.created_by = u.id
            WHERE b.id = ?";
            
    $stmt = $db->query($sql, [$booking_id]);
    $booking = $stmt->fetch();
    
    if (!$booking) {
        return ['success' => false, 'message' => 'Booking not found'];
    }
    
    // Get company details
    $settings = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    $company_name = $settings['company_name'] ?? 'Builderz';
    $company_address = $settings['company_address'] ?? '123 Business Park, Sector 15, Mumbai - 400001';
    $company_phone = $settings['company_phone'] ?? '+91-12345678';
    $company_email = $settings['company_email'] ?? 'bookings@builderz.com';
    $company_website = $settings['company_website'] ?? 'www.builderz.com';
    $company_logo = $settings['company_logo'] ?? null;
    
    // Create PDF
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 10);
    $pdf->SetMargins(15, 10, 15);
    
    // Defines
    $primaryColor = [16, 25, 60]; // Navy Blue
    
    // --- Header Section ---
    
    // Logo (Centered Top)
    $y = 10;
    if ($company_logo && file_exists(__DIR__ . '/../' . $company_logo)) {
        // Calculate X to center the image. Assuming width 30
        $pdf->Image(__DIR__ . '/../' . $company_logo, 90, $y, 30); 
        $y += 25;
    } else {
        $y += 5;
    }
    
    // Company Name
    $pdf->SetY($y);
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->Cell(0, 8, strtoupper($company_name), 0, 1, 'C');
    
    // Address & Contact
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 5, 'Registered Office: ' . $company_address, 0, 1, 'C');
    $contact_line = "Tel: $company_phone | Email: $company_email | $company_website";
    $pdf->Cell(0, 5, $contact_line, 0, 1, 'C');
    
    $pdf->Ln(5);
    
    // --- Title Bar ---
    $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'BOOKING CONFIRMATION', 0, 1, 'C', true);
    
    $pdf->Ln(5);
    
    // Booking ID & Date Row
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(30, 6, 'Booking ID:', 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(60, 6, 'BK' . str_pad($booking['id'], 6, '0', STR_PAD_LEFT), 0, 0);
    
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(70, 6, 'Date:', 0, 0, 'R');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(30, 6, date('d F Y', strtotime($booking['booking_date'])), 0, 1, 'R');
    
    $pdf->Ln(5);
    
    // Helper function for sections
    $drawSectionHeader = function($pdf, $title) use ($primaryColor) {
        $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 8, strtoupper($title), 0, 1, 'L');
        $pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetX() + 180, $pdf->GetY());
        $pdf->Ln(2);
    };

    $drawRow = function($pdf, $label, $value) {
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(50, 6, $label, 0, 0);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 6, $value, 0, 1);
    };

    // --- Customer Details ---
    $drawSectionHeader($pdf, 'CUSTOMER DETAILS');
    $drawRow($pdf, 'Customer Name:', $booking['customer_name']);
    $drawRow($pdf, 'Contact Number:', $booking['mobile']);
    $drawRow($pdf, 'Email Address:', $booking['email']);
    $drawRow($pdf, 'Address:', $booking['customer_address']);
    
    $pdf->Ln(5);
    
    // --- Property Details ---
    $drawSectionHeader($pdf, 'PROPERTY DETAILS');
    $drawRow($pdf, 'Project Name:', $booking['project_name']);
    $drawRow($pdf, 'Location:', $booking['project_location'] ?? 'N/A');
    $drawRow($pdf, 'Tower/Block:', $booking['block'] ?? 'N/A');
    $drawRow($pdf, 'Floor:', $booking['floor']);
    $drawRow($pdf, 'Flat/Unit Number:', $booking['flat_no']);
    $drawRow($pdf, 'Type:', ucfirst($booking['unit_type'] ?? 'Flat'));
    $drawRow($pdf, 'Carpet Area:', ($booking['area_sqft'] ?? 0) . ' sq. ft.');
    // Add Built-up/Super Built-up if available in DB, otherwise skip or use area_sqft as placeholder
    
    $pdf->Ln(5);
    
    // --- Payment Details ---
    $drawSectionHeader($pdf, 'PAYMENT DETAILS');
    
    // Table
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(140, 8, 'Description', 1, 0, 'L', true);
    $pdf->Cell(40, 8, 'Amount (INR)', 1, 1, 'R', true);
    
    $pdf->SetFont('Arial', '', 9);
    
    // 1. Total Agreement Value
    $pdf->Cell(140, 8, 'Total Agreement Value', 1, 0);
    $pdf->Cell(40, 8, number_format($booking['agreement_value'], 2), 1, 1, 'R');
    
    // 2. Booking Amount Paid
    $pdf->Cell(140, 8, 'Booking Amount Paid', 1, 0);
    $pdf->Cell(40, 8, number_format($booking['booking_amount'], 2), 1, 1, 'R');
    
    // 3. Balance Payable -> Highlighted
    $balance = $booking['agreement_value'] - $booking['total_received'];
    $pdf->SetFillColor(255, 255, 230); // Light Yellow
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(140, 8, 'Balance Payable', 1, 0, 'L', true);
    $pdf->Cell(40, 8, number_format($balance, 2), 1, 1, 'R', true);
    
    $pdf->Ln(5);
    
    // Payment Mode Info
    if (!empty($booking['payment_mode'])) {
        $drawRow($pdf, 'Payment Mode:', ucfirst($booking['payment_mode']));
        if (!empty($booking['transaction_id'])) $drawRow($pdf, 'Cheque/Transaction No:', $booking['transaction_id']);
        if (!empty($booking['bank_name'])) $drawRow($pdf, 'Bank Name:', $booking['bank_name']);
        if (!empty($booking['booking_date'])) $drawRow($pdf, 'Date of Payment:', date('d F Y', strtotime($booking['booking_date'])));
    }
    
    $pdf->Ln(8);
    
    // --- Terms ---
    $drawSectionHeader($pdf, 'TERMS & CONDITIONS');
    $pdf->SetFont('Arial', '', 8);
    $terms = [
        "This booking is subject to execution of the Sale Agreement within 30 days.",
        "The balance payment shall be made as per the payment schedule provided separately.",
        "Any cancellation of booking will be subject to cancellation charges as per company policy.",
        "All statutory approvals and clearances are in place for the project.",
        "This confirmation is subject to the terms and conditions mentioned in the Sale Agreement."
    ];
    
    foreach ($terms as $i => $term) {
        $pdf->Cell(5, 4, ($i+1) . '.', 0, 0);
        $pdf->MultiCell(0, 4, $term, 0, 1);
    }
    
    $pdf->Ln(20);
    
    // --- Signatures ---
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(90, 5, 'Authorized Signatory', 0, 0, 'L');
    $pdf->Cell(90, 5, "Customer's Signature", 0, 1, 'R');
    
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(90, 5, $company_name, 0, 0, 'L');
    $pdf->Cell(90, 5, '[' . $booking['customer_name'] . ']', 0, 1, 'R');
    
    $pdf->Ln(20);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->SetTextColor(128, 128, 128);
    $pdf->Cell(0, 5, 'This is a computer-generated document and does not require a physical signature.', 0, 1, 'C');
    
    // Output
    $filename = 'booking_conf_' . $booking['id'] . '.pdf';
    $content = $pdf->Output('S');
    
    return ['success' => true, 'filename' => $filename, 'content' => $content];
}
