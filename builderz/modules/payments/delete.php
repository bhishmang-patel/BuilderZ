<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'accountant']);

$id = intval($_GET['id'] ?? 0);
$db = Database::getInstance();

// Fetch payment details before deletion for redirection and updates
$payment = $db->select('payments', 'id = ?', [$id])->fetch();

if (!$payment) {
    setFlashMessage('error', 'Payment not found');
    redirect('modules/payments/index.php');
}

// Perform deletion
try {
    $db->beginTransaction();

    $db->delete('payments', 'id = ?', [$id]);

    // Recalculate totals based on reference type
    if ($payment['reference_type'] === 'booking') {
        updateBookingTotals($payment['reference_id']);
    } elseif ($payment['reference_type'] === 'bill') {
        updateBillPaidAmount($payment['reference_id']);
    } elseif ($payment['reference_type'] === 'challan') {
        updateChallanPaidAmount($payment['reference_id']);
    }

    logAudit('delete', 'payments', $id, $payment, null);

    $db->commit();
    setFlashMessage('success', 'Payment deleted successfully');
} catch (Exception $e) {
    $db->rollback();
    setFlashMessage('error', 'Failed to delete payment: ' . $e->getMessage());
}

// Redirect back to source
if (!empty($_GET['redirect'])) {
    redirect($_GET['redirect']);
} elseif ($payment['reference_type'] === 'booking') {
    redirect('modules/booking/view.php?id=' . $payment['reference_id']);
} else {
    redirect('modules/payments/index.php');
}
