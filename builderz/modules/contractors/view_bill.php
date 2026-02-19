<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/MasterService.php';
require_once __DIR__ . '/../../includes/ColorHelper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'project_manager', 'accountant']);

$db = Database::getInstance();
$masterService = new MasterService();

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: index.php'); exit;
}

// Fetch Bill Details
$sql = "SELECT c.*, 
       p.name as contractor_name, p.mobile as contractor_mobile, p.email as contractor_email, p.address as contractor_address, p.gst_number as contractor_gst, p.pan_number as contractor_pan, p.contractor_type,
               pr.project_name,
               wo.work_order_no, wo.title as work_order_title,
               u.full_name as created_by_name,
               au.full_name as approved_by_name
        FROM contractor_bills c
        JOIN parties p ON c.contractor_id = p.id
        JOIN projects pr ON c.project_id = pr.id
        LEFT JOIN work_orders wo ON c.work_order_id = wo.id
        LEFT JOIN users u ON c.created_by = u.id
        LEFT JOIN users au ON c.approved_by = au.id
        WHERE c.id = ?";

$stmt = $db->query($sql, [$id]);
$bill = $stmt->fetch();

if (!$bill) {
    die("Bill not found");
}

$page_title = 'Bill Details: ' . $bill['bill_no'];
$current_page = 'contractor_pay';

// Handle Actions (Approve/Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
         setFlashMessage('error', 'Security token expired.');
         header("Location: view_bill.php?id=$id"); exit;
    }

    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'approve_challan' && $_SESSION['user_role'] === 'admin') {
            $update_data = [
                'status' => 'approved',
                'approved_by' => $_SESSION['user_id'],
                'approved_at' => date('Y-m-d H:i:s')
            ];
            $db->update('contractor_bills', $update_data, 'id = ?', ['id' => $id]);
            logAudit('approve', 'contractor_bills', $id);
            setFlashMessage('success', 'Bill approved successfully');
        } elseif ($action === 'reject_challan' && $_SESSION['user_role'] === 'admin') {
            $update_data = ['status' => 'rejected'];
            $db->update('contractor_bills', $update_data, 'id = ?', ['id' => $id]);
            logAudit('reject', 'contractor_bills', $id);
            setFlashMessage('success', 'Bill rejected successfully');
        }
    } catch (Throwable $e) {
        $db->rollback();
        error_log("Bill Action Error: " . $e->getMessage());
        setFlashMessage('error', 'Action failed: ' . $e->getMessage());
    }
    
    header("Location: view_bill.php?id=$id"); exit;
}

include __DIR__ . '/../../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
    *, *::before, *::after { box-sizing: border-box; }

    :root {
        --ink:       #1a1714;
        --ink-soft:  #6b6560;
        --ink-mute:  #9e9690;
        --cream:     #f5f3ef;
        --surface:   #ffffff;
        --border:    #e8e3db;
        --border-lt: #f0ece5;
        --accent:    #b5622a;
        --accent-bg: #fdf8f3;
        --accent-lt: #fef3ea;
    }

    body {
        background: var(--cream);
        font-family: 'DM Sans', sans-serif;
        color: var(--ink);
    }

    /* ── Layout ──────────────────────────────── */
    .view-wrap {
        max-width: 1060px;
        margin: 2.5rem auto;
        padding: 0 1.5rem 4rem;
    }

    /* ── Page Header ─────────────────────────── */
    .view-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1.5rem;
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 1.5px solid var(--border);
        flex-wrap: wrap;
    }

    .header-meta {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
    }

    .back-crumb {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--ink-mute);
        text-decoration: none;
        letter-spacing: 0.04em;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        transition: color 0.15s ease;
    }
    .back-crumb:hover { color: var(--accent); }

    .crumb-sep { color: var(--border); font-size: 0.75rem; }
    .crumb-current { font-size: 0.75rem; font-weight: 600; color: var(--ink-soft); }

    .view-header h1 {
        font-family: 'Fraunces', serif;
        font-size: 1.85rem;
        font-weight: 700;
        color: var(--ink);
        margin: 0 0 0.4rem;
        line-height: 1.1;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .meta-line {
        font-size: 0.8rem;
        color: var(--ink-mute);
        display: flex;
        align-items: center;
        gap: 0.6rem;
        flex-wrap: wrap;
    }
    .meta-line .dot { color: var(--border); }

    /* ── Status Pills ────────────────────────── */
    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        white-space: nowrap;
        vertical-align: middle;
    }
    .status-pill::before {
        content: '';
        width: 5px; height: 5px;
        border-radius: 50%;
        flex-shrink: 0;
    }
    .pill-pending   { background: var(--accent-lt); color: #a04d1e; }
    .pill-pending::before { background: var(--accent); }
    .pill-approved  { background: #ecfdf5; color: #065f46; }
    .pill-approved::before { background: #10b981; }
    .pill-paid      { background: #ecfdf5; color: #065f46; }
    .pill-paid::before { background: #10b981; }
    .pill-rejected  { background: #fef2f2; color: #991b1b; }
    .pill-rejected::before { background: #ef4444; }
    .pill-partial   { background: #fff7ed; color: #ea580c; }
    .pill-partial::before { background: #f97316; }

    /* ── Action Buttons ──────────────────────── */
    .header-actions {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        flex-wrap: wrap;
        flex-shrink: 0;
    }

    .act {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.6rem 1.2rem;
        border-radius: 8px;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        border: 1.5px solid transparent;
        transition: all 0.18s ease;
        white-space: nowrap;
    }
    .act:hover { transform: translateY(-1px); }
    .act:active { transform: translateY(0); }

    .act-back {
        background: var(--surface);
        border-color: var(--border);
        color: var(--ink-soft);
    }
    .act-back:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }

    .act-print {
        background: var(--surface);
        border-color: var(--border);
        color: var(--ink-soft);
    }
    .act-print:hover { border-color: #4f63d2; color: #4f63d2; background: #eef2ff; }

    .act-approve {
        background: #ecfdf5;
        border-color: #6ee7b7;
        color: #065f46;
    }
    .act-approve:hover {
        background: #10b981;
        border-color: #10b981;
        color: white;
        box-shadow: 0 4px 12px rgba(16,185,129,0.25);
    }

    .act-reject {
        background: #fef2f2;
        border-color: #fca5a5;
        color: #991b1b;
    }
    .act-reject:hover {
        background: #ef4444;
        border-color: #ef4444;
        color: white;
        box-shadow: 0 4px 12px rgba(239,68,68,0.25);
    }

    /* ── Summary Strip ───────────────────────── */
    .summary-strip {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1px;
        background: var(--border);
        border: 1.5px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 1.5rem;
    }
    @media (max-width: 700px) { .summary-strip { grid-template-columns: repeat(2, 1fr); } }

    .strip-cell {
        background: var(--surface);
        padding: 1.1rem 1.4rem;
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .strip-label {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--ink-mute);
    }

    .strip-value {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--ink);
    }

    .strip-value.accent {
        font-family: 'Fraunces', serif;
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--accent);
    }

    /* ── Two-col info grid ───────────────────── */
    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.25rem;
        margin-bottom: 1.25rem;
    }
    @media (max-width: 680px) { .info-grid { grid-template-columns: 1fr; } }

    /* ── Cards ───────────────────────────────── */
    .detail-card {
        background: var(--surface);
        border: 1.5px solid var(--border);
        border-radius: 14px;
        overflow: hidden;
        margin-bottom: 1.25rem;
    }

    .card-head {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        padding: 1rem 1.5rem;
        border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }

    .card-icon {
        width: 28px; height: 28px; border-radius: 7px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.72rem; flex-shrink: 0;
    }
    .icon-contractor { background: #eef2ff; color: #4f63d2; }
    .icon-project    { background: #ecfdf5; color: #059669; }
    .icon-bill       { background: var(--accent-lt); color: var(--accent); }
    .icon-work       { background: #fefce8; color: #a16207; }

    .card-head h2 {
        font-family: 'Fraunces', serif;
        font-size: 0.95rem; font-weight: 600; color: var(--ink); margin: 0;
    }

    .card-body { padding: 1.4rem 1.5rem; }

    /* ── Detail rows ─────────────────────────── */
    .detail-list { display: flex; flex-direction: column; gap: 0; }

    .detail-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        padding: 0.7rem 0;
        border-bottom: 1px solid var(--border-lt);
    }
    .detail-row:last-child { border-bottom: none; padding-bottom: 0; }
    .detail-row:first-child { padding-top: 0; }

    .dl { font-size: 0.78rem; font-weight: 600; color: var(--ink-soft); letter-spacing: 0.02em; flex-shrink: 0; }
    .dv { font-size: 0.875rem; font-weight: 600; color: var(--ink); text-align: right; word-break: break-word; }
    .dv.muted { color: var(--ink-mute); font-weight: 400; }

    /* ── Calculations Table ──────────────────── */
    .calc-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 0.6rem 0;
        color: var(--ink-soft);
        font-size: 0.85rem;
    }
    .calc-row strong { font-weight: 600; color: var(--ink); }
    .calc-row.final {
        border-top: 1.5px solid var(--border);
        margin-top: 0.5rem; padding-top: 0.8rem;
        font-family: 'Fraunces', serif;
        color: var(--ink); font-size: 1.25rem; font-weight: 700;
    }
    .calc-val { text-align: right; font-variant-numeric: tabular-nums; }
    
    .green { color: #059669; }
    .red { color: #dc2626; }
    .accent { color: var(--accent); }
    
    .status-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 24px;
        padding: 0 14px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 0.7rem;
        letter-spacing: 0.03em;
        text-transform: uppercase;
    }
    
    .rcm-pill {
        background: #fff7ed;
        color: #c2410c;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 24px;
        padding: 0 14px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 0.7rem;
        letter-spacing: 0.03em;
        text-transform: uppercase;
    }
    /* ── Modal (Reused) ──────────────────────── */
    .cont-modal-backdrop {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(8px);
        z-index: 9999; display: flex; align-items: center; justify-content: center;
        opacity: 0; visibility: hidden; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .cont-modal-backdrop.open { opacity: 1; visibility: visible; }
    
    .cont-modal {
        background: white; width: 90%; max-width: 400px;
        border-radius: 16px; padding: 2rem;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        transform: scale(0.9) translateY(20px); transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        text-align: center;
    }
    .cont-modal-backdrop.open .cont-modal { transform: scale(1) translateY(0); }
    
    .modal-icon {
        width: 64px; height: 64px; background: #fee2e2; color: #dc2626;
        border-radius: 50%; display: flex; align-items: center; justify-content: center;
        font-size: 28px; margin: 0 auto 1.5rem auto;
    }
    
    .modal-title { margin: 0 0 0.5rem 0; font-size: 1.25rem; font-weight: 700; color: var(--ink); }
    .modal-desc { margin: 0 0 1.5rem 0; color: var(--ink-soft); font-size: 0.95rem; line-height: 1.5; }
    
    .modal-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1.5rem; }
    
    .cont-btn {
        padding: 0.75rem 1rem; border-radius: 10px; font-weight: 600; 
        cursor: pointer; border: none; font-family: inherit; font-size: 0.95rem;
        transition: all 0.2s ease;
    }
    .cont-btn:active { transform: scale(0.98); }
    
    .cont-btn.primary { background: #dc2626; color: white; box-shadow: 0 4px 6px -1px rgba(220, 38, 38, 0.3); }
    .cont-btn.primary:hover { background: #b91c1c; }
    
    .cont-btn.secondary { background: white; color: var(--ink); border: 1px solid var(--border); }
    .cont-btn.secondary:hover { background: #f8fafc; border-color: #cbd5e1; }

</style>

<div class="view-wrap">

    <!-- ── Page Header ──────────────────────── -->
    <div class="view-header">
        <div>
            <div class="header-meta">
                <a href="index.php" class="back-crumb"><i class="fas fa-arrow-left"></i> Contractors</a>
                <span class="crumb-sep">/</span>
                <span class="crumb-current">Bill Details</span>
            </div>
            <h1>
                <?= htmlspecialchars($bill['bill_no']) ?>
                <?php
                    $s = $bill['status'];
                    $pillClass = match($s) {
                        'pending'   => 'pill-pending',
                        'approved'  => 'pill-approved',
                        'paid'      => 'pill-paid',
                        'partial'   => 'pill-partial',
                        'rejected'  => 'pill-rejected',
                        default     => 'pill-pending'
                    };
                ?>
                <span class="status-pill <?= $pillClass ?>" style="margin-left:10px;"><?= ucfirst($s) ?></span>
                <?php if ($bill['is_rcm']): ?>
                    <span class="rcm-pill">RCM</span>
                <?php endif; ?>
            </h1>
            <div class="meta-line">
                <span><i class="fas fa-calendar-alt"></i> Bill Date: <?= formatDate($bill['bill_date']) ?></span>
                <span class="dot">•</span>
                <span><i class="fas fa-user-edit"></i> Created By: <?= htmlspecialchars($bill['created_by_name'] ?? 'System') ?></span>
                <?php if ($bill['approved_at']): ?>
                <span class="dot">•</span>
                <span><i class="fas fa-check-double"></i> Approved By: <?= htmlspecialchars($bill['approved_by_name']) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="header-actions">
            <?php if ($bill['status'] === 'pending' && $_SESSION['user_role'] === 'admin'): ?>
                <form method="POST" action="view_bill.php?id=<?= $id ?>" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="approve_challan">
                    <button type="submit" class="act act-approve">
                        <i class="fas fa-check"></i> Approve
                    </button>
                </form>
                <button type="button" onclick="openRejectModal()" class="act act-reject">
                    <i class="fas fa-times"></i> Reject
                </button>
            <?php endif; ?>

            <a href="print_bill.php?id=<?= $id ?>" target="_blank" class="act act-print">
                <i class="fas fa-print"></i> Print
            </a>

            <a href="index.php" class="act act-back">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- ── Summary Strip ────────────────────── -->
    <div class="summary-strip">
        <div class="strip-cell">
            <div class="strip-label">Project</div>
            <div class="strip-value"><?= renderProjectBadge($bill['project_name'], $bill['project_id']) ?></div>
        </div>
        <div class="strip-cell">
            <div class="strip-label">Contractor</div>
            <div class="strip-value"><?= htmlspecialchars($bill['contractor_name']) ?></div>
        </div>
        <div class="strip-cell">
            <div class="strip-label">Work Order</div>
            <div class="strip-value"><?= htmlspecialchars($bill['work_order_no'] ?? '—') ?></div>
        </div>
        <div class="strip-cell">
            <div class="strip-label">Payable Amount</div>
            <div class="strip-value accent"><?= formatCurrency($bill['total_payable']) ?></div>
        </div>
    </div>

    <!-- ── Info Cards (2-col) ────────────────── -->
    <div class="info-grid">

        <!-- Contractor Details -->
        <div class="detail-card">
            <div class="card-head">
                <div class="card-icon icon-contractor"><i class="fas fa-hard-hat"></i></div>
                <h2>Contractor Details</h2>
            </div>
            <div class="card-body">
                <div class="detail-list">
                    <div class="detail-row">
                        <span class="dl">Name</span>
                        <span class="dv"><?= htmlspecialchars($bill['contractor_name']) ?> <small style="color:var(--ink-mute)">(<?= htmlspecialchars($bill['contractor_type'] ?? 'General') ?>)</small></span>
                    </div>
                    <?php if (!empty($bill['contractor_mobile'])): ?>
                    <div class="detail-row">
                        <span class="dl">Mobile</span>
                        <span class="dv"><?= htmlspecialchars($bill['contractor_mobile']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($bill['contractor_email'])): ?>
                    <div class="detail-row">
                        <span class="dl">Email</span>
                        <span class="dv"><?= htmlspecialchars($bill['contractor_email']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($bill['contractor_gst'])): ?>
                    <div class="detail-row">
                        <span class="dl">GST Number</span>
                        <span class="dv" style="font-family:monospace;"><?= htmlspecialchars($bill['contractor_gst']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($bill['contractor_pan'])): ?>
                    <div class="detail-row">
                        <span class="dl">PAN</span>
                        <span class="dv"><?= htmlspecialchars($bill['contractor_pan']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($bill['contractor_address'])): ?>
                    <div class="detail-row">
                        <span class="dl">Address</span>
                        <span class="dv" style="white-space:pre-wrap;"><?= htmlspecialchars($bill['contractor_address']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Bill Calculation -->
        <div class="detail-card">
            <div class="card-head">
                <div class="card-icon icon-bill"><i class="fas fa-calculator"></i></div>
                <h2>Bill Calculation</h2>
            </div>
            <div class="card-body">
                <div class="calc-row">
                    <span>Basic Amount</span>
                    <span class="calc-val"><?= formatCurrency($bill['basic_amount']) ?></span>
                </div>
                <?php if ($bill['gst_amount'] > 0): ?>
                <div class="calc-row">
                    <span>GST Added</span>
                    <span class="calc-val green">+ <?= formatCurrency($bill['gst_amount']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($bill['tds_amount'] > 0): ?>
                <div class="calc-row">
                    <span>TDS Deducted</span>
                    <span class="calc-val red">− <?= formatCurrency($bill['tds_amount']) ?></span>
                </div>
                <?php endif; ?>
                <div class="calc-row final">
                    <span>Net Payable</span>
                    <span class="calc-val accent"><?= formatCurrency($bill['total_payable']) ?></span>
                </div>
                <div style="font-size:0.75rem; color:var(--ink-mute); text-align:right; margin-top:5px;">
                    <?= convertNumberToWords($bill['total_payable']) ?> Only
                </div>
            </div>
        </div>
    </div>

    <!-- ── Work Description ──────────────────── -->
    <div class="detail-card">
        <div class="card-head">
            <div class="card-icon icon-work"><i class="fas fa-tasks"></i></div>
            <h2>Work Description</h2>
        </div>
        <div class="card-body">
            <div class="detail-list">
                <div class="detail-row">
                    <span class="dl">Work Period</span>
                    <span class="dv"><?= formatDate($bill['work_from_date']) ?> to <?= formatDate($bill['work_to_date']) ?></span>
                </div>
                <div class="detail-row" style="flex-direction:column; gap:0.5rem; align-items:flex-start;">
                    <span class="dl">Description</span>
                    <p style="margin:0; font-size:0.9rem; color:var(--ink-soft); line-height:1.6; white-space:pre-line;">
                        <?= htmlspecialchars($bill['work_description']) ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Rejection Modal -->
<!-- Rejection Modal -->
<div class="cont-modal-backdrop" id="rejectModal">
    <div class="cont-modal">
        <div class="modal-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3 class="modal-title">Reject this Bill?</h3>
        <p class="modal-desc">
            Are you sure you want to reject this contractor bill? <br>
            <strong>This action cannot be undone.</strong>
        </p>
        
        <form method="POST" action="view_bill.php?id=<?= $id ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reject_challan">
            
            <div class="modal-actions">
                <button type="button" class="cont-btn secondary" onclick="closeRejectModal()">Cancel</button>
                <button type="submit" class="cont-btn primary">Yes, Reject Bill</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRejectModal() {
    document.getElementById('rejectModal').classList.add('open');
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.remove('open');
}

// Close on click outside
document.getElementById('rejectModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeRejectModal();
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
