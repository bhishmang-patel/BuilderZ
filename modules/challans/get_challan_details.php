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
$challan_id = intval($_GET['id'] ?? 0);

// Fetch challan details
$sql = "SELECT c.*, 
               p.name as party_name,
               p.address as vendor_address,
               p.gst_number,
               pr.project_name,
               u.full_name as created_by_name,
               au.full_name as approved_by_name
        FROM challans c
        JOIN parties p ON c.party_id = p.id
        JOIN projects pr ON c.project_id = pr.id
        LEFT JOIN users u ON c.created_by = u.id
        LEFT JOIN users au ON c.approved_by = au.id
        WHERE c.id = ?";

$stmt = $db->query($sql, [$challan_id]);
$challan = $stmt->fetch();

if (!$challan) {
    echo '<p class="text-center">Record not found</p>';
    exit;
}

// Fetch challan items
$sql = "SELECT ci.*, m.material_name, m.unit
        FROM challan_items ci
        JOIN materials m ON ci.material_id = m.id
        WHERE ci.challan_id = ?";
$stmt = $db->query($sql, [$challan_id]);
$items = $stmt->fetchAll();
?>


<style>
.challan-details-wrapper {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    padding: 0;
}

.details-section {
    background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
    padding: 24px;
    border-radius: 12px 12px 0 0;
    color: white;
    margin-bottom: 0;
}

.challan-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.challan-number {
    font-size: 24px;
    font-weight: 700;
    letter-spacing: 0.5px;
    color: #fff;
}

.challan-status-badge {
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-pending { background: #fef3c7; color: #b45309; }
.status-approved { background: #d1fae5; color: #047857; }
.status-paid { background: #dbeafe; color: #1e40af; }
.status-partial { background: #ffedd5; color: #c2410c; }

.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin-top: 15px;
    margin-bottom: 15px;
}

.info-item {
    background: rgba(255, 255, 255, 0.1);
    padding: 12px 20px;
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.info-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    opacity: 0.8;
    margin-bottom: 4px;
    color: #cbd5e1;
}

.info-value {
    font-size: 15px;
    font-weight: 600;
    color: #fff;
}

.details-body {
    background: white;
    padding: 24px;
    border-radius: 0 0 12px 12px;
}

.section-title {
    font-size: 13px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 1px solid #e2e8f0;
}

.materials-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 24px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
}

.materials-table thead {
    background: #f8fafc;
}

.materials-table th {
    padding: 12px 16px;
    text-align: left;
    color: #475569;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #e2e8f0;
}

.materials-table td {
    padding: 12px 16px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 14px;
    color: #334155;
}

.materials-table tbody tr:last-child td {
    border-bottom: none;
}

.amount-summary {
    background: #f8fafc;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    border: 1px solid #e2e8f0;
}

.amount-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #e2e8f0;
}

.amount-row:last-child {
    border-bottom: none;
    padding-top: 15px;
    padding-bottom: 0;
}

.amount-label {
    font-size: 14px;
    font-weight: 600;
    color: #64748b;
}

.amount-value {
    font-size: 16px;
    font-weight: 700;
    color: #0f172a;
}

.amount-total { color: #0f172a; font-size: 18px; }
.amount-paid { color: #10b981; }
.amount-pending { color: #f59e0b; }

.footer-info {
    background: #fff;
    padding: 15px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 12px;
    color: #94a3b8;
}

.footer-info strong {
    color: #475569;
}
</style>

<div class="challan-details-wrapper">
    <div class="details-section">
        <div class="challan-header">
            <div class="challan-number">
                <?= $challan['challan_type'] === 'material' ? '📦 ' : '👷 ' ?>
                <?= htmlspecialchars($challan['challan_no']) ?>
            </div>
            <span class="challan-status-badge status-<?= $challan['status'] ?>">
                <?= ucfirst($challan['status']) ?>
            </span>
        </div>
        
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">📅 Date</div>
                <div class="info-value"><?= formatDate($challan['challan_date']) ?></div>
            </div>
            <?php if (!empty($challan['vehicle_no'])): ?>
            <div class="info-item">
                <div class="info-label">🚚 Vehicle No</div>
                <div class="info-value"><?= htmlspecialchars($challan['vehicle_no']) ?></div>
            </div>
            <?php endif; ?>
            <div class="info-item">
                <div class="info-label"><?= $challan['challan_type'] === 'material' ? '🏪 Vendor' : '👤 Labour' ?></div>
                <div class="info-value"><?= htmlspecialchars($challan['party_name']) ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">🏗️ Project</div>
                <div class="info-value"><?= htmlspecialchars($challan['project_name']) ?></div>
            </div>
            
            <!-- Added details -->
            <div class="info-item">
                <div class="info-label">📍 Address</div>
                <div class="info-value" style="font-size: 13px;"><?= htmlspecialchars($challan['vendor_address'] ?: 'Not provided') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">📄 GST Number</div>
                <div class="info-value"><?= htmlspecialchars($challan['gst_number'] ?: 'N/A') ?></div>
            </div>
    </div>
    
    <div class="details-body">
        <?php if ($challan['challan_type'] === 'material' && !empty($items)): ?>
            <div class="section-title">📦 Material Items</div>
            <table class="materials-table">
                <thead>
                    <tr>
                        <th>Material</th>
                        <th style="text-align: center;">Quantity</th>
                        <th style="text-align: right;">Rate</th>
                        <th style="text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($item['material_name']) ?></strong></td>
                        <td style="text-align: center;">
                            <?= number_format($item['quantity'], 2) ?> <span style="color: #6c757d;"><?= ucfirst($item['unit']) ?></span>
                        </td>
                        <td style="text-align: right;"><?= formatCurrency($item['rate']) ?></td>
                        <td style="text-align: right;">
                            <strong style="color: #667eea;"><?= formatCurrency($item['total_amount']) ?></strong>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <div class="section-title">💰 Amount Summary</div>
        <div class="amount-summary">
            <div class="amount-row">
                <span class="amount-label">Total Amount</span>
                <span class="amount-value amount-total"><?= formatCurrency($challan['total_amount']) ?></span>
            </div>
            <div class="amount-row">
                <span class="amount-label">Paid Amount</span>
                <span class="amount-value amount-paid"><?= formatCurrency($challan['paid_amount']) ?></span>
            </div>
            <div class="amount-row">
                <span class="amount-label">Pending Amount</span>
                <span class="amount-value <?= $challan['pending_amount'] > 0 ? 'amount-pending' : 'amount-paid' ?>">
                    <?= formatCurrency($challan['pending_amount']) ?>
                </span>
            </div>
        </div>
        
        <div class="footer-info">
            <div style="margin-bottom: 5px;">
                <strong>Created By:</strong> <?= htmlspecialchars($challan['created_by_name']) ?>
            </div>
            <?php if ($challan['approved_by']): ?>
            <div>
                <strong>Approved By:</strong> <?= htmlspecialchars($challan['approved_by_name']) ?> 
                on <?= formatDate($challan['approved_at'], DATETIME_FORMAT) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
