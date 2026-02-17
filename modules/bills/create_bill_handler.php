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
        $db->beginTransaction();

        $vendor_id = intval($_POST['vendor_id']);
        $bill_no = sanitize($_POST['bill_no']);
        $bill_date = $_POST['bill_date'];
        
        // Amounts
        $amount = floatval($_POST['amount']); // Grand Total
        $tax_amount = floatval($_POST['tax_amount'] ?? 0);
        $taxable_amount = floatval($_POST['taxable_amount'] ?? 0);

        if (!$vendor_id || empty($bill_no) || empty($bill_date)) {
            throw new Exception("Missing required fields");
        }

        // Check Duplicate Bill No
        $dup = $db->query("SELECT id FROM bills WHERE bill_no = ? AND party_id = ?", [$bill_no, $vendor_id])->fetch();
        if ($dup) {
            throw new Exception("Bill Number '$bill_no' already exists for this vendor.");
        }

        // Standardize Inputs (Handle both JSON-based multi-select and simple Form-based single-select)
        $challan_ids = [];
        if (!empty($_POST['selected_challans_json'])) {
            $challan_ids = json_decode($_POST['selected_challans_json'], true);
        } elseif (!empty($_POST['challan_id'])) {
            $challan_ids = [$_POST['challan_id']];
        }

        $items = [];
        if (!empty($_POST['items_json'])) {
            $items = json_decode($_POST['items_json'], true);
        }

        // Validation: For now, we allow Direct Bills (no challans) via Vendor Page? 
        // The Vendor Modal has "Direct Bill". So if $challan_ids is empty, it's a Direct Bill.
        // We removed the "No challans selected" exception to allow Direct Bills.
        $file_path = null;
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

        // Create Bill
        $bill_data = [
            'bill_no' => $bill_no,
            'bill_date' => $bill_date,
            'party_id' => $vendor_id,
            'amount' => $amount, // This is Grand Total
            'tax_amount' => $tax_amount,
            'taxable_amount' => $taxable_amount,
            'status' => 'pending',
            'file_path' => $file_path,
            'created_by' => $_SESSION['user_id']
            // challan_id is left null or ignored as we use bill_id in challans table
        ];

        $bill_id = $db->insert('bills', $bill_data);

        // Update Challan Items with Rates & Tax (Only if items provided)
        if (!empty($items)) {
            foreach ($items as $item) {
                $itemId = intval($item['id']);
                $rate = floatval($item['rate']);
                $taxRate = floatval($item['tax_rate']);
                
                // Calculate Item Totals
                $qty = floatval($item['quantity']);
                $taxable = $qty * $rate;
                $taxAmt = $taxable * ($taxRate / 100);
                $total = $taxable + $taxAmt;

                $updateData = [
                    'rate' => $rate,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmt,
                    'total_amount' => $total
                ];
                $db->update('challan_items', $updateData, 'id = ?', [$itemId]);
            }
        }

        // Update Challans (Link to Bill & Update Totals)
        foreach ($challan_ids as $cid) {
            // If we updated items, we should recalculate totals. 
            // If this is a simple "Add Bill" from Vendor modal (no items), we might NOT want to overwrite totals 
            // unless we trust the current DB state.
            // Safe approach: Always calculate from DB items to ensure consistency.
            
            $totals = $db->query("SELECT SUM(total_amount) as grand_total, SUM(tax_amount) as tax_total FROM challan_items WHERE challan_id = ?", [$cid])->fetch();
            
            $grand = $totals['grand_total'] ?? 0;
            $tax = $totals['tax_total'] ?? 0;

            // If items were empty (Simple Bill), grand_total might be 0 if challan_items are not populated with rates yet?
            // Usually Challan Creation involves Rates? If Challan is "Material Inward" (PO based), it has rates.
            // If manual challan, maybe not?
            // Let's assume existing total_amount in challan is fallback if $grand is 0.
            
            $updateData = ['bill_id' => $bill_id];
            
            if (!empty($items) && $grand > 0) {
                 $updateData['total_amount'] = $grand;
                 $updateData['grand_total'] = $grand;
                 $updateData['tax_amount'] = $tax;
            }

            $db->update('challans', $updateData, 'id = ?', [$cid]);
        }

        logAudit('create', 'bills', $bill_id, null, $bill_data);
        $db->commit();

        setFlashMessage('success', "Bill #$bill_no created successfully.");
        redirect('modules/vendors/index.php'); // Or to bill view

    } catch (Exception $e) {
        if (isset($db)) $db->rollback();
        setFlashMessage('error', $e->getMessage());
        redirect('modules/vendors/index.php');
    }
} else {
    redirect('modules/vendors/index.php');
}
