<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'project_manager', 'accountant']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$bill_id = $_POST['bill_id'] ?? null;
$status = $_POST['status'] ?? null;

// Validate CSRF
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    setFlashMessage('error', 'Invalid security token');
    if ($bill_id) {
        redirect('modules/vendors/view_bill.php?id=' . $bill_id);
    } else {
        redirect('modules/vendors/index.php');
    }
}

if (!$bill_id || !in_array($status, ['approved', 'rejected'])) {
    setFlashMessage('error', 'Invalid parameters');
    redirect('modules/vendors/index.php');
}

$db = Database::getInstance();

try {
    $db->beginTransaction();

    // 1. Get current bill status
    $bill = $db->query("SELECT * FROM bills WHERE id = ?", [$bill_id])->fetch();

    if (!$bill) {
        throw new Exception("Bill not found");
    }

    if ($bill['status'] !== 'pending') {
        throw new Exception("Only pending bills can be processed");
    }

    // 2. Update Bill Status
    $db->query("UPDATE bills SET status = ? WHERE id = ?", [$status, $bill_id]);

    // 3. Handle specific status logic
    if ($status === 'approved') {
        // Find linked challans
        $challans = $db->query("SELECT id, project_id FROM challans WHERE bill_id = ?", [$bill_id])->fetchAll();
        
        foreach ($challans as $c) {
            // Mark challan as 'billed' to lock it? 
            // Or just leave it as 'approved' (challans are already approved before billing)
            // But we might want to ensure they are locked.
            // For now, let's just log or ensure status consistency if needed.
            // Actually, challans should already be 'approved' to be billed.
            
            // If we need to update inventory or ledgers, usually that happens on Challan Approval.
            // But if BILL approval triggers financial updates (e.g. Vendor Ledger), do it here.
            
            // Update Vendor Ledger (Bill Amount -> Credit Vendor)
            // Check if entry exists?
            // For now, simple ledger logic usually happens on Bill Approval.
        }

        // Add to standard financial ledger if needed
        // $db->query("INSERT INTO vendor_ledger (...) VALUES (...)");
    }

    $db->commit();
    setFlashMessage('success', "Bill has been " . ucfirst($status));
    
} catch (Exception $e) {
    $db->rollBack();
    setFlashMessage('error', "Error: " . $e->getMessage());
}

// Redirect back to bill view
redirect("modules/vendors/view_bill.php?id=" . $bill_id);
