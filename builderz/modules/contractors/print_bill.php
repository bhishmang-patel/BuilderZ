<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();

$db = Database::getInstance();
$id = $_GET['id'] ?? null;

if (!$id) die("Bill ID missing");

// Fetch Bill Details
$sql = "SELECT c.*, 
               p.name as contractor_name, p.address as contractor_address, p.gst_number as contractor_gst, p.pan_number as contractor_pan,
               pr.project_name, pr.location as project_location,
               wo.work_order_no
        FROM challans c
        JOIN parties p ON c.party_id = p.id
        JOIN projects pr ON c.project_id = pr.id
        LEFT JOIN work_orders wo ON c.work_order_id = wo.id
        WHERE c.id = ? AND c.challan_type = 'contractor'";

$stmt = $db->query($sql, [$id]);
$bill = $stmt->fetch();

if (!$bill) die("Bill not found");

// Company Settings
$company = [];
try {
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('company_name', 'company_address', 'company_phone', 'company_email')");
    $company = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {}

$companyName = $company['company_name'] ?? APP_NAME;
$companyAddr = $company['company_address'] ?? 'Address Line 1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bill #<?= $bill['challan_no'] ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; line-height: 1.5; font-size: 14px; }
        .container { max-width: 800px; margin: 0 auto; padding: 30px; border: 1px solid #eee; }
        .header { display: flex; justify-content: space-between; margin-bottom: 40px; padding-bottom: 20px; border-bottom: 2px solid #333; }
        .company-info h1 { margin: 0 0 5px 0; color: #2c3e50; font-size: 24px; }
        .bill-title { text-align: right; }
        .bill-title h2 { margin: 0; color: #e74c3c; text-transform: uppercase; letter-spacing: 2px; }
        
        .info-grid { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .box { width: 45%; }
        .box-title { font-weight: bold; border-bottom: 1px solid #ccc; margin-bottom: 8px; padding-bottom: 3px; color: #555; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th { background: #f8f9fa; padding: 10px; text-align: left; border-bottom: 2px solid #333; }
        td { padding: 10px; border-bottom: 1px solid #eee; }
        .th-r, .td-r { text-align: right; }
        
        .totals-section { float: right; width: 40%; }
        .total-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
        .total-row.grand { font-weight: bold; font-size: 16px; border-top: 2px solid #333; border-bottom: none; }
        
        .signature-section { margin-top: 80px; display: flex; justify-content: space-between; }
        .sig-box { text-align: center; border-top: 1px solid #333; width: 30%; padding-top: 10px; }
        
        @media print {
            .no-print { display: none; }
            .container { border: none; padding: 0; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="no-print" style="text-align:center; margin-bottom:20px;">
        <button onclick="window.print()" style="padding:10px 20px; cursor:pointer;">Print Bill</button>
    </div>

    <div class="container">
        <div class="header">
            <div class="company-info">
                <h1><?= $companyName ?></h1>
                <div><?= $companyAddr ?></div>
                <div><?= $company['company_phone'] ?? '' ?> <?= $company['company_email'] ?? '' ?></div>
            </div>
            <div class="bill-title">
                <h2>Contractor Bill</h2>
                <div># <?= $bill['challan_no'] ?></div>
                <div>Date: <?= formatDate($bill['challan_date']) ?></div>
            </div>
        </div>

        <div class="info-grid">
            <div class="box">
                <div class="box-title">Contractor Details</div>
                <strong><?= htmlspecialchars($bill['contractor_name']) ?></strong><br>
                <?= htmlspecialchars($bill['contractor_address']) ?><br>
                <?php if($bill['contractor_pan']): ?>PAN: <?= $bill['contractor_pan'] ?><br><?php endif; ?>
                <?php if($bill['contractor_gst']): ?>GST: <?= $bill['contractor_gst'] ?><br><?php endif; ?>
            </div>
            <div class="box">
                <div class="box-title">Project & Contract</div>
                <strong>Project:</strong> <?= htmlspecialchars($bill['project_name']) ?><br>
                <strong>Work Order:</strong> <?= htmlspecialchars($bill['work_order_no'] ?? 'N/A') ?><br>
                <strong>Work Period:</strong><br>
                <?= formatDate($bill['work_from_date']) ?> to <?= formatDate($bill['work_to_date']) ?>
            </div>
        </div>

        <div class="box-title">Work Description</div>
        <p style="margin-bottom:30px;"><?= nl2br(htmlspecialchars($bill['work_description'])) ?></p>

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="th-r">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Basic Bill Amount</td>
                    <td class="td-r"><?= formatCurrency($bill['bill_amount']) ?></td>
                </tr>
                <?php if($bill['gst_amount'] > 0): ?>
                <tr>
                    <td>GST Amount</td>
                    <td class="td-r">+ <?= formatCurrency($bill['gst_amount']) ?></td>
                </tr>
                <?php endif; ?>
                <?php if($bill['tds_amount'] > 0): ?>
                <tr>
                    <td>TDS Deducted</td>
                    <td class="td-r">- <?= formatCurrency($bill['tds_amount']) ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="totals-section">
            <div class="total-row grand">
                <span>Net Payable:</span>
                <span><?= formatCurrency($bill['final_payable_amount']) ?></span>
            </div>
            <div style="font-size:12px; margin-top:5px; text-align:right;">
                (<?= convertNumberToWords($bill['final_payable_amount']) ?> Only)
            </div>
        </div>
        <div style="clear:both;"></div>

        <div class="signature-section">
            <div class="sig-box">Checked By</div>
            <div class="sig-box">Contractor Signature</div>
            <div class="sig-box">Approved By</div>
        </div>
    </div>

</body>
</html>
