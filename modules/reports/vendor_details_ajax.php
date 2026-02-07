<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'accountant', 'project_manager']);

$db = Database::getInstance();
$vendor_id = intval($_GET['vendor_id'] ?? 0);

// Fetch vendor challans
// Fetch pending vendor bills
$sql = "SELECT b.id, b.bill_no, b.bill_date, b.amount as total_amount, b.paid_amount, (b.amount - b.paid_amount) as pending_amount,
               b.status, p.name as vendor_name, c.challan_no
        FROM bills b
        JOIN parties p ON b.party_id = p.id
        LEFT JOIN challans c ON b.challan_id = c.id
        WHERE b.party_id = ? AND b.status IN ('pending', 'partial')
        ORDER BY b.bill_date DESC";

$stmt = $db->query($sql, [$vendor_id]);
$bills = $stmt->fetchAll();

if (empty($bills)) {
    echo '<div style="text-align: center; padding: 40px; color: #64748b;">
            <i class="fas fa-check-circle" style="font-size: 24px; margin-bottom: 10px; display: block; opacity: 0.5; color: #10b981;"></i>
            No pending bills found. All clear!
          </div>';
    exit;
}
?>

<div class="table-responsive">
    <table class="modern-table">
        <thead>
            <tr>
                <th>Bill No</th>
                <th>Date</th>
                <th>Ref Challan</th>
                <th>Total Amount</th>
                <th>Paid</th>
                <th>Pending</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($bills as $bill): ?>
            <tr>
                <td><strong><?= htmlspecialchars($bill['bill_no']) ?></strong></td>
                <td><span style="color: #64748b;"><?= formatDate($bill['bill_date']) ?></span></td>
                <td>
                    <?php if($bill['challan_no']): ?>
                        <span class="badge-soft blue" style="font-size: 11px;"><?= htmlspecialchars($bill['challan_no']) ?></span>
                    <?php else: ?>
                        <span style="color: #cbd5e1;">-</span>
                    <?php endif; ?>
                </td>
                <td style="font-family: monospace; font-weight: 600;"><?= formatCurrency($bill['total_amount']) ?></td>
                <td><span class="badge-soft green" style="font-family: monospace;"><?= formatCurrency($bill['paid_amount']) ?></span></td>
                <td>
                    <?php if ($bill['pending_amount'] > 0): ?>
                        <span class="badge-soft red" style="font-family: monospace;">
                            <?= formatCurrency($bill['pending_amount']) ?>
                        </span>
                    <?php else: ?>
                         <span class="badge-soft green" style="font-size: 11px;">
                            <i class="fas fa-check"></i> Paid
                        </span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php
                    $status_colors = [
                        'pending' => 'orange',
                        'partial' => 'blue',
                        'paid' => 'green'
                    ];
                    $color = $status_colors[$bill['status']] ?? 'gray';
                    ?>
                    <span class="badge-soft <?= $color ?>">
                        <?= ucfirst($bill['status']) ?>
                    </span>
                </td>
                <td>
                    <?php if ($bill['pending_amount'] > 0): ?>
                        <button class="modern-btn" style="width: auto; padding: 6px 12px; font-size: 13px; min-width: auto;" onclick="showPaymentModal('vendor_bill_payment', <?= $bill['id'] ?>, <?= $vendor_id ?>, <?= $bill['pending_amount'] ?>, <?= htmlspecialchars(json_encode($bill['vendor_name'] ?? 'Vendor'), ENT_QUOTES) ?>)">
                            <i class="fas fa-rupee-sign"></i> Pay
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
