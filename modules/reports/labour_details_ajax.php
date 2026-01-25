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
$labour_id = intval($_GET['labour_id'] ?? 0);

// Fetch labour challans
$sql = "SELECT c.*, pr.project_name, p.name as labour_name  
        FROM challans c
        JOIN projects pr ON c.project_id = pr.id
        JOIN parties p ON c.party_id = p.id
        WHERE c.party_id = ? AND c.challan_type = 'labour'
        ORDER BY c.challan_date DESC";

$stmt = $db->query($sql, [$labour_id]);
$challans = $stmt->fetchAll();

if (empty($challans)) {
    echo '<div style="text-align: center; padding: 40px; color: #64748b;">
            <i class="fas fa-search" style="font-size: 24px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
            No work challans found
          </div>';
    exit;
}
?>

<div class="table-responsive">
    <table class="modern-table">
        <thead>
            <tr>
                <th>Challan No</th>
                <th>Date</th>
                <th>Project</th>
                <th>Total Cost</th>
                <th>Paid</th>
                <th>Pending</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($challans as $challan): ?>
            <tr>
                <td><strong><?= htmlspecialchars($challan['challan_no']) ?></strong></td>
                <td><span style="color: #64748b;"><?= formatDate($challan['challan_date']) ?></span></td>
                <td><span style="color: #1e293b; font-weight: 500;"><?= htmlspecialchars($challan['project_name']) ?></span></td>
                <td style="font-family: monospace; font-weight: 600;"><?= formatCurrency($challan['total_amount']) ?></td>
                <td><span class="badge-soft green" style="font-family: monospace;"><?= formatCurrency($challan['paid_amount']) ?></span></td>
                <td>
                    <?php if ($challan['pending_amount'] > 0): ?>
                        <span class="badge-soft red" style="font-family: monospace;">
                            <?= formatCurrency($challan['pending_amount']) ?>
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
                        'approved' => 'blue',
                        'partial' => 'orange',
                        'paid' => 'green'
                    ];
                    $color = $status_colors[$challan['status']] ?? 'gray';
                    ?>
                    <span class="badge-soft <?= $color ?>">
                        <?= ucfirst($challan['status']) ?>
                    </span>
                </td>
                <td>
                    <?php if ($challan['pending_amount'] > 0): ?>
                        <button class="modern-btn" style="width: auto; padding: 6px 12px; font-size: 13px; min-width: auto;" onclick="showPaymentModal('labour_payment', <?= $challan['id'] ?>, <?= $challan['party_id'] ?>, <?= $challan['pending_amount'] ?>, <?= htmlspecialchars(json_encode($challan['labour_name'] ?? 'Labour'), ENT_QUOTES) ?>)">
                            <i class="fas fa-rupee-sign"></i> Pay
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
