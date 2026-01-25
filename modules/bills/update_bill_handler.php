<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance();
        
        $bill_id = intval($_POST['id']);
        $bill_no = sanitize($_POST['bill_no']);
        $bill_date = $_POST['bill_date'];
        $amount = floatval($_POST['amount']);
        // vendor_id and challan_id typically don't change often in simple edit, but let's keep it simple.
        // If we allow changing vendor, it might complicate things (payments linked etc).
        // For now, let's allow editing basic details: No, Date, Amount, File.
        
        // Fetch existing
        $current = $db->select('bills', 'id = ?', [$bill_id])->fetch();
        if (!$current) {
            throw new Exception("Bill not found.");
        }

        // Validation
        if (empty($bill_no) || empty($bill_date) || $amount <= 0) {
            throw new Exception("Missing required fields");
        }

        // Check for duplicate bill no for this vendor (excluding self)
        $dup = $db->query("SELECT id FROM bills WHERE bill_no = ? AND party_id = ? AND id != ?", [$bill_no, $current['party_id'], $bill_id])->fetch();
        if ($dup) {
            throw new Exception("Bill Number '$bill_no' already exists for this vendor.");
        }
        
        // Handle Paid Amount Check
        // If new amount is less than paid_amount, we generally block or warn. 
        // For now, let's just allow it but update status correctly.
        $paid_amount = $current['paid_amount'];
        $new_status = 'pending';
        if ($paid_amount >= $amount) {
            $new_status = 'paid';
        } elseif ($paid_amount > 0) {
            $new_status = 'partial';
        }

        // Handle File Upload
        $file_path = $current['file_path'];
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
                // Optional: Delete old file if exists
            }
        }

        $update_data = [
            'bill_no' => $bill_no,
            'bill_date' => $bill_date,
            'amount' => $amount,
            'status' => $new_status,
            'file_path' => $file_path
        ];

        $db->update('bills', $update_data, 'id = ?', [$bill_id]);
        logAudit('update', 'bills', $bill_id, $current, $update_data);

        setFlashMessage('success', 'Bill updated successfully');

    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
    }

    // Redirect back to Vendor Page
    redirect('modules/vendors/index.php');
}
