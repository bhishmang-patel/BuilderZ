<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ColorHelper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();

$db = Database::getInstance();
$page_title = 'Cancelled Bookings';
$current_page = 'booking';

// Fetch all cancelled bookings with cancellation details
$sql = "SELECT bc.*, 
               b.booking_date, b.agreement_value, b.project_id, b.customer_id,
               f.flat_no,
               p.name as customer_name, p.mobile as customer_mobile,
               pr.project_name,
               u.full_name as processed_by_name
        FROM booking_cancellations bc
        JOIN bookings b ON bc.booking_id = b.id
        JOIN flats f ON b.flat_id = f.id
        JOIN parties p ON b.customer_id = p.id
        JOIN projects pr ON b.project_id = pr.id
        LEFT JOIN users u ON bc.processed_by = u.id
        ORDER BY bc.cancellation_date DESC, bc.created_at DESC";

$stmt = $db->query($sql);
$cancellations = $stmt->fetchAll();

// Calculate totals
$total_refunded = 0;
$total_deducted = 0;
$total_cancelled_value = 0;

foreach ($cancellations as $cancellation) {
    $total_refunded += $cancellation['refund_amount'];
    $total_deducted += $cancellation['deduction_amount'];
    $total_cancelled_value += $cancellation['agreement_value'];
}

include __DIR__ . '/../../includes/header.php';
?>

<!-- Include Booking CSS -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/booking.css">

<div class="row">
    <div class="col-12">
        
        <!-- Stats Row -->
        <div class="row" style="margin-bottom: 30px;">
            <div class="col-md-3">
                <div class="chart-card-custom" style="padding: 20px; text-align: center; border-left: 4px solid #667eea; margin-bottom: 0;">
                    <div style="font-size: 0.85rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Total Cancellations</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #667eea; margin-top: 5px;"><?= count($cancellations) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="chart-card-custom" style="padding: 20px; text-align: center; border-left: 4px solid #f59e0b; margin-bottom: 0;">
                    <div style="font-size: 0.85rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Cancelled Value</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #f59e0b; margin-top: 5px;"><?= formatCurrencyShort($total_cancelled_value) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="chart-card-custom" style="padding: 20px; text-align: center; border-left: 4px solid #ef4444; margin-bottom: 0;">
                    <div style="font-size: 0.85rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Total Refunded</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #ef4444; margin-top: 5px;"><?= formatCurrencyShort($total_refunded) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="chart-card-custom" style="padding: 20px; text-align: center; border-left: 4px solid #10b981; margin-bottom: 0;">
                    <div style="font-size: 0.85rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Total Deducted</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #10b981; margin-top: 5px;"><?= formatCurrencyShort($total_deducted) ?></div>
                </div>
            </div>
        </div>

        <div class="chart-card-custom" style="height: auto;">
            <!-- Header -->
            <div class="chart-header-custom" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <div class="chart-title-group">
                    <h3>
                        <div class="chart-icon-box red" style="background: #fef2f2; color: #ef4444;"><i class="fas fa-ban"></i></div>
                        Cancelled Bookings
                    </h3>
                    <div class="chart-subtitle">View and manage all cancelled booking records</div>
                </div>
                
                <a href="index.php" class="modern-btn" style="background: transparent; color: #64748b; border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 8px; padding: 10px 20px; text-decoration: none;">
                    <i class="fas fa-arrow-left"></i> Back to Bookings
                </a>
            </div>

            <!-- Modern Table -->
            <div class="table-responsive" style="overflow-y: visible;">
                <?php if (empty($cancellations)): ?>
                    <div style="text-align: center; padding: 4rem 2rem; color: #94a3b8;">
                        <i class="fas fa-inbox" style="font-size: 4rem; opacity: 0.3; margin-bottom: 1rem;"></i>
                        <div style="font-size: 1.1rem; font-weight: 600;">No Cancelled Bookings</div>
                        <p style="margin-top: 0.5rem;">There are no cancelled bookings in the system</p>
                    </div>
                <?php else: ?>
                    <table class="modern-table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th style="padding: 15px; vertical-align: middle;">CANCELLATION DATE</th>
                                <th style="padding: 15px; vertical-align: middle;">PROJECT</th>
                                <th style="padding: 15px; vertical-align: middle;">FLAT</th>
                                <th style="padding: 15px; vertical-align: middle;">CUSTOMER</th>
                                <th style="padding: 15px; vertical-align: middle;">AGREEMENT VALUE</th>
                                <th style="padding: 15px; vertical-align: middle;">TOTAL PAID</th>
                                <th style="padding: 15px; vertical-align: middle;">DEDUCTION</th>
                                <th style="padding: 15px; vertical-align: middle;">REFUND</th>
                                <th style="padding: 15px; vertical-align: middle;">REASON</th>
                                <th style="padding: 15px; vertical-align: middle;">PROCESSED BY</th>
                                <th style="padding: 15px; vertical-align: middle;">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            foreach ($cancellations as $cancellation): 
                                $color = ColorHelper::getProjectColor($cancellation['project_id']);
                                $initial = ColorHelper::getInitial($cancellation['project_name']);
                                
                                $custColor = ColorHelper::getCustomerColor($cancellation['customer_id']);
                                $custInitial = ColorHelper::getInitial($cancellation['customer_name']);
                            ?>
                            <tr>
                                <td style="vertical-align: middle;">
                                    <span style="font-size:12px; font-weight:600; color:#64748b;"><?= date('d M Y', strtotime($cancellation['cancellation_date'])) ?></span>
                                </td>
                                <td style="vertical-align: middle;">
                                    <div style="display:flex; align-items:center;">
                                        <div class="avatar-square" style="background: <?= $color ?>"><?= $initial ?></div>
                                        <span style="font-weight:700; margin-left: 0.75rem;"><?= htmlspecialchars($cancellation['project_name']) ?></span>
                                    </div>
                                </td>
                                <td style="white-space: nowrap; vertical-align: middle;"><span class="badge-pill gray"><?= htmlspecialchars($cancellation['flat_no']) ?></span></td>
                                <td style="vertical-align: middle;">
                                    <div style="display:flex; align-items:center;">
                                        <div class="avatar-circle" style="background: <?= $custColor ?>; color: #fff;"><?= $custInitial ?></div>
                                        <div style="display:flex; flex-direction:column;">
                                            <span style="line-height:1.2;"><?= htmlspecialchars($cancellation['customer_name']) ?></span>
                                            <span style="font-size:10px; color:#94a3b8;"><?= htmlspecialchars($cancellation['customer_mobile']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td style="vertical-align: middle;"><span style="font-weight:600;"><?= formatCurrencyShort($cancellation['agreement_value']) ?></span></td>
                                <td style="vertical-align: middle;"><span style="font-weight:600; color:#3b82f6;"><?= formatCurrencyShort($cancellation['total_paid']) ?></span></td>
                                <td style="vertical-align: middle;"><span style="font-weight:600; color:#10b981;"><?= formatCurrencyShort($cancellation['deduction_amount']) ?></span></td>
                                <td style="vertical-align: middle;"><span style="font-weight:600; color:#ef4444;"><?= formatCurrencyShort($cancellation['refund_amount']) ?></span></td>
                                <td style="vertical-align: middle;"><span style="font-size:12px; color:#64748b; max-width: 150px; display: inline-block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($cancellation['cancellation_reason']) ?>"><?= htmlspecialchars($cancellation['cancellation_reason']) ?></span></td>
                                <td style="vertical-align: middle;"><span class="badge-pill gray" style="font-size:10px;"><?= htmlspecialchars($cancellation['processed_by_name'] ?? 'System') ?></span></td>
                                <td style="vertical-align: middle;">
                                    <a href="cancellation_details.php?id=<?= $cancellation['id'] ?>" class="action-btn" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
