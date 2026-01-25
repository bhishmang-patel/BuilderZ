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
$page_title = 'Cancellation Details';
$current_page = 'booking';

$cancellation_id = intval($_GET['id'] ?? 0);

// Fetch cancellation details with booking and customer info
$sql = "SELECT bc.*, 
               b.booking_date, b.agreement_value, b.flat_id,
               f.flat_no, f.area_sqft,
               p.name as customer_name, p.mobile as customer_mobile, p.email as customer_email,
               pr.project_name,
               u.full_name as processed_by_name
        FROM booking_cancellations bc
        JOIN bookings b ON bc.booking_id = b.id
        JOIN flats f ON b.flat_id = f.id
        JOIN parties p ON b.customer_id = p.id
        JOIN projects pr ON b.project_id = pr.id
        LEFT JOIN users u ON bc.processed_by = u.id
        WHERE bc.id = ?";

$stmt = $db->query($sql, [$cancellation_id]);
$cancellation = $stmt->fetch();

if (!$cancellation) {
    setFlashMessage('error', 'Cancellation record not found');
    redirect('modules/booking/index.php');
}

// Fetch payment history for the booking
$sql = "SELECT * FROM payments 
        WHERE reference_type = 'booking' AND reference_id = ?
        ORDER BY payment_date ASC";
$stmt = $db->query($sql, [$cancellation['booking_id']]);
$payments = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<style>
/* Modern Cancellation Details Design */
.cancellation-details-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

.details-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    margin-bottom: 1.5rem;
    overflow: hidden;
}

.details-header {
    background: linear-gradient(135deg, #f5576c 0%, #c92a3e 100%);
    padding: 1.25rem 1.5rem;
    color: white;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.details-header h3 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.status-badge {
    background: rgba(255, 255, 255, 0.25);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.95rem;
}

.details-body {
    padding: 2rem;
}

.summary-cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}

.summary-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 1.25rem;
    border-radius: 10px;
    border-left: 4px solid;
    text-align: center;
}

.summary-card.received {
    border-left-color: #38ef7d;
}

.summary-card.deduction {
    border-left-color: #f5576c;
}

.summary-card.refund {
    border-left-color: #667eea;
}

.summary-card.agreement {
    border-left-color: #ffd89b;
}

.summary-label {
    font-size: 0.85rem;
    color: #6c757d;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.summary-amount {
    font-size: 1.4rem;
    font-weight: 700;
    margin: 0;
}

.summary-card.received .summary-amount {
    color: #38ef7d;
}

.summary-card.deduction .summary-amount {
    color: #f5576c;
}

.summary-card.refund .summary-amount {
    color: #667eea;
}

.summary-card.agreement .summary-amount {
    color: #f5a623;
}

.info-section {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 1.5rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
}

.info-section h5 {
    color: #2c3e50;
    font-weight: 600;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #dee2e6;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem;
    background: white;
    border-radius: 6px;
}

.info-label {
    font-weight: 600;
    color: #6c757d;
    font-size: 0.9rem;
}

.info-value {
    font-weight: 700;
    color: #2c3e50;
    font-size: 0.95rem;
}

.payment-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.payment-table thead {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.payment-table th {
    padding: 0.875rem;
    text-align: left;
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.9rem;
    border-bottom: 2px solid #dee2e6;
}

.payment-table td {
    padding: 0.875rem;
    border-bottom: 1px solid #f0f0f0;
    font-size: 0.9rem;
}

.payment-table tfoot {
    background: linear-gradient(135deg, #e0f7fa 0%, #e8f5e9 100%);
    font-weight: 700;
}

.payment-table tfoot td {
    padding: 1rem 0.875rem;
    font-size: 1.05rem;
    color: #11998e;
}

.action-buttons {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 2rem;
}

.btn-primary-custom {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
    border: none;
    padding: 0.875rem 2rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-primary-custom:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(17, 153, 142, 0.4);
    color: white;
    text-decoration: none;
}

.btn-secondary-custom {
    background: #6c757d;
    color: white;
    border: none;
    padding: 0.875rem 2rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-secondary-custom:hover {
    background: #5a6268;
    color: white;
    text-decoration: none;
}

.remarks-box {
    background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
    border-left: 4px solid #ffc107;
    padding: 1.25rem;
    border-radius: 8px;
    margin-top: 1.5rem;
}

.remarks-box h6 {
    color: #856404;
    font-weight: 600;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.remarks-box p {
    color: #856404;
    margin: 0;
    line-height: 1.6;
}
</style>

<div class="cancellation-details-container">
    <!-- Main Details Card -->
    <div class="details-card">
        <div class="details-header">
            <h3>
                <i class="fas fa-ban"></i>
                Booking Cancellation Details
            </h3>
            <div class="status-badge">
                CANCELLED
            </div>
        </div>
        <div class="details-body">
            <!-- Summary Cards -->
            <div class="summary-cards">
                <div class="summary-card agreement">
                    <div class="summary-label">Agreement Value</div>
                    <div class="summary-amount"><?= formatCurrency($cancellation['agreement_value']) ?></div>
                </div>
                <div class="summary-card received">
                    <div class="summary-label">Total Received</div>
                    <div class="summary-amount"><?= formatCurrency($cancellation['total_paid']) ?></div>
                </div>
                <div class="summary-card deduction">
                    <div class="summary-label">Deduction Charges</div>
                    <div class="summary-amount"><?= formatCurrency($cancellation['deduction_amount']) ?></div>
                </div>
                <div class="summary-card refund">
                    <div class="summary-label">Refund Amount</div>
                    <div class="summary-amount"><?= formatCurrency($cancellation['refund_amount']) ?></div>
                </div>
            </div>

            <!-- Booking Information -->
            <div class="info-section">
                <h5>
                    <i class="fas fa-building"></i>
                    Booking Information
                </h5>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Project:</span>
                        <span class="info-value"><?= htmlspecialchars($cancellation['project_name']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Flat No:</span>
                        <span class="info-value"><?= htmlspecialchars($cancellation['flat_no']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Area:</span>
                        <span class="info-value"><?= number_format($cancellation['area_sqft'], 2) ?> Sqft</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Booking Date:</span>
                        <span class="info-value"><?= formatDate($cancellation['booking_date']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Customer Information -->
            <div class="info-section">
                <h5>
                    <i class="fas fa-user-tie"></i>
                    Customer Information
                </h5>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Name:</span>
                        <span class="info-value"><?= htmlspecialchars($cancellation['customer_name']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Mobile:</span>
                        <span class="info-value"><?= htmlspecialchars($cancellation['customer_mobile']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?= htmlspecialchars($cancellation['customer_email']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Cancellation Information -->
            <div class="info-section">
                <h5>
                    <i class="fas fa-file-alt"></i>
                    Cancellation Information
                </h5>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Cancellation Date:</span>
                        <span class="info-value"><?= formatDate($cancellation['cancellation_date']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Cancellation Reason:</span>
                        <span class="info-value"><?= htmlspecialchars($cancellation['cancellation_reason']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Deduction Reason:</span>
                        <span class="info-value"><?= htmlspecialchars($cancellation['deduction_reason']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Processed By:</span>
                        <span class="info-value"><?= htmlspecialchars($cancellation['processed_by_name']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Refund Mode:</span>
                        <span class="info-value"><?= ucfirst($cancellation['refund_mode']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Refund Reference:</span>
                        <span class="info-value"><?= htmlspecialchars($cancellation['refund_reference']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Payment History -->
            <div class="info-section">
                <h5>
                    <i class="fas fa-history"></i>
                    Payment History (<?= count($payments) ?> Installments)
                </h5>
                <?php if (empty($payments)): ?>
                    <p style="text-align: center; color: #6c757d; padding: 2rem;">No payments were received for this booking</p>
                <?php else: ?>
                    <table class="payment-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Mode</th>
                                <th>Reference No</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $index => $payment): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= formatDate($payment['payment_date']) ?></td>
                                <td><strong style="color: #38ef7d;"><?= formatCurrency($payment['amount']) ?></strong></td>
                                <td><?= ucfirst($payment['payment_mode']) ?></td>
                                <td><?= htmlspecialchars($payment['reference_no']) ?></td>
                                <td><?= htmlspecialchars($payment['remarks']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2"><strong>TOTAL RECEIVED</strong></td>
                                <td colspan="4"><strong><?= formatCurrency($cancellation['total_paid']) ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Remarks -->
            <?php if (!empty($cancellation['remarks'])): ?>
            <div class="remarks-box">
                <h6>
                    <i class="fas fa-comment-alt"></i>
                    Additional Remarks
                </h6>
                <p><?= nl2br(htmlspecialchars($cancellation['remarks'])) ?></p>
            </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="index.php" class="btn-secondary-custom">
                    <i class="fas fa-list"></i> Back to Bookings
                </a>
                <a href="view.php?id=<?= $cancellation['booking_id'] ?>" class="btn-secondary-custom">
                    <i class="fas fa-eye"></i> View Booking
                </a>
                <a href="<?= BASE_URL ?>modules/reports/download.php?action=cancellation_receipt&id=<?= $cancellation_id ?>" 
                   class="btn-primary-custom" target="_blank">
                    <i class="fas fa-file-pdf"></i> Download Receipt
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
