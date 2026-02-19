<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'project_manager']);

$db = Database::getInstance();
$page_title = 'Bookings';
$current_page = 'booking';

require_once __DIR__ . '/../../includes/BookingService.php';
require_once __DIR__ . '/../../includes/ColorHelper.php';

// Handle booking creation
// (Moved to create.php)

// Fetch all bookings
$status_filter = $_GET['status'] ?? '';
$project_filter = $_GET['project'] ?? '';
$referrer_filter = $_GET['referrer'] ?? '';

$where = '1=1';
$params = [];

if ($status_filter) {
    $where .= ' AND b.status = ?';
    $params[] = $status_filter;
}

if ($project_filter) {
    $where .= ' AND b.project_id = ?';
    $params[] = $project_filter;
}

if ($referrer_filter) {
    $where .= ' AND b.referred_by = ?';
    $params[] = $referrer_filter;
}

$sql = "SELECT b.*, 
               f.flat_no, f.area_sqft,
               p.name as customer_name,
               p.mobile as customer_mobile,
               pr.project_name
        FROM bookings b
        JOIN flats f ON b.flat_id = f.id
        JOIN parties p ON b.customer_id = p.id
        JOIN projects pr ON b.project_id = pr.id
        WHERE $where
        ORDER BY b.created_at DESC";

$stmt = $db->query($sql, $params);
$bookings = $stmt->fetchAll();

// Calculate Totals
$total_bookings_count = 0;
$total_bookings_value = 0;
$total_received_value = 0;
$total_pending_value = 0;
$total_area_sold = 0;

foreach($bookings as $b) {
    if ($b['status'] !== 'cancelled') {
        $total_bookings_count++;
        $total_bookings_value += $b['agreement_value'];
        $total_received_value += $b['total_received'];
        $total_pending_value += $b['total_pending'];
        if(!empty($b['area_sqft'])) $total_area_sold += $b['area_sqft'];
    }
}

$average_rate = ($total_area_sold > 0) ? ($total_bookings_value / $total_area_sold) : 0;

// Get projects for filter
$projects = $db->query("SELECT id, project_name FROM projects ORDER BY project_name")->fetchAll();
$referrers = $db->query("SELECT DISTINCT referred_by FROM bookings WHERE referred_by IS NOT NULL AND referred_by != '' ORDER BY referred_by")->fetchAll(PDO::FETCH_COLUMN);

// Get available flats for booking - logic moved to create.php

include __DIR__ . '/../../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
    :root {
        --ink:       #1a1714;
        --ink-soft:  #6b6560;
        --ink-mute:  #9e9690;
        --cream:     #f5f3ef;
        --surface:   #ffffff;
        --border:    #e8e3db;
        --border-lt: #f0ece5;
        --accent:    #2a58b5ff;
        --accent-bg: #fdf8f3;
        --accent-lt: #fef3ea;
    }

    /* ── Page Wrapper ────────────────────────── */
    .bk-wrap { max-width: 1280px; margin: 2.5rem auto; padding: 0 1.5rem 4rem; }

    /* ── Header ──────────────────────────────── */
    .bk-header {
        margin-bottom: 2rem; padding-bottom: 1.5rem;
        border-bottom: 1.5px solid var(--border);
        display: flex; align-items: flex-end; justify-content: space-between;
        flex-wrap: wrap; gap: 1rem;
    }

    .bk-header .eyebrow {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.15em;
        text-transform: uppercase; color: var(--accent); margin-bottom: 0.3rem;
    }

    .bk-header h1 {
        font-family: 'Fraunces', serif; font-size: 2rem; font-weight: 700;
        line-height: 1.1; color: var(--ink); margin: 0;
    }

    .bk-header h1 em { color: var(--accent); font-style: italic; }

    .header-actions { display: flex; gap: 0.6rem; flex-wrap: wrap; }

    .btn-new {
        display: inline-flex; align-items: center; gap: 0.5rem;
        padding: 0.68rem 1.4rem; background: var(--ink); color: white;
        border-radius: 8px; text-decoration: none;
        font-size: 0.875rem; font-weight: 600;
        transition: background 0.18s, transform 0.15s, box-shadow 0.18s;
        border: 1.5px solid var(--ink);
    }
    .btn-new:hover { background: var(--accent); border-color: var(--accent); transform: translateY(-1px); box-shadow: 0 4px 14px rgba(181,98,42,0.28); color: white; }

    /* ── Stats Grid ──────────────────────────── */
    .stats-grid {
        display: grid; grid-template-columns: repeat(6, 1fr);
        gap: 1.1rem; margin-bottom: 1.75rem;
    }
    @media (max-width: 1100px) { .stats-grid { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 720px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 480px) { .stats-grid { grid-template-columns: 1fr; } }

    .stat-card {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 12px; padding: 1.1rem 1.3rem;
        transition: transform 0.2s, box-shadow 0.2s;
        animation: fadeUp 0.4s ease both;
    }
    .stat-card:nth-child(1) { animation-delay: .05s; }
    .stat-card:nth-child(2) { animation-delay: .1s; }
    .stat-card:nth-child(3) { animation-delay: .15s; }
    .stat-card:nth-child(4) { animation-delay: .2s; }
    .stat-card:nth-child(5) { animation-delay: .25s; }
    .stat-card:nth-child(6) { animation-delay: .3s; }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(26,23,20,0.07); }

    .s-icon {
        width: 36px; height: 36px; border-radius: 9px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.85rem; margin-bottom: 0.75rem;
    }
    .ico-blue   { background: #eff6ff; color: #3b82f6; }
    .ico-green  { background: #ecfdf5; color: #10b981; }
    .ico-purple { background: #f3e8ff; color: #9333ea; }
    .ico-orange { background: var(--accent-lt); color: var(--accent); }

    .stat-label {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.07em;
        text-transform: uppercase; color: var(--ink-soft); margin-bottom: 0.4rem;
    }

    .stat-value {
        font-family: 'Fraunces', serif; font-size: 1.4rem; font-weight: 700;
        color: var(--ink); line-height: 1; font-variant-numeric: tabular-nums;
        position: relative;
    }

    .stat-value .unit { font-size: 0.7rem; color: var(--ink-mute); font-weight: 500; }

    /* Hover reveal for large numbers */
    .stat-value .short-val, .stat-value .full-val { transition: opacity 0.2s; }
    .stat-value .full-val { display: none; }
    .stat-card:hover .stat-value .short-val { display: none; }
    .stat-card:hover .stat-value .full-val { display: inline; }

    /* ── Main Panel ──────────────────────────── */
    .bk-panel {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 14px; overflow: hidden;
        animation: fadeUp 0.45s 0.2s ease both;
    }

    /* ── Toolbar ─────────────────────────────── */
    .panel-toolbar {
        display: flex; align-items: center; gap: 1.25rem; flex-wrap: nowrap;
        padding: 1rem 1.5rem; border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }

    .toolbar-left { display: flex; align-items: center; gap: 0.65rem; flex-shrink: 0; }
    .toolbar-icon {
        width: 32px; height: 32px; background: var(--accent); border-radius: 7px;
        display: flex; align-items: center; justify-content: center;
        color: white; font-size: 0.75rem;
    }
    .toolbar-title { font-family: 'Fraunces', serif; font-size: 0.95rem; font-weight: 600; color: var(--ink); white-space: nowrap; }
    .toolbar-subtitle { font-size: 0.73rem; color: var(--ink-mute); margin-left: 0.4rem; }
    .toolbar-div { width: 1.5px; height: 28px; background: var(--border); flex-shrink: 0; }

    .toolbar-actions { display: flex; align-items: center; gap: 0.5rem; flex: 1; justify-content: flex-end; flex-wrap: wrap; }

    .btn-filter, .btn-action {
        display: inline-flex; align-items: center; gap: 0.4rem;
        padding: 0.55rem 1rem; border: 1.5px solid var(--border);
        background: white; color: var(--ink-soft);
        border-radius: 7px; font-size: 0.8rem; font-weight: 600;
        cursor: pointer; transition: all 0.18s; text-decoration: none;
    }
    .btn-filter:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }

    .btn-action.cancelled { background: #fef2f2; border-color: #fca5a5; color: #b91c1c; }
    .btn-action.cancelled:hover { background: #fee2e2; }

    .btn-action.export { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
    .btn-action.export:hover { background: #d1fae5; }

    @media (max-width: 920px) {
        .panel-toolbar { flex-wrap: wrap; }
        .toolbar-div { display: none; }
        .toolbar-actions { width: 100%; justify-content: flex-start; }
    }

    /* ── Filter Card ─────────────────────────── */
    .filter-section {
        display: none; padding: 1.25rem 1.5rem;
        border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }
    .filter-section.show { display: block; }

    .filter-form { display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap; }

    .f-select {
        height: 38px; padding: 0 2rem 0 0.75rem;
        border: 1.5px solid var(--border); border-radius: 7px;
        font-size: 0.82rem; color: var(--ink); background: white;
        outline: none; transition: border-color 0.15s; -webkit-appearance: none; appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 0.6rem center;
        flex: 0 0 140px;
    }
    .f-select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(181,98,42,0.1); }

    .btn-go, .btn-clear {
        height: 38px; padding: 0 1.25rem; border: none; border-radius: 7px;
        display: flex; align-items: center; gap: 0.4rem;
        font-size: 0.8rem; font-weight: 600; cursor: pointer;
        transition: all 0.18s; text-decoration: none;
    }
    .btn-go { background: var(--ink); color: white; }
    .btn-go:hover { background: var(--accent); }
    .btn-clear { background: #fee2e2; color: #b91c1c; }
    .btn-clear:hover { background: #fca5a5; color: white; }

    /* ── Table ───────────────────────────────── */
    .bk-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }

    .bk-table thead tr { background: #fdfcfa; border-bottom: 1.5px solid var(--border); }
    .bk-table thead th {
        padding: 0.7rem 1rem; text-align: left;
        font-size: 0.64rem; font-weight: 700; letter-spacing: 0.1em;
        text-transform: uppercase; color: var(--ink-soft); white-space: nowrap;
    }
    .bk-table thead th.th-c { text-align: center; }
    .bk-table thead th.th-r { text-align: right; }

    .bk-table tbody tr { border-bottom: 1px solid var(--border-lt); transition: background 0.13s; }
    .bk-table tbody tr:last-child { border-bottom: none; }
    .bk-table tbody tr:hover { background: #fdfcfa; }

    .bk-table td { padding: 0.8rem 1rem; vertical-align: middle; }
    .bk-table td.td-c { text-align: center; }
    .bk-table td.td-r { text-align: right; }

    /* New Cell Styles */
    .cust-flat-cell { display: flex; flex-direction: column; line-height: 1.3; }
    .cust-name { font-weight: 700; color: var(--ink); font-size: 0.9rem; }
    .cust-sub  { font-size: 0.75rem; color: var(--ink-mute); margin-top: 2px; font-weight: 500; }
    .mobile-cell { font-family: 'DM Sans', sans-serif; font-weight: 500; color: var(--ink-soft); font-size: 0.85rem; letter-spacing: 0.02em; }



    /* Pill badges */
    .pill {
        display: inline-block; padding: 0.24rem 0.7rem;
        border-radius: 20px; font-size: 0.7rem; font-weight: 700;
        letter-spacing: 0.03em; text-transform: uppercase;
    }
    .pill.blue   { background: #eff6ff; color: #1e40af; }
    .pill.green  { background: #ecfdf5; color: #065f46; }
    .pill.orange { background: var(--accent-lt); color: var(--accent); }
    .pill.red    { background: #fef2f2; color: #b91c1c; }

    /* Actions */
    .act-group { display: flex; gap: 0.35rem; justify-content: flex-end; }
    .act-btn {
        width: 28px; height: 28px; border-radius: 6px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.72rem; text-decoration: none; cursor: pointer;
        border: 1.5px solid var(--border); background: var(--surface);
        color: var(--ink-soft); transition: all 0.16s;
    }
    .act-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }
    .act-btn.edit:hover { border-color: #4f63d2; color: #4f63d2; background: #eef2ff; }
    .act-btn.del:hover { border-color: #ef4444; color: #ef4444; background: #fef2f2; }

    /* Empty state */
    .empty-state { padding: 4rem 1rem; text-align: center; }
    .empty-state .ei { font-size: 2.5rem; color: var(--border); margin-bottom: 0.75rem; display: block; }
    .empty-state p { color: var(--ink-mute); font-size: 0.875rem; margin: 0; }

    /* ── Modals ──────────────────────────────── */
    .bk-modal-backdrop {
        display: none; position: fixed; inset: 0; z-index: 10000;
        background: rgba(26,23,20,0.5); backdrop-filter: blur(3px);
        align-items: center; justify-content: center; padding: 1rem;
    }
    .bk-modal-backdrop.open { display: flex; }

    .bk-modal {
        background: white; border-radius: 16px; overflow: hidden;
        width: 100%; max-width: 1100px;
        box-shadow: 0 25px 50px rgba(26,23,20,0.2);
        animation: modalIn 0.25s ease;
        /* Fix for layout break: contain height within viewport */
        max-height: 90vh;
        display: flex; flex-direction: column;
    }
    .bk-modal form {
        display: flex; flex-direction: column;
        height: 100%; overflow: hidden;
    }
    @keyframes modalIn { from { opacity:0; transform:translateY(-16px); } to { opacity:1; transform:translateY(0); } }

    .modal-head {
        display: flex; align-items: center; justify-content: space-between;
        padding: 1.3rem 1.6rem; border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
        flex-shrink: 0; /* Header stays fixed */
    }
    .modal-head h3 {
        font-family: 'Fraunces', serif; font-size: 1.1rem;
        font-weight: 600; color: var(--ink); margin: 0;
        display: flex; align-items: center; gap: 0.6rem;
    }
    .modal-head p { font-size: 0.75rem; color: var(--ink-mute); margin: 0.25rem 0 0; }
    .modal-close {
        width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
        border: none; background: var(--cream); font-size: 1.2rem;
        color: var(--ink-mute); cursor: pointer; border-radius: 8px; transition: all 0.15s;
    }
    .modal-close:hover { background: var(--border); color: var(--ink); }

    .modal-body { padding: 1.75rem 1.6rem 2.5rem; display: flex; gap: 2rem; overflow-y: auto; /* Enable scrolling */}
    .modal-left { flex: 1; }
    .modal-right { width: 340px; flex-shrink: 0; }

    /* Form sections */
    .form-sec-title {
        font-size: 0.67rem; font-weight: 700; letter-spacing: 0.1em;
        text-transform: uppercase; color: var(--ink-mute);
        margin: 0 0 1rem; padding-bottom: 0.5rem;
        border-bottom: 1px solid var(--border-lt);
    }
    .form-sec-title:not(:first-child) { margin-top: 1.75rem; }

    .field-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.1rem; margin-bottom: 1.1rem; }
    .field-grid.full { grid-template-columns: 1fr; }

    .field label {
        display: block; font-size: 0.75rem; font-weight: 700;
        letter-spacing: 0.03em; text-transform: uppercase;
        color: var(--ink-soft); margin-bottom: 0.4rem;
    }
    .field input, .field select {
        width: 100%; height: 42px; padding: 0 0.85rem;
        border: 1.5px solid var(--border); border-radius: 8px;
        font-size: 0.875rem; color: var(--ink); background: #fdfcfa;
        outline: none; transition: border-color 0.18s, box-shadow 0.18s;
        -webkit-appearance: none; appearance: none;
    }
    .field select {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 0.8rem center;
        padding-right: 2.2rem;
    }
    .field input:focus, .field select:focus {
        border-color: var(--accent); background: white;
        box-shadow: 0 0 0 3px rgba(181,98,42,0.1);
    }

    /* Summary card */
    .sum-card {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 12px; overflow: hidden;
    }
    .sum-head {
        padding: 1.2rem; background: linear-gradient(135deg, var(--ink) 0%, #3e3936 100%);
        color: white;
    }
    .sum-head .sp { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.1em; opacity: 0.7; margin-bottom: 0.3rem; }
    .sum-head .sn { font-family: 'Fraunces', serif; font-size: 1.8rem; font-weight: 700; }

    .sum-body { padding: 1.2rem; }
    .sum-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 0.6rem 0; border-bottom: 1px solid var(--border-lt);
    }
    .sum-row:last-child { border-bottom: none; }
    .sum-label { font-size: 0.75rem; font-weight: 600; color: var(--ink-soft); }
    .sum-val { font-weight: 700; color: var(--ink); font-variant-numeric: tabular-nums; }

    .sum-row input[type="number"] {
        width: 100px; padding: 0.35rem 0.6rem; border: 1.5px solid var(--border);
        border-radius: 6px; text-align: right; font-size: 0.8rem;
        background: white; outline: none; transition: border-color 0.15s;
    }
    .sum-row input[type="number"]:focus { border-color: var(--accent); box-shadow: 0 0 0 2px rgba(181,98,42,0.1); }

    .sum-total {
        background: #ecfdf5; border: 1.5px dashed #10b981;
        border-radius: 10px; padding: 1rem;
        text-align: center; margin-top: 1rem;
    }
    .sum-total .st-lbl {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.08em;
        text-transform: uppercase; color: #065f46; margin-bottom: 0.3rem;
    }
    .sum-total .st-val { font-family: 'Fraunces', serif; font-size: 1.4rem; font-weight: 700; color: #065f46; }

    .btn-submit {
        width: 100%; margin-top: 1.25rem; padding: 0.85rem;
        background: var(--ink); color: white; border: none;
        border-radius: 8px; font-size: 0.875rem; font-weight: 700;
        cursor: pointer; transition: all 0.18s;
        display: flex; align-items: center; justify-content: center; gap: 0.5rem;
    }
    .btn-submit:hover { background: var(--accent); box-shadow: 0 4px 14px rgba(181,98,42,0.3); transform: translateY(-1px); }

    /* ── Responsive Modal ───────────────────── */
    @media (max-width: 860px) {
        .modal-body { flex-direction: column; gap: 1.5rem; }
        .modal-right { width: 100%; }
        .modal-left { width: 100%; }
    }

    /* ── Animations ──────────────────────────── */
    @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
</style>

<div class="bk-wrap">

    <!-- ── Header ───────────────────────────── -->
    <div class="bk-header">
        <div>
            <div class="eyebrow">Sales & Bookings</div>
            <h1>Property <em>Bookings</em></h1>
        </div>
        <div class="header-actions">
            <a href="create.php" class="btn-new">
                <i class="fas fa-plus"></i> New Booking
            </a>
        </div>
    </div>

    <!-- ── Stats Grid ───────────────────────── -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="s-icon ico-blue"><i class="fas fa-file-signature"></i></div>
            <div class="stat-label">Total Bookings</div>
            <div class="stat-value"><?= $total_bookings_count ?></div>
        </div>

        <div class="stat-card">
            <div class="s-icon ico-green"><i class="fas fa-chart-area"></i></div>
            <div class="stat-label">Total Area</div>
            <div class="stat-value"><?= number_format($total_area_sold, 0) ?> <span class="unit">sqft</span></div>
        </div>

        <div class="stat-card">
            <div class="s-icon ico-purple"><i class="fas fa-percent"></i></div>
            <div class="stat-label">Avg Rate</div>
            <div class="stat-value">₹<?= number_format($average_rate, 0) ?> <span class="unit">/sqft</span></div>
        </div>

        <div class="stat-card">
            <div class="s-icon ico-orange"><i class="fas fa-wallet"></i></div>
            <div class="stat-label">Sold Value</div>
            <div class="stat-value">
                <span class="short-val"><?= formatCurrencyShort($total_bookings_value) ?></span>
                <span class="full-val"><?= formatCurrency($total_bookings_value) ?></span>
            </div>
        </div>

        <div class="stat-card">
            <div class="s-icon ico-green"><i class="fas fa-hand-holding-usd"></i></div>
            <div class="stat-label">Received</div>
            <div class="stat-value">
                <span class="short-val"><?= formatCurrencyShort($total_received_value) ?></span>
                <span class="full-val"><?= formatCurrency($total_received_value) ?></span>
            </div>
        </div>

        <div class="stat-card">
            <div class="s-icon ico-orange"><i class="fas fa-clock"></i></div>
            <div class="stat-label">Pending</div>
            <div class="stat-value">
                <span class="short-val"><?= formatCurrencyShort($total_pending_value) ?></span>
                <span class="full-val"><?= formatCurrency($total_pending_value) ?></span>
            </div>
        </div>
    </div>

    <!-- ── Main Panel ────────────────────────── -->
    <div class="bk-panel">

        <!-- Toolbar -->
        <div class="panel-toolbar">
            <div class="toolbar-left">
                <div class="toolbar-icon"><i class="fas fa-file-contract"></i></div>
                <div>
                    <div class="toolbar-title">All Bookings</div>
                    <span class="toolbar-subtitle">Manage customer reservations</span>
                </div>
            </div>
            <div class="toolbar-div"></div>

            <div class="toolbar-actions">
                <button class="btn-filter" onclick="toggleFilters()">
                    <i class="fas fa-filter"></i> Filters
                </button>
                <a href="cancelled.php" class="btn-action cancelled">
                    <i class="fas fa-ban"></i> Cancelled
                </a>
                <a href="export.php" class="btn-action export">
                    <i class="fas fa-file-excel"></i> Export
                </a>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section <?= ($status_filter || $project_filter || $referrer_filter) ? 'show' : '' ?>" id="filterSection">
            <form method="GET" class="filter-form">
                <select name="project" class="f-select">
                    <option value="">All Projects</option>
                    <?php foreach ($projects as $proj): ?>
                        <option value="<?= $proj['id'] ?>" <?= $project_filter == $proj['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($proj['project_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="referrer" class="f-select">
                    <option value="">All Referrers</option>
                    <?php foreach ($referrers as $ref): ?>
                        <option value="<?= htmlspecialchars($ref) ?>" <?= $referrer_filter === $ref ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ref) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="status" class="f-select" style="flex:0 0 120px">
                    <option value="">All Status</option>
                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>

                <button type="submit" class="btn-go"><i class="fas fa-search"></i> Apply</button>

                <?php if ($status_filter || $project_filter || $referrer_filter): ?>
                    <a href="index.php" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Table -->
        <div style="overflow-x:auto">
            <table class="bk-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Customer / Flat</th>
                        <th>Mobile</th>
                        <th class="th-r">Area</th>
                        <th>Referred By</th>
                        <th class="th-r">Rate</th>
                        <th class="th-r">Agreement</th>
                        <th class="th-r">Received</th>
                        <th class="th-r">Pending</th>
                        <th class="th-c">Status</th>
                        
                        <th class="th-r">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr>
                            <td colspan="12">
                                <div class="empty-state">
                                    <span class="ei"><i class="fas fa-folder-open"></i></span>
                                    <p>No bookings found<?= ($status_filter || $project_filter || $referrer_filter) ? ' matching your filters' : '' ?>.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $booking):
                            $pillClass = match($booking['status']) {
                                'active'    => 'blue',
                                'completed' => 'green',
                                'cancelled' => 'red',
                                default     => 'orange'
                            };
                        ?>
                        <tr>
                            <td><span style="font-size:0.78rem;font-weight:600;color:var(--ink-soft)"><?= date('d M Y', strtotime($booking['booking_date'])) ?></span></td>
                            <td>
                                <div class="cust-flat-cell">
                                    <span class="cust-name"><?= htmlspecialchars($booking['customer_name']) ?></span>
                                    <div style="margin-top:4px; display:flex; align-items:center; gap:5px;">
                                        <?= renderProjectBadge($booking['project_name'], $booking['project_id']) ?>
                                        <span class="cust-sub" style="margin:0;">&ndash; <?= htmlspecialchars($booking['flat_no']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="mobile-cell"><?= htmlspecialchars($booking['customer_mobile']) ?></span>
                            </td>
                            <td class="td-r"><span style="font-weight:600;color:var(--ink-soft);font-size:0.82rem"><?= number_format($booking['area_sqft'], 0) ?> sqft</span></td>
                            <td>
                                <?php if(!empty($booking['referred_by'])): ?>
                                    <span style="font-size:0.8rem;color:var(--ink-soft)"><i class="fas fa-user-tag" style="color:var(--ink-mute);margin-right:3px"></i> <?= htmlspecialchars($booking['referred_by']) ?></span>
                                <?php else: ?>
                                    <span style="color:var(--border)">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="td-r">
                                <?php if(!empty($booking['rate'])): ?>
                                    <span style="font-weight:600;color:var(--ink)" title="<?= formatCurrency($booking['rate']) ?>"><?= formatCurrencyShort($booking['rate']) ?></span>
                                <?php else: ?>
                                    <span style="color:var(--border)">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="td-r"><span class="pill green" title="<?= formatCurrency($booking['agreement_value']) ?>"><?= formatCurrencyShort($booking['agreement_value']) ?></span></td>
                            <td class="td-r"><span style="font-size:0.82rem;font-weight:600;color:#10b981" title="<?= formatCurrency($booking['total_received']) ?>"><?= formatCurrencyShort($booking['total_received']) ?></span></td>
                            <td class="td-r">
                                <?php if($booking['total_pending'] > 0): ?>
                                    <span style="font-size:0.82rem;font-weight:600;color:#f59e0b" title="<?= formatCurrency($booking['total_pending']) ?>"><?= formatCurrencyShort($booking['total_pending']) ?></span>
                                <?php else: ?>
                                    <span class="pill green">Paid</span>
                                <?php endif; ?>
                            </td>
                            <td class="td-c"><span class="pill <?= $pillClass ?>"><?= ucfirst($booking['status']) ?></span></td>
                            
                            <td class="td-r">
                                <div class="act-group">
                                    <a href="<?= BASE_URL ?>modules/booking/view.php?id=<?= $booking['id'] ?>" class="act-btn" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($booking['status'] !== 'cancelled'): ?>
                                        <a href="<?= BASE_URL ?>modules/booking/edit.php?id=<?= $booking['id'] ?>" class="act-btn edit" title="Edit">
                                            <i class="fas fa-pencil-alt"></i>
                                        </a>
                                        <button class="act-btn del"
                                            onclick="openCancelModal(<?= $booking['id'] ?>, '<?= htmlspecialchars(addslashes($booking['project_name'])) ?>', '<?= htmlspecialchars($booking['flat_no']) ?>')"
                                            title="Cancel">
                                            <i class="fas fa-times-circle"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div><!-- /.bk-panel -->
</div><!-- /.bk-wrap -->




<!-- ══════════ CANCEL MODAL ══════════ -->
<div class="bk-modal-backdrop" id="cancelModal">
    <div class="bk-modal" style="max-width:480px">
        <div class="modal-head" style="background:#fef2f2;border-color:#fca5a5">
            <div>
                <h3 style="color:#b91c1c"><i class="fas fa-exclamation-triangle"></i> Confirm Cancellation</h3>
            </div>
            <button type="button" class="modal-close" onclick="closeModal('cancelModal')">×</button>
        </div>
        <div style="padding:2rem;text-align:center">
            <div style="width:56px;height:56px;background:#fef2f2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem">
                <i class="fas fa-ban" style="font-size:1.5rem;color:#ef4444"></i>
            </div>
            <h4 style="margin:0 0 0.75rem;color:var(--ink);font-weight:700">Are you sure?</h4>
            <p style="color:var(--ink-soft);margin:0 0 1.25rem;line-height:1.6;font-size:0.875rem">
                You are about to cancel booking for<br>
                <strong id="cancel_project_name" style="color:var(--ink)"></strong> - Flat <strong id="cancel_flat_no" style="color:var(--ink)"></strong>.
            </p>
            <p style="font-size:0.78rem;color:var(--ink-mute);background:#fdfcfa;padding:0.75rem;border-radius:8px;margin-bottom:1.5rem">
                This will take you to the cancellation page to process refunds.
            </p>
            <div style="display:flex;gap:0.75rem;justify-content:center">
                <button type="button" onclick="closeModal('cancelModal')"
                        style="padding:0.6rem 1.25rem;border:1.5px solid var(--border);background:white;color:var(--ink-soft);border-radius:8px;font-weight:600;cursor:pointer">
                    Go Back
                </button>
                <a href="#" id="confirm_cancel_btn"
                   style="padding:0.6rem 1.25rem;background:#ef4444;color:white;border:none;border-radius:8px;font-weight:600;text-decoration:none">
                    Proceed to Cancel
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleFilters() {
        const filterSection = document.getElementById('filterSection');
        filterSection.classList.toggle('show');
    }

    function openCancelModal(bookingId, projectName, flatNo) {
        document.getElementById('cancel_project_name').textContent = projectName;
        document.getElementById('cancel_flat_no').textContent = flatNo;
        document.getElementById('confirm_cancel_btn').href = 'cancel.php?id=' + bookingId;
        
        const modal = document.getElementById('cancelModal');
        modal.classList.add('open');
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('open');
        }
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('cancelModal');
        if (event.target == modal) {
            closeModal('cancelModal');
        }
    }
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>