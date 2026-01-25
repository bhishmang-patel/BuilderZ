<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ColorHelper.php';

requireAuth();

if (!isset($_GET['id'])) {
    echo "Invalid request";
    exit;
}

$flat_id = intval($_GET['id']);
$db = Database::getInstance();

// 1. Fetch Flat Details
$flat = $db->query("
    SELECT f.*, p.project_name, p.location 
    FROM flats f 
    JOIN projects p ON f.project_id = p.id 
    WHERE f.id = ?
", [$flat_id])->fetch();

if (!$flat) {
    echo "Flat not found";
    exit;
}

// 2. Fetch Booking Details (if booked/sold)
$booking = null;
if ($flat['status'] === 'booked' || $flat['status'] === 'sold') {
    $booking = $db->query("
        SELECT b.*, c.name as customer_name, c.mobile, c.email
        FROM bookings b
        JOIN parties c ON b.customer_id = c.id
        WHERE b.flat_id = ? AND b.status = 'active'
        ORDER BY b.id DESC LIMIT 1
    ", [$flat_id])->fetch();
}

$statusColor = 'gray';
if($flat['status'] === 'available') $statusColor = 'green';
if($flat['status'] === 'booked') $statusColor = 'orange';
if($flat['status'] === 'sold') $statusColor = 'blue';

$projectColor = ColorHelper::getProjectColor($flat['project_id']);
?>

<div style="display: flex; gap: 20px;">
    <!-- Left Column: Flat Info -->
    <div style="flex: 1;">
        <div class="info-card" style="height: 100%;">
            <div class="card-header" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 1px solid #e2e8f0; padding: 15px;">
                <h4 style="margin: 0; color: #475569; font-size: 14px; text-transform: uppercase; font-weight: 700;">Property Details</h4>
            </div>
            <div class="card-body" style="padding: 20px;">
                
                <div style="display: flex; align-items: center; margin-bottom: 20px;">
                    <div style="width: 50px; height: 50px; background: <?= $projectColor ?>; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 700; color: white;">
                        <?= substr($flat['project_name'], 0, 1) ?>
                    </div>
                    <div style="margin-left: 15px;">
                        <div style="font-size: 16px; font-weight: 700; color: #1e293b;"><?= htmlspecialchars($flat['project_name']) ?></div>
                        <div style="font-size: 13px; color: #64748b; margin-top: 2px;"><?= htmlspecialchars($flat['location']) ?></div>
                    </div>
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Flat No</span>
                        <span class="info-value" style="font-size: 18px; color: #3b82f6;"><?= htmlspecialchars($flat['flat_no']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Floor</span>
                        <span class="info-value"><?= htmlspecialchars($flat['floor']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Type</span>
                        <span class="badge-pill purple"><?= htmlspecialchars($flat['bhk'] ?? '—') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Status</span>
                        <span class="badge-pill <?= $statusColor ?>"><?= ucfirst($flat['status']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Area</span>
                        <span class="info-value"><?= $flat['area_sqft'] ?> sqft</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Base Rate</span>
                        <span class="info-value"><?= formatCurrency($flat['rate_per_sqft']) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Booking Info or Empty State -->
    <div style="flex: 1;">
        <?php if ($booking): ?>
            <div class="info-card" style="height: 100%; border-color: #e2e8f0;">
                <div class="card-header" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-bottom: 1px solid #bfdbfe; padding: 15px;">
                    <h4 style="margin: 0; color: #1e40af; font-size: 14px; text-transform: uppercase; font-weight: 700;">Booking Details</h4>
                </div>
                <div class="card-body" style="padding: 20px;">
                    
                    <div style="text-align: center; margin-bottom: 20px;">
                        <div style="width: 60px; height: 60px; background: #e0f2fe; border-radius: 50%; color: #0284c7; display: flex; align-items: center; justify-content: center; font-size: 24px; margin: 0 auto;">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <h3 style="margin: 10px 0 5px; font-size: 16px; color: #0f172a;"><?= htmlspecialchars($booking['customer_name']) ?></h3>
                        <div style="font-size: 13px; color: #64748b;"><?= htmlspecialchars($booking['mobile']) ?></div>
                    </div>

                    <div class="info-grid one-col">
                        <div class="info-item">
                            <span class="info-label">Booking Date</span>
                            <span class="info-value"><?= date('d M Y', strtotime($booking['booking_date'])) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Agreement Value</span>
                            <span class="info-value" style="font-weight: 700; color: #059669;"><?= formatCurrency($booking['agreement_value']) ?></span>
                        </div>
                        <?php if ($booking['rate'] > 0): ?>
                        <div class="info-item">
                            <span class="info-label">Booked Rate</span>
                            <span class="info-value"><?= formatCurrency($booking['rate']) ?> / sqft</span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div style="margin-top: 20px;">
                        <a href="<?= BASE_URL ?>modules/booking/view.php?id=<?= $booking['id'] ?>" class="modern-btn" style="width: 100%; text-align: center; background: #3b82f6;">
                            View Full Booking <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
             <div class="info-card" style="height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 40px; background: #f8fafc; border: 2px dashed #cbd5e1;">
                <div style="font-size: 40px; color: #cbd5e1; margin-bottom: 15px;"><i class="fas fa-key"></i></div>
                <h4 style="color: #64748b; margin: 0 0 10px;">Not Booked Yet</h4>
                <p style="color: #94a3b8; font-size: 13px; margin: 0 0 20px;">This flat is currently available.</p>
                <div style="display: flex; gap: 10px;">
                    <a href="<?= BASE_URL ?>modules/booking/create.php?flat_id=<?= $flat['id'] ?>&project_id=<?= $flat['project_id'] ?>" class="modern-btn" style="background: #10b981;">
                        <i class="fas fa-plus"></i> Book Now
                    </a>
                </div>
             </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Scoped styles for this snippet */
.info-card { background: white; border: 1px solid #f1f5f9; border-radius: 12px; overflow: hidden; height: 100%; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
.info-grid.one-col { grid-template-columns: 1fr; }
.info-item { display: flex; flex-direction: column; gap: 4px; }
.info-label { font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; letter-spacing: 0.5px; }
.info-value { font-size: 14px; font-weight: 600; color: #334155; }
.badge-pill.purple { background: #f3e8ff; color: #9333ea; padding: 4px 10px; font-size: 11px; border-radius: 999px; font-weight: 600; display: inline-block; width: fit-content; }
.badge-pill.green { background: #dcfce7; color: #16a34a; padding: 4px 10px; font-size: 11px; border-radius: 999px; font-weight: 600; display: inline-block; width: fit-content; }
.badge-pill.orange { background: #ffedd5; color: #ea580c; padding: 4px 10px; font-size: 11px; border-radius: 999px; font-weight: 600; display: inline-block; width: fit-content; }
.badge-pill.blue { background: #dbeafe; color: #2563eb; padding: 4px 10px; font-size: 11px; border-radius: 999px; font-weight: 600; display: inline-block; width: fit-content; }
.modern-btn { 
    display: inline-flex; 
    align-items: center; 
    justify-content: center; 
    padding: 10px 20px; 
    color: white; 
    border-radius: 8px; 
    text-decoration: none; 
    font-size: 13px; 
    font-weight: 600; 
    transition: transform 0.1s; 
    border: none;
    cursor: pointer;
}
.modern-btn:hover { transform: translateY(-1px); opacity: 0.9; }
</style>
