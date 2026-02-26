<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/pdf_excel_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'project_manager', 'accountant']);

$db = Database::getInstance();
$demand_id = $_GET['id'] ?? null;

if (!$demand_id) {
    die("Invalid Demand ID");
}

$pdfResult = generateDemandPDF($demand_id);

if ($pdfResult && $pdfResult['success']) {
    // Clean output buffer before sending headers
    if (ob_get_length()) {
        ob_end_clean();
    }
    
    // Send PDF headers to browser
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $pdfResult['filename'] . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    // Output the PDF binary string
    echo $pdfResult['content'];
    exit;
} else {
    // If generation fails, show the error
    die("Error generating PDF: " . ($pdfResult['message'] ?? 'Unknown error'));
}
