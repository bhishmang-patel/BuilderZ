<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();

$db = Database::getInstance();
$challan_id = intval($_GET['id'] ?? 0);

$sql = "SELECT c.*, 
               p.name as party_name,
               p.address as vendor_address,
               p.gst_number,
               pr.project_name,
               u.full_name as created_by_name,
               au.full_name as approved_by_name
        FROM challans c
        LEFT JOIN parties p ON c.party_id = p.id
        JOIN projects pr ON c.project_id = pr.id
        LEFT JOIN users u ON c.created_by = u.id
        LEFT JOIN users au ON c.approved_by = au.id
        WHERE c.id = ?";

$challan = $db->query($sql, [$challan_id])->fetch();

if ($challan && empty($challan['party_name']) && !empty($challan['temp_vendor_data'])) {
    $tv = json_decode($challan['temp_vendor_data'], true);
    $challan['party_name']     = ($tv['name'] ?? '?') . ' (Draft)';
    $challan['vendor_address'] = $tv['address'] ?? '';
    $challan['gst_number']     = $tv['gst_number'] ?? '';
}

if (!$challan) {
    echo '<p style="text-align:center;padding:2rem;color:#9e9690">Record not found.</p>';
    exit;
}

$items = $db->query(
    "SELECT ci.*, m.material_name, m.unit
     FROM challan_items ci
     JOIN materials m ON ci.material_id = m.id
     WHERE ci.challan_id = ?",
    [$challan_id]
)->fetchAll();
?>

<style>
/* ── scoped to this modal fragment ─────────────── */
.cd-wrap {
    font-family: 'DM Sans', 'Segoe UI', sans-serif;
    color: #1a1714;
}

/* ── Top band ────────────────────────────────── */
.cd-band {
    padding: 1.4rem 1.6rem 1.2rem;
    background: #fdfcfa;
    border-bottom: 1.5px solid #f0ece5;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.cd-num {
    font-family: 'Fraunces', Georgia, serif;
    font-size: 1.45rem;
    font-weight: 700;
    color: #1a1714;
    line-height: 1.1;
    margin-bottom: 0.3rem;
}

.cd-meta {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
    font-size: 0.78rem;
    color: #6b6560;
}

.cd-meta .dot { color: #e8e3db; }

/* Status pill */
.cd-pill {
    display: inline-flex; align-items: center; gap: 0.3rem;
    padding: 0.28rem 0.75rem; border-radius: 20px;
    font-size: 0.7rem; font-weight: 700; letter-spacing: 0.06em;
    text-transform: uppercase; white-space: nowrap;
}
.cd-pill::before { content:''; width:5px; height:5px; border-radius:50%; flex-shrink:0; }
.pill-pending  { background:#fef3ea; color:#a04d1e; }
.pill-pending::before  { background:#b5622a; }
.pill-approved { background:#ecfdf5; color:#065f46; }
.pill-approved::before { background:#10b981; }
.pill-paid     { background:#eff6ff; color:#1e40af; }
.pill-paid::before     { background:#3b82f6; }
.pill-partial  { background:#fefce8; color:#854d0e; }
.pill-partial::before  { background:#eab308; }

/* ── Info grid ───────────────────────────────── */
.cd-info-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1px;
    background: #e8e3db;
    border-top: 1px solid #e8e3db;
    border-bottom: 1.5px solid #e8e3db;
}

.cd-info-cell {
    background: #ffffff;
    padding: 0.9rem 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.cd-info-cell .ci-label {
    font-size: 0.67rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #9e9690;
}

.cd-info-cell .ci-val {
    font-size: 0.875rem;
    font-weight: 600;
    color: #1a1714;
    word-break: break-word;
}

.cd-info-cell .ci-val.muted {
    color: #9e9690;
    font-weight: 400;
}

/* ── Body ────────────────────────────────────── */
.cd-body { padding: 1.25rem 1.6rem; }

/* Section header */
.cd-sec-head {
    display: flex; align-items: center; gap: 0.5rem;
    margin-bottom: 0.85rem;
}
.cd-sec-head .sec-icon {
    width: 22px; height: 22px; border-radius: 5px;
    background: #fef3ea; color: #b5622a;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.62rem; flex-shrink: 0;
}
.cd-sec-head span {
    font-size: 0.67rem; font-weight: 700; letter-spacing: 0.1em;
    text-transform: uppercase; color: #6b6560;
}

/* ── Items table ─────────────────────────────── */
.cd-table-wrap {
    border: 1.5px solid #e8e3db;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 1.25rem;
}

.cd-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}

.cd-table thead tr {
    background: #f5f1eb;
    border-bottom: 1.5px solid #e8e3db;
}

.cd-table thead th {
    padding: 0.65rem 1rem;
    text-align: left;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #6b6560;
    white-space: nowrap;
}
.cd-table thead th.th-c { text-align: center; }

.cd-table tbody tr { border-bottom: 1px solid #f0ece5; }
.cd-table tbody tr:last-child { border-bottom: none; }
.cd-table tbody tr:hover { background: #fdfcfa; }

.cd-table td { padding: 0.75rem 1rem; vertical-align: middle; }
.cd-table td.td-c { text-align: center; }

.mat-name { font-weight: 600; color: #1a1714; }
.mat-unit {
    display: inline-block;
    font-size: 0.65rem; font-weight: 700; letter-spacing: 0.06em;
    text-transform: uppercase; color: #9e9690;
    background: #f5f3ef; border: 1px solid #e8e3db;
    padding: 0.12rem 0.4rem; border-radius: 4px; margin-left: 0.3rem;
}
.mat-qty {
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    color: #1a1714;
}

/* ── Footer audit strip ──────────────────────── */
.cd-footer {
    display: flex;
    align-items: flex-start;
    gap: 1.5rem;
    padding: 0.9rem 1.25rem;
    background: #fdfcfa;
    border-top: 1.5px solid #f0ece5;
    border-radius: 0 0 14px 14px;
    flex-wrap: wrap;
}

.cd-audit-item { display: flex; flex-direction: column; gap: 0.15rem; }
.ca-label { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.09em; text-transform: uppercase; color: #9e9690; }
.ca-val   { font-size: 0.8rem;  font-weight: 600; color: #1a1714; }
.ca-val.approved { color: #059669; }

/* ── Empty items ─────────────────────────────── */
.cd-empty {
    padding: 2rem 1rem; text-align: center;
    color: #9e9690; font-size: 0.82rem;
}
.cd-empty i { display: block; font-size: 1.6rem; opacity: 0.3; margin-bottom: 0.5rem; }
</style>

<?php
$s = $challan['status'];
$pillClass = match($s) {
    'approved' => 'pill-approved',
    'paid'     => 'pill-paid',
    'partial'  => 'pill-partial',
    default    => 'pill-pending'
};
$isLabour = $challan['challan_type'] === 'labour';
?>

<div class="cd-wrap">

    <!-- ── Top band ────────────────────────── -->
    <div class="cd-band">
        <div>
            <div class="cd-num"><?= htmlspecialchars($challan['challan_no']) ?></div>
            <div class="cd-meta">
                <span><?= $isLabour ? 'Labour Challan' : 'Material Challan' ?></span>
                <span class="dot">•</span>
                <span><i class="fas fa-calendar-alt" style="margin-right:3px"></i><?= formatDate($challan['challan_date']) ?></span>
                <span class="dot">•</span>
                <?= renderProjectBadge($challan['project_name'], $challan['project_id']) ?>
            </div>
        </div>
        <span class="cd-pill <?= $pillClass ?>"><?= ucfirst($s) ?></span>
    </div>

    <!-- ── Info grid ───────────────────────── -->
    <div class="cd-info-grid">
        <div class="cd-info-cell">
            <span class="ci-label"><?= $isLabour ? 'Contractor' : 'Vendor' ?></span>
            <span class="ci-val"><?= htmlspecialchars($challan['party_name'] ?? '—') ?></span>
        </div>
        <div class="cd-info-cell">
            <span class="ci-label">Project</span>
            <span class="ci-val"><?= renderProjectBadge($challan['project_name'], $challan['project_id']) ?></span>
        </div>
        <div class="cd-info-cell">
            <span class="ci-label"><?= $isLabour ? 'Work Order' : 'Vehicle No' ?></span>
            <span class="ci-val <?= empty($challan['vehicle_no']) && empty($challan['work_order_id']) ? 'muted' : '' ?>">
                <?php 
                if ($isLabour && !empty($challan['work_order_id'])) {
                    $wo = $db->select('work_orders', 'id=?', [$challan['work_order_id']])->fetch();
                    echo htmlspecialchars($wo['work_order_no'] ?? '—');
                } else {
                    echo htmlspecialchars($challan['vehicle_no'] ?: '—');
                }
                ?>
            </span>
        </div>
        <div class="cd-info-cell">
            <span class="ci-label">GST Number</span>
            <span class="ci-val <?= empty($challan['gst_number']) ? 'muted' : '' ?>">
                <?= htmlspecialchars($challan['gst_number'] ?: '—') ?>
            </span>
        </div>
        <div class="cd-info-cell" style="grid-column: span 2">
            <span class="ci-label">Address</span>
            <span class="ci-val <?= empty($challan['vendor_address']) ? 'muted' : '' ?>">
                <?= htmlspecialchars($challan['vendor_address'] ?: '—') ?>
            </span>
        </div>
    </div>
    
    <?php if ($isLabour): ?>
    <div class="cd-info-grid" style="border-top:none; background:#f8fafc;">
        <div class="cd-info-cell" style="background:#f8fafc;">
            <span class="ci-label">Bill Amount</span>
            <span class="ci-val"><?= formatCurrency($challan['bill_amount'] > 0 ? $challan['bill_amount'] : $challan['total_amount']) ?></span>
        </div>
        <div class="cd-info-cell" style="background:#f8fafc;">
            <span class="ci-label">GST (<?= $challan['is_rcm'] ? 'RCM' : 'Normal' ?>)</span>
            <span class="ci-val"><?= formatCurrency($challan['gst_amount']) ?></span>
            <?php if($challan['is_rcm']): ?><span style="font-size:0.65rem; color:#ef4444; font-weight:700;">(Builder Pays)</span><?php endif; ?>
        </div>
        <div class="cd-info-cell" style="background:#f8fafc;">
            <span class="ci-label">TDS</span>
            <span class="ci-val" style="color:#ef4444;">- <?= formatCurrency($challan['tds_amount']) ?></span>
        </div>
        <div class="cd-info-cell" style="background:#fff; border-left:1px solid #e2e8f0; grid-column: span 3;">
            <span class="ci-label" style="color:#0f172a;">Net Payable Amount</span>
            <span class="ci-val" style="font-size:1.2rem; color:#0f172a;"><?= formatCurrency($challan['final_payable_amount'] > 0 ? $challan['final_payable_amount'] : $challan['total_amount']) ?></span>
        </div>
    </div>
    <div class="cd-body">
        <div class="cd-sec-head">
            <div class="sec-icon"><i class="fas fa-align-left"></i></div>
            <span>Work Description</span>
        </div>
        <p style="font-size:0.9rem; color:#334155; white-space: pre-wrap; margin-bottom:1rem;"><?= htmlspecialchars($challan['work_description'] ?? '') ?></p>
        <div style="display:flex; gap:20px; font-size:0.85rem; color:#64748b;">
            <div><strong>From:</strong> <?= formatDate($challan['work_from_date']) ?></div>
            <div><strong>To:</strong> <?= formatDate($challan['work_to_date']) ?></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Body: items ─────────────────────── -->
    <div class="cd-body">
        <?php if (!$isLabour && !empty($items)): ?>
            <div class="cd-sec-head">
                <div class="sec-icon"><i class="fas fa-boxes"></i></div>
                <span>Material Items (<?= count($items) ?>)</span>
            </div>
            <div class="cd-table-wrap">
                <table class="cd-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Material</th>
                            <th class="th-c">Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $i => $item): ?>
                        <tr>
                            <td style="color:#9e9690;font-size:.72rem;width:32px"><?= $i + 1 ?></td>
                            <td>
                                <span class="mat-name"><?= htmlspecialchars($item['material_name']) ?></span>
                            </td>
                            <td class="td-c">
                                <span class="mat-qty"><?= number_format($item['quantity'], 2) ?></span>
                                <span class="mat-unit"><?= strtoupper($item['unit']) ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif (!$isLabour): ?>
            <div class="cd-empty">
                <i class="fas fa-cubes"></i>
                No material items recorded for this challan.
            </div>
        <?php endif; ?>
    </div>

    <!-- ── Audit footer ─────────────────────── -->
    <div class="cd-footer">
        <div class="cd-audit-item">
            <span class="ca-label">Created By</span>
            <span class="ca-val"><?= htmlspecialchars($challan['created_by_name'] ?? '—') ?></span>
        </div>
        <?php if ($challan['approved_by']): ?>
        <div class="cd-audit-item">
            <span class="ca-label">Approved By</span>
            <span class="ca-val approved">
                <i class="fas fa-check-circle" style="margin-right:3px;font-size:.7rem"></i>
                <?= htmlspecialchars($challan['approved_by_name']) ?>
                <span style="font-weight:400;color:#6b6560;margin-left:4px;font-size:0.75rem">
                    on <?= formatDate($challan['approved_at'], DATETIME_FORMAT) ?>
                </span>
            </span>
        </div>
        <?php endif; ?>
    </div>

</div>