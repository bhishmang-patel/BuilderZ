<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/ProcurementService.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Auth optional for print? Usually yes, but verify. Keeping it secure.
requireAuth();

$db = Database::getInstance();
$service = new ProcurementService();
$id = $_GET['id'] ?? null;
$po = $service->getPOById($id);

if (!$po) die("Order not found");

// Fetch company settings safely
$company = [];
try {
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('company_name', 'company_address', 'company_phone', 'company_email', 'company_logo', 'po_terms')");
    $company = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    // Silently continue if settings table issues, use defaults
    error_log("Settings fetch error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order #<?= $po['po_number'] ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; line-height: 1.6; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; border: 1px solid #eee; }
        .header { display: flex; justify-content: space-between; margin-bottom: 40px; border-bottom: 2px solid #333; padding-bottom: 20px; }
        .company-info h1 { margin: 0; color: #2c3e50; }
        .po-title { text-align: right; }
        .po-title h2 { margin: 0; color: #e74c3c; text-transform: uppercase; letter-spacing: 2px; }
        .info-grid { display: flex; justify-content: space-between; margin-bottom: 40px; }
        .box { width: 45%; }
        .box h3 { border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-bottom: 10px; font-size: 16px; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th { background: #f8f9fa; border-bottom: 2px solid #333; padding: 10px; text-align: left; font-weight: 600; }
        td { padding: 10px; border-bottom: 1px solid #eee; }
        .totals { float: right; width: 40%; }
        .totals-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
        .totals-row.grand { font-weight: bold; font-size: 1.2em; border-top: 2px solid #333; border-bottom: none; }
        .footer { margin-top: 80px; text-align: center; font-size: 12px; color: #777; border-top: 1px solid #eee; padding-top: 20px; }
        .signature { margin-top: 60px; display: flex; justify-content: space-between; }
        .sig-box { text-align: center; width: 200px; border-top: 1px solid #333; padding-top: 10px; }
        
        @media print {
            .no-print { display: none; }
            .container { border: none; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="no-print" style="text-align: center; margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer;">Print Order</button>
    </div>

    <div class="container">
        <div class="header">
            <div class="company-info">
                <h1><?= $company['company_name'] ?? APP_NAME ?></h1>
                <div><?= $company['company_address'] ?? 'Address Line 1' ?></div>
                <div>Ph: <?= $company['company_phone'] ?? '' ?> | Email: <?= $company['company_email'] ?? '' ?></div>
            </div>
            <div class="po-title">
                <h2>Purchase Order</h2>
                <div># <?= $po['po_number'] ?></div>
                <div>Date: <?= formatDate($po['order_date']) ?></div>
            </div>
        </div>

        <div class="info-grid">
            <div class="box">
                <h3>Vendor</h3>
                <strong><?= htmlspecialchars($po['vendor_name']) ?></strong><br>
                <!-- Address would go here if we fetched it -->
            </div>
            <div class="box">
                <h3>Ship To</h3>
                <strong><?= htmlspecialchars($po['project_name']) ?></strong><br>
                <!-- Site address would go here -->
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Item Description</th>
                    <th style="text-align: center;">Qty</th>
                    <th style="text-align: center;">Unit</th>
                    <th style="text-align: right;">Rate</th>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($po['items'] as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['material_name']) ?></td>
                    <td style="text-align: center;"><?= $item['quantity'] ?></td>
                    <td style="text-align: center;"><?= strtoupper($item['unit']) ?></td>
                    <td style="text-align: right;"><?= formatCurrency($item['rate']) ?></td>
                    <td style="text-align: right;"><?= formatCurrency($item['total_amount']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals">
            <div class="totals-row grand">
                <span>Total Amount:</span>
                <span><?= formatCurrency($po['total_amount']) ?></span>
            </div>
        </div>
        <div style="clear: both;"></div>

        <?php if ($po['notes']): ?>
        <div style="margin-top: 30px; background: #f9f9f9; padding: 15px;">
            <strong>Notes / Instructions:</strong><br>
            <?= nl2br(htmlspecialchars($po['notes'])) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($company['po_terms'])): ?>
        <div style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 10px;">
            <strong>Terms & Conditions:</strong><br>
            <div style="font-size: 11px; color: #555; margin-top: 5px;">
                <?= nl2br(htmlspecialchars($company['po_terms'])) ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="signature">
            <div class="sig-box">
                Authorized Signatory
            </div>
            <div class="sig-box">
                Vendor Acceptance
            </div>
        </div>

        <div class="footer">
            This is a computer-generated document.
        </div>
    </div>
</body>
</html>
