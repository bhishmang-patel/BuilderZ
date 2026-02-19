<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/pdf_excel_helpers.php';

$db = Database::getInstance();





if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'project_manager']);

$booking_id = intval($_GET['id'] ?? 0);

if ($booking_id <= 0) {
    die('Invalid Booking ID');
}

// Generate PDF
$result = generateBookingConfirmationPDF($booking_id);

if ($result['success']) {
    // Determine content type
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $result['filename'] . '"');
    header('Content-Length: ' . strlen($result['content']));
    
    // Output PDF content
    echo $result['content'];
    exit;
} else {
    // Handle Error
    die('Error generating PDF: ' . $result['message']);
}
