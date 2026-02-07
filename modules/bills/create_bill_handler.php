<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
         setFlashMessage('error', 'Security token expired. Please try again.');
         redirect('modules/vendors/index.php');
    }

    try {
        $db = Database::getInstance();
        
        $vendor_id = intval($_POST['vendor_id']);
        $challan_id = !empty($_POST['challan_id']) ? intval($_POST['challan_id']) : null;
        $bill_no = sanitize($_POST['bill_no']);
        $bill_date = $_POST['bill_date'];
        $amount = floatval($_POST['amount']);
        $amount = floatval($_POST['amount']);
        $file_path = null;
        
        // Security check: If linked to a challan, ensure it is APPROVED
        if ($challan_id) {
            $challan = $db->query("SELECT status FROM challans WHERE id = ?", [$challan_id])->fetch();
            if (!$challan || $challan['status'] !== 'approved') {
                 throw new Exception("Cannot create bill for Pending Challan. Please approve the challan first.");
            }
        }

        // Validation
        if (!$vendor_id || empty($bill_no) || empty($bill_date) || $amount <= 0) {
            throw new Exception("Missing required fields");
        }

        // Check for duplicate bill no for this vendor
        $dup = $db->query("SELECT id FROM bills WHERE bill_no = ? AND party_id = ?", [$bill_no, $vendor_id])->fetch();
        if ($dup) {
            throw new Exception("Bill Number '$bill_no' already exists for this vendor.");
        }

        // Handle File Upload
        if (isset($_FILES['bill_file']) && $_FILES['bill_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../uploads/bills/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileExt = pathinfo($_FILES['bill_file']['name'], PATHINFO_EXTENSION);
            $fileName = 'bill_' . time() . '_' . uniqid() . '.' . $fileExt;
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['bill_file']['tmp_name'], $targetPath)) {
                $file_path = 'uploads/bills/' . $fileName;
            }
        }

        $bill_data = [
            'bill_no' => $bill_no,
            'bill_date' => $bill_date,
            'party_id' => $vendor_id,
            'challan_id' => $challan_id,
            'amount' => $amount,
            'status' => 'pending',
            'file_path' => $file_path,
            'created_by' => $_SESSION['user_id']
        ];

        $bill_id = $db->insert('bills', $bill_data);
        logAudit('create', 'bills', $bill_id, null, $bill_data);

        setFlashMessage('success', 'Bill added successfully');

    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
    }

    // Redirect back to Vendor Page (or wherever came from)
    redirect('modules/vendors/index.php');
}
