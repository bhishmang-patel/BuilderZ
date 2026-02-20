<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/InvestmentService.php';
require_once __DIR__ . '/../../includes/MasterService.php';
require_once __DIR__ . '/../../includes/ColorHelper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'accountant']);

$investmentService = new InvestmentService();
$masterService = new MasterService();
$page_title = 'Investments';
$current_page = 'investments';

// Handle CRUD Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
         setFlashMessage('error', 'Security token expired. Please try again.');
         redirect('modules/investments/index.php');
    }

    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create') {
            $investmentService->createInvestment($_POST, $_SESSION['user_id']);
            setFlashMessage('success', 'Investment recorded successfully');
        
        } elseif ($action === 'update') {
            $investmentService->updateInvestment(intval($_POST['id']), $_POST);
            setFlashMessage('success', 'Investment updated successfully');
        
        } elseif ($action === 'delete') {
            $investmentService->deleteInvestment(intval($_POST['id']));
            setFlashMessage('success', 'Investment deleted successfully');
        }
    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
    }
    
    redirect('modules/investments/index.php');
}

// Fetch all investments
$filters = [
    'search' => $_GET['search'] ?? '',
    'project_id' => $_GET['project_id'] ?? '',
    'investment_type' => $_GET['investment_type'] ?? ''
];
$investments = $investmentService->getAllInvestments($filters);
$projects = $masterService->getAllProjects();

// Stats
$total_invested = 0;
foreach($investments as $inv) {
    $total_invested += $inv['amount'];
}

// Handle Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'investments_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Project', 'Investor Name', 'Type', 'Amount', 'Remarks']);
    foreach ($investments as $row) {
        fputcsv($output, [
            date('d-M-Y', strtotime($row['investment_date'])),
            $row['project_name'],
            $row['investor_name'],
            ucfirst($row['investment_type']),
            $row['amount'],
            $row['remarks']
        ]);
    }
    fclose($output);
    exit();
}

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
    .inv-wrap { max-width: 1280px; margin: 2.5rem auto; padding: 0 1.5rem 4rem; }

    /* ── Header ──────────────────────────────── */
    .inv-header {
        margin-bottom: 2rem; padding-bottom: 1.5rem;
        border-bottom: 1.5px solid var(--border);
        display: flex; align-items: flex-end; justify-content: space-between;
        flex-wrap: wrap; gap: 1rem;
    }

    .inv-header .eyebrow {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.15em;
        text-transform: uppercase; color: var(--accent); margin-bottom: 0.3rem;
    }
    .inv-header h1 {
        font-family: 'Fraunces', serif; font-size: 1.7rem; font-weight: 700;
        line-height: 1.1; color: var(--ink); margin: 0;
    }

    .header-actions { display: flex; gap: 0.6rem; flex-wrap: wrap; }
    .inv-header h1 em { color: var(--accent); font-style: italic; }
    .btn-new {
        display: inline-flex; align-items: center; gap: 0.5rem;
        padding: 0.68rem 1.4rem; background: var(--ink); color: white;
        border-radius: 8px; text-decoration: none;
        font-size: 0.875rem; font-weight: 600;
        transition: background 0.18s, transform 0.15s, box-shadow 0.18s;
        border: 1.5px solid var(--ink);
    }
    .btn-new:hover { background: var(--accent); border-color: var(--accent); transform: translateY(-1px); box-shadow: 0 4px 14px rgba(181,98,42,0.28); color: white; }

    /* ── Stats Card ──────────────────────────── */
    .stat-card {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 12px; padding: 1.3rem 1.5rem; width: 25%;
        transition: transform 0.2s, box-shadow 0.2s;
        animation: fadeUp 0.4s ease both;
        margin-bottom: 1.75rem; display: flex; align-items: center; gap: 1.25rem;
    }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(26,23,20,0.07); }

    .s-icon {
        width: 48px; height: 48px; border-radius: 11px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem; background: var(--accent-bg); color: var(--accent);
        flex-shrink: 0;
    }

    .stat-content { flex: 1; }
    .stat-label {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.07em;
        text-transform: uppercase; color: var(--ink-soft); margin-bottom: 0.4rem;
    }

    .stat-value {
        font-family: 'Fraunces', serif; font-size: 1.6rem; font-weight: 700;
        color: var(--ink); line-height: 1; font-variant-numeric: tabular-nums;
        position: relative;
    }

    .stat-value .short-val, .stat-value .full-val { transition: opacity 0.2s; }
    .stat-value .full-val { display: none; }
    .stat-card:hover .stat-value .short-val { display: none; }
    .stat-card:hover .stat-value .full-val { display: inline; }

    /* ── Main Panel ──────────────────────────── */
    .inv-panel {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 14px; overflow: hidden;
        animation: fadeUp 0.45s 0.15s ease both;
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

    .btn-filter, .btn-export {
        display: inline-flex; align-items: center; gap: 0.4rem;
        padding: 0.55rem 1rem; border: 1.5px solid var(--border);
        background: white; color: var(--ink-soft);
        border-radius: 7px; font-size: 0.8rem; font-weight: 600;
        cursor: pointer; transition: all 0.18s; text-decoration: none;
    }
    .btn-filter:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }
    .btn-export { background: #f0ece5; border-color: var(--border); color: var(--ink-soft); }
    .btn-export:hover { background: var(--border); color: var(--ink); }

    @media (max-width: 920px) {
        .panel-toolbar { flex-wrap: wrap; }
        .toolbar-div { display: none; }
        .toolbar-actions { width: 100%; justify-content: flex-start; }
    }

    /* ── Filter Section ──────────────────────── */
    .filter-section {
        display: none; padding: 1.25rem 1.5rem;
        border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }
    .filter-section.show { display: block; }

    .filter-form { display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap; }

    .f-input, .f-select {
        height: 38px; padding: 0 0.75rem;
        border: 1.5px solid var(--border); border-radius: 7px;
        font-size: 0.82rem; color: var(--ink); background: white;
        outline: none; transition: border-color 0.15s;
    }
    .f-input { flex: 0 0 200px; }
    .f-select {
        flex: 0 0 160px; -webkit-appearance: none; appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 0.6rem center;
        padding-right: 2rem;
    }
    .f-input:focus, .f-select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(181,98,42,0.1); }

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
    .inv-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }

    .inv-table thead tr { background: #fdfcfa; border-bottom: 1.5px solid var(--border); }
    .inv-table thead th {
        padding: 0.7rem 1rem; text-align: left;
        font-size: 0.64rem; font-weight: 700; letter-spacing: 0.1em;
        text-transform: uppercase; color: var(--ink-soft); white-space: nowrap;
    }
    .inv-table thead th.th-c { text-align: center; }
    .inv-table thead th.th-r { text-align: right; }

    .inv-table tbody tr { border-bottom: 1px solid var(--border-lt); transition: background 0.13s; }
    .inv-table tbody tr:last-child { border-bottom: none; }
    .inv-table tbody tr:hover { background: #fdfcfa; }

    .inv-table td { padding: 0.8rem 1rem; vertical-align: middle; }
    .inv-table td.td-c { text-align: center; }
    .inv-table td.td-r { text-align: right; }

    /* Avatar */
    .av-sq {
        width: 28px; height: 28px; border-radius: 7px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.7rem; font-weight: 700; color: white;
        margin-right: 0.65rem; flex-shrink: 0;
    }

    /* Pill badges */
    .pill {
        display: inline-block; padding: 0.24rem 0.7rem;
        border-radius: 20px; font-size: 0.7rem; font-weight: 700;
        letter-spacing: 0.03em;
    }
    .pill.loan     { background: #fef2f2; color: #b91c1c; }
    .pill.partner  { background: #eff6ff; color: #1e40af; }
    .pill.personal { background: #ecfdf5; color: #065f46; }
    .pill.other    { background: #f0ece5; color: var(--ink-soft); }

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
    .act-btn.del:hover { border-color: #ef4444; color: #ef4444; background: #fef2f2; }

    /* Empty state */
    .empty-state {
        padding: 4rem 1rem; text-align: center;
    }
    .empty-state i {
        font-size: 2.5rem; color: var(--border);
        margin-bottom: 0.75rem; display: block;
    }
    .empty-state h4 {
        font-size: 1rem; font-weight: 700; color: var(--ink-soft);
        margin: 0 0 0.35rem;
    }
    .empty-state p {
        font-size: 0.82rem; color: var(--ink-mute); margin: 0;
    }

    /* ── Modals ──────────────────────────────── */
    .inv-modal-backdrop {
        display: none; position: fixed; inset: 0; z-index: 10000;
        background: rgba(26,23,20,0.5); backdrop-filter: blur(3px);
        align-items: center; justify-content: center; padding: 1rem;
    }
    .inv-modal-backdrop.open { display: flex; }

    .inv-modal {
        background: white; border-radius: 16px; overflow: hidden;
        width: 100%; max-width: 580px;
        box-shadow: 0 25px 50px rgba(26,23,20,0.2);
        animation: modalIn 0.25s ease;
    }
    @keyframes modalIn { from { opacity:0; transform:translateY(-16px); } to { opacity:1; transform:translateY(0); } }

    .modal-head {
        display: flex; align-items: center; justify-content: space-between;
        padding: 1.3rem 1.6rem; border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }
    .modal-head h3 {
        font-family: 'Fraunces', serif; font-size: 1.1rem;
        font-weight: 600; color: var(--ink); margin: 0;
        display: flex; align-items: center; gap: 0.6rem;
    }
    .modal-head h3 i { color: var(--accent); }
    .modal-head p { font-size: 0.75rem; color: var(--ink-mute); margin: 0.25rem 0 0; }
    .modal-close {
        width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
        border: none; background: var(--cream); font-size: 1.2rem;
        color: var(--ink-mute); cursor: pointer; border-radius: 8px; transition: all 0.15s;
    }
    .modal-close:hover { background: var(--border); color: var(--ink); }

    .modal-body { padding: 1.75rem 1.6rem; }

    .modal-footer {
        display: flex; justify-content: flex-end; gap: 0.65rem;
        padding: 1.25rem 1.6rem; border-top: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }

    /* Form fields */
    .field {
        margin-bottom: 1.1rem;
    }
    .field label {
        display: block; font-size: 0.75rem; font-weight: 700;
        letter-spacing: 0.03em; text-transform: uppercase;
        color: var(--ink-soft); margin-bottom: 0.4rem;
    }
    .field input, .field select, .field textarea {
        width: 100%; padding: 0.65rem 0.85rem;
        border: 1.5px solid var(--border); border-radius: 8px;
        font-size: 0.875rem; color: var(--ink); background: #fdfcfa;
        outline: none; transition: border-color 0.18s, box-shadow 0.18s;
    }
    .field select {
        -webkit-appearance: none; appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 0.8rem center;
        padding-right: 2.2rem;
    }
    .field input:focus, .field select:focus, .field textarea:focus {
        border-color: var(--accent); background: white;
        box-shadow: 0 0 0 3px rgba(181,98,42,0.1);
    }

    .field-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }

    .highlight-box {
        background: #ecfdf5; border: 1.5px dashed #10b981;
        border-radius: 10px; padding: 1.25rem;
        text-align: center; margin-bottom: 1.5rem;
    }
    .highlight-box .lbl {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.08em;
        text-transform: uppercase; color: #065f46; margin-bottom: 0.4rem;
    }
    .highlight-box .val { font-family: 'Fraunces', serif; font-size: 1.8rem; font-weight: 700; color: #065f46; }

    .detail-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 0.75rem 0; border-bottom: 1px solid var(--border-lt);
    }
    .detail-row:last-child { border-bottom: none; }
    .detail-row .lbl { font-size: 0.8rem; font-weight: 600; color: var(--ink-soft); }
    .detail-row .val { font-size: 0.875rem; font-weight: 600; color: var(--ink); }

    .remarks-box {
        background: #fdfcfa; border: 1px solid var(--border-lt);
        border-radius: 8px; padding: 1rem;
        font-size: 0.85rem; color: var(--ink-soft);
        line-height: 1.6; min-height: 50px; margin-top: 1rem;
    }

    .btn {
        padding: 0.7rem 1.4rem; border-radius: 8px;
        font-size: 0.875rem; font-weight: 600; cursor: pointer;
        transition: all 0.18s; display: inline-flex;
        align-items: center; gap: 0.5rem; text-decoration: none;
    }
    .btn-secondary { background: white; color: var(--ink-soft); border: 1.5px solid var(--border); }
    .btn-secondary:hover { border-color: var(--accent); color: var(--accent); }
    .btn-primary {
        background: var(--ink); color: white; border: 1.5px solid var(--ink);
    }
    .btn-primary:hover { background: var(--accent); border-color: var(--accent); box-shadow: 0 4px 14px rgba(181,98,42,0.3); }
    .btn-danger { background: #ef4444; color: white; border: 1.5px solid #ef4444; }
    .btn-danger:hover { background: #dc2626; box-shadow: 0 4px 14px rgba(239,68,68,0.3); }

    /* Animations */
    @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }

    /* ── Analytics Styles ── */

    /* KPI Strip */
    .kpi-strip {
        display:grid; grid-template-columns:repeat(4,1fr);
        gap:1px; background:var(--border);
        border:1.5px solid var(--border); border-radius:14px;
        overflow:hidden; margin-bottom:1.5rem;
    }
    .kpi-cell {
        background:var(--surface); padding:1.25rem 1.4rem;
        position:relative; transition:background 0.2s;
    }
    .kpi-cell:hover { background:#fdfcfa; }
    .kpi-cell::after {
        content:''; position:absolute; bottom:0; left:0; right:0; height:2.5px;
    }
    .kpi-cell:nth-child(1)::after { background:var(--accent); }
    .kpi-cell:nth-child(2)::after { background:#10b981; }
    .kpi-cell:nth-child(3)::after { background:#8b5cf6; }
    .kpi-cell:nth-child(4)::after { background:#f59e0b; }
    .kpi-lbl {
        font-size:0.62rem; font-weight:700; letter-spacing:0.1em;
        text-transform:uppercase; color:var(--ink-mute); margin-bottom:0.55rem;
    }
    .kpi-val {
        font-family:'Fraunces',serif; font-size:1.65rem; font-weight:400;
        color:var(--ink); letter-spacing:-0.02em; line-height:1;
    }
    
    /* Hover reveal for large numbers */
    .kpi-val .short-val, .kpi-val .full-val { transition: opacity 0.2s; }
    .kpi-val .full-val { display: none; }
    .kpi-cell:hover .kpi-val .short-val { display: none; }
    .kpi-cell:hover .kpi-val .full-val { display: inline; }
    .kpi-delta { margin-top:0.45rem; font-size:0.7rem; font-weight:600; }
    .kpi-delta.up   { color:#10b981; }
    .kpi-delta.down { color:#ef4444; }
    .kpi-delta.neu  { color:var(--ink-mute); }

    /* Charts Grid */
    .an-grid {
        display:grid; grid-template-columns:1.6fr 1fr;
        gap:1rem; margin-bottom:2rem;
    }
    .an-card {
        background:var(--surface); border:1.5px solid var(--border);
        border-radius:14px; overflow:hidden;
        animation:fadeUp 0.45s ease both; position:relative;
        transition:border-color 0.22s, box-shadow 0.22s;
    }
    .an-card:hover {
        border-color:#d4ccc0;
        box-shadow:0 8px 32px rgba(26,23,20,0.08);
    }
    .an-card::before {
        content:''; position:absolute; top:0; left:0; right:0; height:1px;
        background:linear-gradient(90deg,transparent,rgba(26,23,20,0.08),transparent);
    }
    .an-card.span-full { grid-column:1 / -1; animation-delay:0.05s; }
    .an-card:nth-child(2) { animation-delay:0.12s; }
    .an-card:nth-child(3) { animation-delay:0.2s; }

    /* Card Header */
    .ac-head {
        display:flex; align-items:flex-start; justify-content:space-between;
        padding:1.25rem 1.5rem 0;
    }
    .ac-title {
        font-family:'Fraunces',serif; font-size:0.95rem; font-weight:600;
        color:var(--ink); letter-spacing:-0.01em; line-height:1.25;
    }
    .ac-title em { font-style:italic; color:#b0763a; }
    .ac-sub {
        font-size:0.67rem; color:var(--ink-mute); margin-top:0.2rem;
        letter-spacing:0.02em;
    }
    .ac-badge {
        font-size:0.58rem; font-weight:700; padding:0.28rem 0.65rem;
        border-radius:6px; letter-spacing:0.1em; text-transform:uppercase;
        flex-shrink:0;
    }
    .badge-amber { background:#fef3c7; color:#92400e; border:1px solid #fde68a; }
    .badge-green { background:#d1fae5; color:#065f46; border:1px solid #a7f3d0; }
    .badge-purple{ background:#ede9fe; color:#5b21b6; border:1px solid #ddd6fe; }

    /* Card Body */
    .ac-body { padding:1rem 1.5rem 1.5rem; }

    /* Stat footer row */
    .ac-footer {
        display:flex; gap:1.5rem; flex-wrap:wrap;
        padding:0.9rem 1.5rem; border-top:1px solid var(--border-lt);
        background:#fdfcfa;
    }
    .af-item .af-lbl {
        font-size:0.58rem; font-weight:700; letter-spacing:0.1em;
        text-transform:uppercase; color:var(--ink-mute); margin-bottom:0.2rem;
    }
    .af-item .af-val {
        font-family:'Fraunces',serif; font-size:0.98rem;
        color:var(--ink); font-weight:600; letter-spacing:-0.01em;
    }

    /* Donut layout */
    .donut-layout { display:flex; align-items:center; gap:0.5rem; min-height:210px; }
    .donut-canvas-wrap { position:relative; flex:0 0 180px; height:180px; display:flex; align-items:center; justify-content:center; }
    .donut-center-text { position:absolute; text-align:center; pointer-events:none; }
    .dct-val { font-family:'Fraunces',serif; font-size:1.3rem; font-weight:700; color:var(--ink); line-height:1; }
    .dct-lbl { font-size:0.58rem; font-weight:700; letter-spacing:0.1em; text-transform:uppercase; color:var(--ink-mute); margin-top:0.25rem; }

    /* Custom legend */
    .c-legend { flex:1; display:flex; flex-direction:column; gap:0.65rem; padding-left:0.75rem; }
    .cl-item { display:flex; align-items:center; gap:0.65rem; cursor:default; }
    .cl-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
    .cl-name { font-size:0.8rem; color:var(--ink-soft); flex:1; }
    .cl-pct { font-size:0.7rem; color:var(--ink-mute); font-weight:600; }

    /* Horizontal Bars */
    .hbar-list { display:flex; flex-direction:column; gap:1.1rem; }
    .hbar-meta { display:flex; justify-content:space-between; align-items:baseline; margin-bottom:0.45rem; }
    .hbar-name { font-size:0.8rem; font-weight:500; color:var(--ink-soft); }
    .hbar-amount { font-size:0.72rem; font-weight:700; color:var(--ink-mute); }
    .hbar-track { height:5px; background:var(--border-lt); border-radius:99px; overflow:hidden; }
    .hbar-fill {
        height:100%; border-radius:99px;
        transition:width 1s cubic-bezier(0.16,1,0.3,1);
        position:relative;
    }
    .hbar-fill::after {
        content:''; position:absolute; inset:0;
        background:linear-gradient(90deg,transparent 60%,rgba(255,255,255,0.5));
        border-radius:99px;
    }

    @media(max-width:900px){
        .an-grid { grid-template-columns:1fr; }
        .an-card.span-full { grid-column:1; }
        .kpi-strip { grid-template-columns:repeat(2,1fr); }
    }
</style>

<div class="inv-wrap">

    <!-- Header -->
    <div class="inv-header">
        <div>
            <div class="eyebrow">Capital Tracking</div>
            <h1>Investment <em>Analytics</em></h1>
        </div>
        <div class="header-actions">
            <button class="btn-new" onclick="openModal('addModal')">
                <i class="fas fa-plus"></i> New Investment
            </button>
        </div>
    </div>

    <!-- ── Analytics Section ────────────────── -->

<?php if (!empty($investments)): 
    /* ── Prepare Data ── */
    $byType    = [];
    $byProject = [];
    $monthly   = [];

    $total_returned_all = 0;
    $balance_all        = 0;

    foreach ($investments as $inv) {
        $t = ucfirst($inv['investment_type']);
        $byType[$t] = ($byType[$t] ?? 0) + $inv['amount'];

        $p = $inv['project_name'];
        $byProject[$p] = ($byProject[$p] ?? 0) + $inv['amount'];

        $sortKey = date('Y-m', strtotime($inv['investment_date']));
        if (!isset($monthly[$sortKey])) {
            $monthly[$sortKey] = ['label' => date('M', strtotime($inv['investment_date'])), 'amount' => 0];
        }
        $monthly[$sortKey]['amount'] += $inv['amount'];

        $total_returned_all += ($inv['total_returned'] ?? 0);
        $balance_all        += ($inv['balance'] ?? 0);
    }
    ksort($monthly);
    $monthly = array_slice($monthly, -12);

    /* Sort projects descending by amount, cap at 5 */
    arsort($byProject);
    $byProject = array_slice($byProject, 0, 5, true);

    /* KPI helpers */
    $avg_investment = count($investments) > 0 ? $total_invested / count($investments) : 0;
    $peak_month     = '';
    $peak_amount    = 0;
    foreach ($monthly as $m) {
        if ($m['amount'] > $peak_amount) {
            $peak_amount = $m['amount'];
            $peak_month  = $m['label'];
        }
    }
    $active_months = count(array_filter($monthly, fn($m) => $m['amount'] > 0));

    /* JSON for JS */
    $jsonTypeLabels  = json_encode(array_keys($byType));
    $jsonTypeValues  = json_encode(array_values($byType));
    $jsonProjLabels  = json_encode(array_keys($byProject));
    $jsonProjValues  = json_encode(array_values($byProject));
    $jsonMonthLabels = json_encode(array_column($monthly, 'label'));
    $jsonMonthValues = json_encode(array_column($monthly, 'amount'));
?>


<!-- ── KPI Strip ── -->
<div class="kpi-strip">
    <div class="kpi-cell">
        <div class="kpi-lbl">Total Invested</div>
        <div class="kpi-val">
            <span class="short-val"><?= formatCurrencyShort($total_invested) ?></span>
            <span class="full-val"><?= formatCurrency($total_invested) ?></span>
        </div>
        <div class="kpi-delta neu"><?= count($investments) ?> records total</div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-lbl">Total Returned</div>
        <div class="kpi-val">
            <span class="short-val"><?= formatCurrencyShort($total_returned_all) ?></span>
            <span class="full-val"><?= formatCurrency($total_returned_all) ?></span>
        </div>
        <div class="kpi-delta up">
            ↑ <?= $total_invested > 0 ? number_format($total_returned_all/$total_invested*100,1) : 0 ?>% return rate
        </div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-lbl">Balance Outstanding</div>
        <div class="kpi-val">
            <span class="short-val"><?= formatCurrencyShort($balance_all) ?></span>
            <span class="full-val"><?= formatCurrency($balance_all) ?></span>
        </div>
        <div class="kpi-delta <?= $balance_all > 0 ? 'down' : 'up' ?>">
            <?= $balance_all > 0 ? '↓ Pending recovery' : '✓ Fully recovered' ?>
        </div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-lbl">Avg. Investment</div>
        <div class="kpi-val">
            <span class="short-val"><?= formatCurrencyShort($avg_investment) ?></span>
            <span class="full-val"><?= formatCurrency($avg_investment) ?></span>
        </div>
        <div class="kpi-delta neu"><?= count($byProject) ?> projects tracked</div>
    </div>
</div>

<!-- ── Charts Grid ── -->
<div class="an-grid">

    <!-- Trend Chart — full width -->
    <div class="an-card span-full">
        <div class="ac-head">
            <div>
                <div class="ac-title">Monthly <em>Flow</em></div>
                <div class="ac-sub">Investment volume over time · Last 12 months</div>
            </div>
            <div class="ac-badge badge-green">TREND</div>
        </div>
        <div class="ac-body" style="height:160px">
            <canvas id="anTrendChart"></canvas>
        </div>
        <div class="ac-footer">
            <div class="af-item">
                <div class="af-lbl">Peak Month</div>
                <div class="af-val"><?= $peak_month ?: '—' ?></div>
            </div>
            <div class="af-item">
                <div class="af-lbl">Largest Inflow</div>
                <div class="af-val"><?= formatCurrencyShort($peak_amount) ?></div>
            </div>
            <div class="af-item">
                <div class="af-lbl">Active Months</div>
                <div class="af-val"><?= $active_months ?> of <?= count($monthly) ?></div>
            </div>
            <div class="af-item">
                <div class="af-lbl">Avg. / Month</div>
                <div class="af-val"><?= $active_months > 0 ? formatCurrencyShort($total_invested / $active_months) : '—' ?></div>
            </div>
        </div>
    </div>

    <!-- Distribution Donut -->
    <div class="an-card">
        <div class="ac-head">
            <div>
                <div class="ac-title">Capital <em>Distribution</em></div>
                <div class="ac-sub">By investment type</div>
            </div>
            <div class="ac-badge badge-amber">SPLIT</div>
        </div>
        <div class="ac-body">
            <div class="donut-layout">
                <div class="donut-canvas-wrap">
                    <canvas id="anTypeChart" width="180" height="180"></canvas>
                    <div class="donut-center-text">
                        <div class="dct-val"><?= count($byType) ?></div>
                        <div class="dct-lbl">Types</div>
                    </div>
                </div>
                <div class="c-legend" id="anTypeLegend"></div>
            </div>
        </div>
    </div>

    <!-- Project Bars -->
    <div class="an-card">
        <div class="ac-head">
            <div>
                <div class="ac-title">Project <em>Breakdown</em></div>
                <div class="ac-sub">Capital allocated per project</div>
            </div>
            <div class="ac-badge badge-purple">PROJECTS</div>
        </div>
        <div class="ac-body">
            <div class="hbar-list" id="anProjBars"></div>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Common chart defaults ──
    Chart.defaults.font.family = "'DM Sans', sans-serif";
    Chart.defaults.color = '#9e9690';

    const palette   = ['#b5622a', '#10b981', '#8b5cf6', '#f59e0b', '#3b82f6'];
    const typeLabels  = <?= $jsonTypeLabels ?>;
    const typeValues  = <?= $jsonTypeValues ?>;
    const projLabels  = <?= $jsonProjLabels ?>;
    const projValues  = <?= $jsonProjValues ?>;
    const monthLabels = <?= $jsonMonthLabels ?>;
    const monthValues = <?= $jsonMonthValues ?>;

    // ── 1. Trend Chart ──
    const tCtx = document.getElementById('anTrendChart').getContext('2d');
    const tGrad = tCtx.createLinearGradient(0, 0, 0, 160);
    tGrad.addColorStop(0,   'rgba(16,185,129,0.18)');
    tGrad.addColorStop(0.65,'rgba(16,185,129,0.03)');
    tGrad.addColorStop(1,   'rgba(16,185,129,0)');

    new Chart(tCtx, {
        type: 'line',
        data: {
            labels: monthLabels,
            datasets: [{
                data: monthValues,
                borderColor: '#10b981',
                borderWidth: 2,
                pointBackgroundColor: '#10b981',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: monthValues.map(v => v > 0 ? 4 : 0),
                pointHoverRadius: 6,
                backgroundColor: tGrad,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1a1714',
                    titleColor: '#9e9690',
                    bodyColor: '#e8e3db',
                    borderColor: 'rgba(232,227,219,0.12)',
                    borderWidth: 1,
                    padding: 10,
                    callbacks: { label: ctx => '  ' + new Intl.NumberFormat('en-IN', { style:'currency', currency:'INR', maximumFractionDigits:0 }).format(ctx.raw) }
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(26,23,20,0.05)', drawBorder: false },
                    ticks: { color: '#9e9690', font: { size: 11 } }
                },
                y: {
                    grid: { color: 'rgba(26,23,20,0.05)', drawBorder: false },
                    ticks: {
                        color: '#9e9690', font: { size: 11 },
                        callback: v => v > 0 ? '₹' + (v >= 10000000 ? (v/10000000).toFixed(1)+'Cr' : v >= 100000 ? (v/100000).toFixed(1)+'L' : v) : ''
                    },
                    beginAtZero: true
                }
            }
        }
    });

    // ── 2. Donut Chart ──
    const dCtx = document.getElementById('anTypeChart').getContext('2d');
    const totalAmt = typeValues.reduce((a,b)=>a+b,0);

    new Chart(dCtx, {
        type: 'doughnut',
        data: {
            labels: typeLabels,
            datasets: [{
                data: typeValues,
                backgroundColor: palette,
                borderColor: '#ffffff',
                borderWidth: 3,
                hoverOffset: 5
            }]
        },
        options: {
            responsive: false, cutout: '70%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1a1714',
                    titleColor: '#9e9690',
                    bodyColor: '#e8e3db',
                    borderColor: 'rgba(232,227,219,0.12)',
                    borderWidth: 1,
                    padding: 10,
                    callbacks: {
                        label: ctx => '  ' + new Intl.NumberFormat('en-IN', { style:'currency', currency:'INR', maximumFractionDigits:0 }).format(ctx.raw) +
                                      '  (' + Math.round(ctx.raw/totalAmt*100) + '%)'
                    }
                }
            }
        }
    });

    // Legend
    const legEl = document.getElementById('anTypeLegend');
    typeLabels.forEach((label, i) => {
        const pct = totalAmt > 0 ? Math.round(typeValues[i] / totalAmt * 100) : 0;
        legEl.innerHTML += `<div class="cl-item">
            <div class="cl-dot" style="background:${palette[i]}"></div>
            <span class="cl-name">${label}</span>
            <span class="cl-pct">${pct}%</span>
        </div>`;
    });

    // ── 3. Project Bars ──
    const maxProj = Math.max(...projValues);
    const gradArr = [
        'linear-gradient(90deg,#b5622a,#d48a60)',
        'linear-gradient(90deg,#8b5cf6,#a78cf8)',
        'linear-gradient(90deg,#10b981,#5dd4aa)',
        'linear-gradient(90deg,#f59e0b,#f8c55c)',
        'linear-gradient(90deg,#3b82f6,#7ab3fa)',
    ];
    const barEl = document.getElementById('anProjBars');

    projLabels.forEach((label, i) => {
        const pct = maxProj > 0 ? Math.round(projValues[i] / maxProj * 100) : 0;
        const fmt = new Intl.NumberFormat('en-IN', { style:'currency', currency:'INR', maximumFractionDigits:0 }).format(projValues[i]);
        barEl.innerHTML += `<div class="hbar-item">
            <div class="hbar-meta">
                <span class="hbar-name">${label}</span>
                <span class="hbar-amount">${fmt}</span>
            </div>
            <div class="hbar-track">
                <div class="hbar-fill" style="width:0%;background:${gradArr[i % gradArr.length]}" data-target="${pct}"></div>
            </div>
        </div>`;
    });

    // Animate bars
    requestAnimationFrame(() => setTimeout(() => {
        document.querySelectorAll('#anProjBars .hbar-fill').forEach(b => { b.style.width = b.dataset.target + '%'; });
    }, 350));

});
</script>

<?php endif; ?>

    <!-- Main Panel -->
    <div class="inv-panel">

        <!-- Toolbar -->
        <div class="panel-toolbar">
            <div class="toolbar-left">
                <div class="toolbar-icon"><i class="fas fa-coins"></i></div>
                <div>
                    <div class="toolbar-title">All Investments</div>
                    <span class="toolbar-subtitle">Track capital, loans, and partner contributions</span>
                </div>
            </div>
            <div class="toolbar-div"></div>

            <div class="toolbar-actions">
                <button class="btn-filter" onclick="toggleFilters()">
                    <i class="fas fa-filter"></i> Filters
                </button>
                <a href="?export=csv&search=<?= urlencode($filters['search']) ?>&project_id=<?= urlencode($filters['project_id']) ?>&investment_type=<?= urlencode($filters['investment_type']) ?>" class="btn-export">
                    <i class="fas fa-file-csv"></i> Export CSV
                </a>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section <?= ($filters['search'] || $filters['project_id'] || $filters['investment_type']) ? 'show' : '' ?>" id="filterSection">
            <form method="GET" class="filter-form">
                <input type="text" name="search" class="f-input" placeholder="Search investor..." value="<?= htmlspecialchars($filters['search']) ?>">
                
                <select name="project_id" class="f-select">
                    <option value="">All Projects</option>
                    <?php foreach ($projects as $proj): ?>
                        <option value="<?= $proj['id'] ?>" <?= $filters['project_id'] == $proj['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($proj['project_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="investment_type" class="f-select">
                    <option value="">All Types</option>
                    <option value="loan" <?= $filters['investment_type'] === 'loan' ? 'selected' : '' ?>>Loan</option>
                    <option value="partner" <?= $filters['investment_type'] === 'partner' ? 'selected' : '' ?>>Partner</option>
                    <option value="personal" <?= $filters['investment_type'] === 'personal' ? 'selected' : '' ?>>Personal</option>
                    <option value="other" <?= $filters['investment_type'] === 'other' ? 'selected' : '' ?>>Other</option>
                </select>
                
                <button type="submit" class="btn-go"><i class="fas fa-search"></i> Apply</button>
                
                <?php if ($filters['search'] || $filters['project_id'] || $filters['investment_type']): ?>
                    <a href="index.php" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Table -->
        <div style="overflow-x:auto">
            <table class="inv-table">
                <thead>
                    <tr>
                        <th class="th-c">Date</th>
                        <th>Project</th>
                        <th class="th-c">Investor</th>
                        <th class="th-c">Type</th>
                        <th class="th-r">Amount</th>
                        <th class="th-r">Returned</th>
                        <th class="th-r">Balance</th>
                        <th class="th-r">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($investments)): ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <h4>No investments found</h4>
                                    <p>Start recording your capital investments and loans.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: 
                        foreach ($investments as $inv): 
                            $color = ColorHelper::getProjectColor($inv['project_id']);
                            $initial = ColorHelper::getInitial($inv['project_name']);
                    ?>
                    <tr>
                        <td class="td-c"><span style="font-weight:600;color:var(--ink-soft);font-size:0.82rem"><?= formatDate($inv['investment_date']) ?></span></td>
                        <td>
                            <?= renderProjectBadge($inv['project_name'], $inv['project_id']) ?>
                        </td>
                        <td class="td-c"><span style="font-weight:600;color:var(--ink)"><?= htmlspecialchars($inv['investor_name']) ?></span></td>
                        <td class="td-c">
                            <span class="pill <?= $inv['investment_type'] ?>"><?= ucfirst($inv['investment_type']) ?></span>
                            <?php if ($inv['is_equity']): ?>
                                <div style="font-size:0.7rem; color:var(--ink-mute); margin-top:0.2rem; font-weight:600;">
                                    <?= number_format($inv['share_percentage'], 1) ?>% Ownership
                                </div>
                            <?php else: ?>
                                <div style="font-size:0.7rem; color:var(--ink-mute); margin-top:0.2rem;">
                                    <?= number_format($inv['capital_mix_percentage'], 1) ?>% of Cap
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="td-r"><strong style="font-weight:700;color:var(--ink)"><?= formatCurrency($inv['amount']) ?></strong></td>
                        <td class="td-r"><span style="color:#059669"><?= formatCurrency($inv['total_returned']) ?></span></td>
                        <td class="td-r"><strong style="font-weight:700;color:<?= $inv['balance'] > 0 ? '#dc2626' : '#059669' ?>"><?= formatCurrency($inv['balance']) ?></strong></td>
                        <td class="td-r">
                            <div class="act-group">
                                <a href="returns.php?id=<?= $inv['id'] ?>" class="act-btn" title="Manage Returns" style="color:var(--accent);">
                                    <i class="fas fa-hand-holding-usd"></i>
                                </a>
                                <button class="act-btn" onclick="viewInvestment(<?= htmlspecialchars(json_encode($inv)) ?>)" title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="act-btn" onclick="editInvestment(<?= htmlspecialchars(json_encode($inv)) ?>)" title="Edit">
                                    <i class="fas fa-pencil-alt"></i>
                                </button>
                                <button class="act-btn del" onclick="openDeleteModal(<?= $inv['id'] ?>)" title="Delete">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

    </div>

</div>

<!-- View Modal -->
<div class="inv-modal-backdrop" id="viewModal">
    <div class="inv-modal" style="max-width:480px">
        <div class="modal-head">
            <div>
                <h3><i class="fas fa-file-invoice-dollar"></i> Investment Details</h3>
                <p>View complete investment record</p>
            </div>
            <button type="button" class="modal-close" onclick="closeModal('viewModal')">×</button>
        </div>
        <div class="modal-body">
            <div class="highlight-box">
                <div class="lbl">Invested Amount</div>
                <div class="val" id="view_amount">₹ 0.00</div>
            </div>

            <div class="detail-row">
                <span class="lbl">Project</span>
                <span class="val" id="view_project"></span>
            </div>
            <div class="detail-row">
                <span class="lbl">Investor</span>
                <span class="val" id="view_investor"></span>
            </div>
            <div class="detail-row">
                <span class="lbl">Type</span>
                <span class="val" id="view_type"></span>
            </div>
            <div class="detail-row">
                <span class="lbl">Date</span>
                <span class="val" id="view_date"></span>
            </div>

            <div style="margin-top:1.25rem">
                <span class="lbl" style="display:block;margin-bottom:0.5rem">Remarks</span>
                <div class="remarks-box" id="view_remarks"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('viewModal')">Close</button>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="inv-modal-backdrop" id="addModal">
    <div class="inv-modal">
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">

            <div class="modal-head">
                <div>
                    <h3><i class="fas fa-hand-holding-usd"></i> New Investment</h3>
                    <p>Record initial capital or loan</p>
                </div>
                <button type="button" class="modal-close" onclick="closeModal('addModal')">×</button>
            </div>

            <div class="modal-body">
                <div class="field">
                    <label>Project *</label>
                    <select name="project_id" required>
                        <option value="">Select Project</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label>Investor Name *</label>
                        <input type="text" name="investor_name" required placeholder="e.g. HDFC Bank">
                    </div>
                    <div class="field">
                        <label>Type *</label>
                        <select name="investment_type" required>
                            <option value="other">Other</option>
                            <option value="loan">Loan</option>
                            <option value="partner">Partner</option>
                            <option value="personal">Personal</option>
                        </select>
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label>Amount (₹) *</label>
                        <input type="number" name="amount" required step="0.01" placeholder="0.00">
                    </div>
                    <div class="field">
                        <label>Date *</label>
                        <input type="date" name="investment_date" required value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="field">
                    <label>Remarks</label>
                    <textarea name="remarks" rows="2" placeholder="Optional notes..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Record</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="inv-modal-backdrop" id="editModal">
    <div class="inv-modal">
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">

            <div class="modal-head">
                <div>
                    <h3><i class="fas fa-edit"></i> Edit Investment</h3>
                    <p>Update investment record</p>
                </div>
                <button type="button" class="modal-close" onclick="closeModal('editModal')">×</button>
            </div>

            <div class="modal-body">
                <div class="field">
                    <label>Project *</label>
                    <select name="project_id" id="edit_project_id" required>
                        <option value="">Select Project</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['project_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label>Investor Name *</label>
                        <input type="text" name="investor_name" id="edit_investor_name" required>
                    </div>
                    <div class="field">
                        <label>Type *</label>
                        <select name="investment_type" id="edit_investment_type" required>
                            <option value="other">Other</option>
                            <option value="loan">Loan</option>
                            <option value="partner">Partner</option>
                            <option value="personal">Personal</option>
                        </select>
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label>Amount (₹) *</label>
                        <input type="number" name="amount" id="edit_amount" required step="0.01">
                    </div>
                    <div class="field">
                        <label>Date *</label>
                        <input type="date" name="investment_date" id="edit_investment_date" required>
                    </div>
                </div>

                <div class="field">
                    <label>Remarks</label>
                    <textarea name="remarks" id="edit_remarks" rows="2"></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Update</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Modal -->
<div class="inv-modal-backdrop" id="deleteModal">
    <div class="inv-modal" style="max-width:420px">
        <div style="padding:2rem;text-align:center">
            <div style="width:64px;height:64px;background:#fef2f2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem">
                <i class="fas fa-trash-alt" style="font-size:1.5rem;color:#ef4444"></i>
            </div>
            <h4 style="margin:0 0 0.75rem;color:var(--ink);font-weight:700">Delete Investment?</h4>
            <p style="color:var(--ink-soft);margin:0 0 1.5rem;line-height:1.6;font-size:0.875rem">
                Are you sure you want to delete this record?<br>
                <span style="color:#ef4444;font-weight:600">This action cannot be undone.</span>
            </p>

            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">
                
                <div style="display:flex;gap:0.75rem;justify-content:center">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Yes, Delete It</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleFilters() { document.getElementById('filterSection').classList.toggle('show'); }
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.inv-modal-backdrop').forEach(bd => {
    bd.addEventListener('click', e => { if (e.target === bd) bd.classList.remove('open'); });
});

function formatMoney(amount) {
    return '₹ ' + parseFloat(amount).toLocaleString('en-IN', {
        maximumFractionDigits: 2,
        minimumFractionDigits: 2
    });
}

function viewInvestment(inv) {
    document.getElementById('view_amount').innerText = formatMoney(inv.amount);
    document.getElementById('view_project').innerText = inv.project_name;
    document.getElementById('view_investor').innerText = inv.investor_name;
    document.getElementById('view_type').innerText = inv.investment_type.charAt(0).toUpperCase() + inv.investment_type.slice(1);
    document.getElementById('view_date').innerText = new Date(inv.investment_date).toLocaleDateString('en-GB', {
        day: '2-digit', month: 'short', year: 'numeric'
    });
    document.getElementById('view_remarks').innerText = inv.remarks || 'No remarks provided.';
    openModal('viewModal');
}

function editInvestment(inv) {
    document.getElementById('edit_id').value = inv.id;
    document.getElementById('edit_project_id').value = inv.project_id;
    document.getElementById('edit_investor_name').value = inv.investor_name;
    document.getElementById('edit_investment_type').value = inv.investment_type;
    document.getElementById('edit_amount').value = inv.amount;
    document.getElementById('edit_investment_date').value = inv.investment_date;
    document.getElementById('edit_remarks').value = inv.remarks || '';
    openModal('editModal');
}

function openDeleteModal(id) {
    document.getElementById('delete_id').value = id;
    openModal('deleteModal');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>