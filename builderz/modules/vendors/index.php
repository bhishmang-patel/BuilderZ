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

$masterService = new MasterService();
$db = Database::getInstance();
$page_title = 'Vendors';
$current_page = 'vendors';

$current_page = 'vendors';

$filters = [
    'type'        => 'vendor',
    'search'      => $_GET['search']      ?? '',
    'vendor_type' => $_GET['vendor_type'] ?? '',
    'city'        => $_GET['city']        ?? '',
    'gst_status'  => $_GET['gst_status']  ?? '',
    'status'      => $_GET['status']      ?? '',
    'material'    => $_GET['material']    ?? ''
];

$parties = [];
$fatalError = null;
try {
    $parties = $masterService->getAllParties($filters);
} catch (Exception $e) {
    $fatalError = (strpos($e->getMessage(), 'Column not found') !== false || strpos($e->getMessage(), 'Unknown column') !== false)
        ? "Database columns missing! Please run: <code>migrations/add_vendor_columns.sql</code>"
        : "Error loading vendors: " . $e->getMessage();
}

$totalVendors = count($parties);

$stmt = $db->query("SELECT SUM(amount - paid_amount) as total_pending FROM bills WHERE status != 'rejected' AND payment_status != 'paid'");
$totalPending = $stmt->fetch()['total_pending'] ?? 0;

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
    --accent:    #2a58b5;
    --accent-lt: #eff4ff;
    --accent-md: #c7d9f9;
    --accent-bg: #f0f5ff;
    --accent-dk: #1e429f;
}

body { background: var(--cream); font-family: 'DM Sans', sans-serif; color: var(--ink); }

/* ── Wrapper ─────────────────────────── */
.page-wrap { max-width: 1260px; margin: 2.5rem auto; padding: 0 1.5rem 4rem; }

/* ── Animations ──────────────────────── */
@keyframes fadeDown {
    from { opacity: 0; transform: translateY(-14px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes rowSlide {
    from { opacity: 0; transform: translateX(-10px); }
    to   { opacity: 1; transform: translateX(0); }
}

.page-header {
    display: flex; align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 2rem; padding-bottom: 1.5rem;
    border-bottom: 1.5px solid var(--border);
    gap: 1rem; flex-wrap: wrap;
    opacity: 0;
    animation: fadeDown 0.45s cubic-bezier(0.22,1,0.36,1) 0.05s forwards;
}

/* ── Stat cards ──────────────────────── */
.stat-row {
    display: flex; gap: 1rem; flex-wrap: wrap;
    margin-bottom: 1.5rem;
    opacity: 0;
    animation: fadeUp 0.42s cubic-bezier(0.22,1,0.36,1) 0.08s forwards;
}

.stat-card {
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: 12px; padding: 1rem 1.25rem;
    display: flex; align-items: center; gap: 0.9rem;
    min-width: 200px; flex: 1;
    box-shadow: 0 1px 4px rgba(26,23,20,0.04);
    transition: transform 0.18s, box-shadow 0.18s;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(26,23,20,0.08); }

.stat-ic {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; flex-shrink: 0;
}
.stat-ic.blue { background: var(--accent-lt); color: var(--accent); }
.stat-ic.red  { background: #fee2e2; color: #dc2626; }

.stat-lbl {
    font-size: 0.72rem; font-weight: 600;
    letter-spacing: 0.05em; text-transform: uppercase;
    color: var(--ink-mute); margin-bottom: 2px;
}
.stat-val {
    font-family: 'Fraunces', serif;
    font-size: 1.5rem; font-weight: 700; color: var(--ink); line-height: 1; text-align: center;
}
.stat-val.red { color: #dc2626; }

/* ── Filter + Main cards ─────────────── */
.ch-card {
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: 14px; overflow: hidden;
    margin-bottom: 1.25rem;
    box-shadow: 0 1px 4px rgba(26,23,20,0.04);
    opacity: 0;
    animation: fadeUp 0.42s cubic-bezier(0.22,1,0.36,1) forwards;
}
.ch-card.delay-1 { animation-delay: 0.12s; }
.ch-card.delay-2 { animation-delay: 0.20s; }

.card-head {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 1rem 1.5rem;
    border-bottom: 1.5px solid var(--border-lt);
    background: #fafbff;
}
.card-icon {
    width: 28px; height: 28px; border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.72rem; flex-shrink: 0;
}
.card-icon.blue   { background: var(--accent-lt); color: var(--accent); }
.card-icon.purple { background: #ede9fe; color: #7c3aed; }
.card-head h2 {
    font-family: 'Fraunces', serif;
    font-size: 0.95rem; font-weight: 600; color: var(--ink); margin: 0;
}
.count-tag {
    margin-left: auto;
    font-size: 0.62rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
    color: var(--ink-mute); background: var(--cream); border: 1px solid var(--border);
    padding: 0.18rem 0.6rem; border-radius: 20px;
}

/* ── Filter body ─────────────────────── */
.filter-body { padding: 1rem 1.5rem; }
.filter-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr 1fr 2fr auto;
    gap: 0.65rem; align-items: center;
}
@media (max-width: 1000px) { .filter-grid { grid-template-columns: 1fr 1fr; } }
@media (max-width: 600px)  { .filter-grid { grid-template-columns: 1fr; } }

.f-ctrl {
    height: 36px; padding: 0 0.75rem;
    border: 1.5px solid var(--border); border-radius: 7px;
    font-family: 'DM Sans', sans-serif; font-size: 0.82rem;
    color: var(--ink); background: #fdfcfa; outline: none;
    transition: border-color 0.18s, box-shadow 0.18s;
    -webkit-appearance: none; appearance: none; width: 100%;
}
.f-ctrl:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(42,88,181,0.1); }
select.f-ctrl {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 0.65rem center; padding-right: 2rem;
}
.search-wrap { position: relative; }
.search-wrap .si { position: absolute; left: 0.65rem; top: 50%; transform: translateY(-50%); color: var(--ink-mute); font-size: 0.68rem; pointer-events: none; }
.search-wrap .f-ctrl { padding-left: 1.9rem; }

.btn-apply {
    height: 36px; padding: 0 1.1rem;
    background: var(--accent); color: white; border: none; border-radius: 7px;
    font-family: 'DM Sans', sans-serif; font-size: 0.8rem; font-weight: 700;
    cursor: pointer; display: flex; align-items: center; gap: 0.35rem;
    transition: background 0.18s; white-space: nowrap;
}
.btn-apply:hover { background: var(--accent-dk); }
.btn-reset {
    height: 36px; padding: 0 0.9rem;
    background: white; color: var(--ink-soft); border: 1.5px solid var(--border); border-radius: 7px;
    font-family: 'DM Sans', sans-serif; font-size: 0.8rem; font-weight: 500;
    text-decoration: none; display: flex; align-items: center; gap: 0.35rem; transition: all 0.18s;
}
.btn-reset:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); text-decoration: none; }

/* ── Page header buttons ─────────────── */
.eyebrow {
    font-size: 0.68rem; font-weight: 700;
    letter-spacing: 0.15em; text-transform: uppercase;
    color: var(--accent); margin-bottom: 0.3rem;
}
.page-header h1 {
    font-family: 'Fraunces', serif;
    font-size: 2rem; font-weight: 700; line-height: 1.1; color: var(--ink); margin: 0;
}
.page-header h1 em { color: var(--accent); font-style: italic; }

.hdr-right { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }

.btn-filter-toggle {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.55rem 1rem; font-size: 0.82rem; font-weight: 500;
    color: var(--ink-soft); border: 1.5px solid var(--border); border-radius: 6px;
    background: white; cursor: pointer; transition: all 0.18s ease; font-family: 'DM Sans', sans-serif;
}
.btn-filter-toggle:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }
.filter-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--accent); margin-left: 2px; }

.btn-add {
    display: inline-flex; align-items: center; gap: 0.45rem;
    padding: 0.62rem 1.4rem; background: var(--accent); color: white;
    border: none; border-radius: 8px; font-family: 'DM Sans', sans-serif;
    font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all 0.18s ease;
}
.btn-add:hover { background: var(--accent-dk); transform: translateY(-1px); box-shadow: 0 4px 14px rgba(42,88,181,0.32); }

/* ── Table ───────────────────────────── */
.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse;  table-layout: auto; font-size: 0.875rem; }
.data-table thead tr { background: #f0f4fb; border-bottom: 1.5px solid var(--border); }
.data-table thead th {
    padding: 0.75rem 1rem; text-align: left;
    font-size: 0.67rem; font-weight: 700;
    letter-spacing: 0.1em; text-transform: uppercase;
    color: var(--ink-soft); white-space: nowrap;
}
.data-table thead th,
.data-table td {
    padding: 0.8rem 1rem;
}
.data-table thead th.center { text-align: center; }
.data-table thead th.right  { text-align: right; }
.data-table tbody tr { border-bottom: 1px solid var(--border-lt); transition: background 0.12s; }
.data-table tbody tr:last-child { border-bottom: none; }
.data-table tbody tr:hover { background: #f5f8ff; }
.data-table td { padding: 0.85rem 1rem; vertical-align: middle; color: var(--ink-soft); }
.data-table td.center { text-align: center; }
.data-table td.right  { text-align: right; }
.data-table tbody tr.row-anim { opacity: 0; transform: translateX(-10px); animation: rowSlide 0.32s cubic-bezier(0.22,1,0.36,1) forwards; }

/* ── Vendor name cell ────────────────── */
.vname-cell { display: flex; align-items: center; gap: 0.7rem; }
.av-sq { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 0.72rem; font-weight: 800; color: white; flex-shrink: 0; text-transform: uppercase; }
.vname { font-weight: 700; color: var(--ink); font-size: 0.875rem; }

/* ── Pill badges ─────────────────────── */
.pill {
    display: inline-block; font-size: 0.67rem; font-weight: 700;
    letter-spacing: 0.04em; padding: 0.22rem 0.65rem; border-radius: 20px;
}
.pill.gray    { background: var(--cream); color: var(--ink-soft); border: 1px solid var(--border); }
.pill.blue    { background: var(--accent-lt); color: var(--accent-dk); border: 1px solid var(--accent-md); }
.pill.green   { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
.pill.red     { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
.pill.orange  { background: #fff7ed; color: #9a3412; border: 1px solid #fed7aa; }
.pill.active  { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
.pill.inactive{ background: var(--cream); color: var(--ink-mute); border: 1px solid var(--border); }

/* ── Outstanding amount ──────────────── */
.owed { font-family: 'Fraunces', serif; font-weight: 700; font-size: 0.9rem; }
.owed.red   { color: #dc2626; }
.owed.green { color: #059669; }

/* ── GST cell ────────────────────────── */
.gst-cell { font-size: 0.78rem; }
.gst-status { font-weight: 700; color: var(--ink); text-transform: capitalize; }
.gst-num { font-family: monospace; font-size: 0.7rem; color: var(--ink-mute); margin-top: 1px; }

/* ── Action buttons ──────────────────── */
.act-wrap { display: flex; align-items: center; justify-content: center; gap: 0.28rem; }
.act-btn {
    width: 27px; height: 27px; border-radius: 5px; font-size: 0.68rem;
    border: 1.5px solid var(--border); background: white; color: var(--ink-mute);
    cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
    transition: all 0.15s;
}
.act-btn.view:hover  { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }
.act-btn.edit:hover  { border-color: #059669; color: #059669; background: #f0fdf4; }
.act-btn.del:hover   { border-color: #dc2626; color: #dc2626; background: #fff5f5; }
.act-btn.bills:hover { border-color: #0ea5e9; color: #0ea5e9; background: #f0faff; }

/* ── Empty state ─────────────────────── */
.empty-state { text-align: center; padding: 4rem 2rem; color: var(--ink-mute); }
.empty-state .ei { font-size: 2.8rem; opacity: 0.2; margin-bottom: 1rem; display: block; color: var(--accent); }
.empty-state .et { font-family: 'Fraunces', serif; font-size: 1.05rem; color: var(--ink-soft); margin-bottom: 0.4rem; }

/* ═══════════════════════════════════════
   MODALS
═══════════════════════════════════════ */
.modal-overlay {
    position: fixed; inset: 0;
    background: rgba(26,23,20,0.48);
    backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px);
    z-index: 9000;
    display: flex; align-items: center; justify-content: center;
    padding: 1rem;
    opacity: 0; pointer-events: none;
    transition: opacity 0.25s ease;
}
.modal-overlay.open { opacity: 1; pointer-events: all; }
.modal-overlay.z-top { z-index: 9500; }

.modal-box {
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: 16px;
    box-shadow: 0 24px 60px rgba(26,23,20,0.2);
    width: 100%; max-width: 640px;
    max-height: 92vh; display: flex; flex-direction: column; overflow: hidden;
    transform: scale(0.95) translateY(12px);
    transition: transform 0.32s cubic-bezier(0.22,1,0.36,1);
}
.modal-overlay.open .modal-box { transform: scale(1) translateY(0); }
.modal-box.sm  { max-width: 460px; }
.modal-box.lg  { max-width: 820px; }
.modal-box.xs  { max-width: 400px; }

.modal-head {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 1.2rem 1.5rem;
    border-bottom: 1.5px solid var(--border-lt);
    background: #fafbff; flex-shrink: 0;
}
.mh-icon {
    width: 34px; height: 34px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; flex-shrink: 0;
}
.mh-icon.blue   { background: var(--accent-lt); color: var(--accent); }
.mh-icon.green  { background: #d1fae5; color: #059669; }
.mh-icon.orange { background: #fdf8f3; color: #b5622a; border: 1px solid #e0c9b5; }
.mh-icon.red    { background: #fee2e2; color: #dc2626; }
.mh-icon.sky    { background: #f0faff; color: #0ea5e9; }

.modal-title    { font-family: 'Fraunces', serif; font-size: 1rem; font-weight: 700; color: var(--ink); margin: 0; flex: 1; }
.modal-subtitle { font-size: 0.7rem; color: var(--ink-mute); margin-top: 1px; }

.modal-close {
    width: 26px; height: 26px; border-radius: 5px;
    border: 1.5px solid var(--border); background: white; color: var(--ink-soft);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 0.7rem; transition: all 0.16s; flex-shrink: 0;
}
.modal-close:hover { border-color: #dc2626; color: #dc2626; background: #fff5f5; }

.modal-body { padding: 1.5rem; overflow-y: auto; flex: 1; }
.modal-foot {
    padding: 1rem 1.5rem; border-top: 1.5px solid var(--border-lt);
    background: #fafbff; display: flex; justify-content: flex-end; gap: 0.65rem; flex-shrink: 0;
}

/* form elements inside modals */
.fsec {
    font-size: 0.63rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase;
    color: var(--ink-mute); margin: 1.2rem 0 0.75rem;
    padding-bottom: 0.4rem; border-bottom: 1px solid var(--border-lt);
    display: flex; align-items: center; gap: 0.4rem;
}
.fsec:first-child { margin-top: 0; }

.fg  { display: flex; flex-direction: column; gap: 0.35rem; margin-bottom: 0.85rem; }
.fg:last-child { margin-bottom: 0; }
.fg label { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--ink-mute); }
.fg label .req { color: #dc2626; margin-left: 2px; }

.fg input, .fg select, .fg textarea {
    width: 100%; height: 38px; padding: 0 0.8rem;
    border: 1.5px solid var(--border); border-radius: 7px;
    font-family: 'DM Sans', sans-serif; font-size: 0.875rem; color: var(--ink);
    background: #fdfcfa; outline: none;
    transition: border-color 0.18s ease, box-shadow 0.18s ease;
}
.fg select {
    -webkit-appearance: none; appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 0.8rem center; padding-right: 2.2rem;
}
.fg textarea { height: auto; padding: 0.65rem 0.8rem; resize: vertical; min-height: 68px; }
.fg input:focus, .fg select:focus, .fg textarea:focus {
    border-color: var(--accent); box-shadow: 0 0 0 3px rgba(42,88,181,0.1); background: white;
}
.fg input:disabled { background: var(--cream); color: var(--ink-mute); cursor: not-allowed; }

.fgrid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; }
.fspan  { grid-column: 1 / -1; }
@media (max-width: 600px) { .fgrid2 { grid-template-columns: 1fr; } }

/* challan link box */
.challan-link-box {
    padding: 0.9rem 1rem; margin-bottom: 1.1rem;
    background: var(--accent-lt); border: 1.5px solid var(--accent-md);
    border-radius: 9px;
}
.challan-link-box .clb-label { font-size: 0.67rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--accent); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.35rem; }
.challan-link-box .clb-hint  { font-size: 0.68rem; color: var(--accent); opacity: 0.75; margin-top: 0.3rem; }

/* autocomplete */
.ac-wrap { position: relative; }
.ac-list {
    position: absolute; top: calc(100% + 4px); left: 0; right: 0;
    background: white; border: 1.5px solid var(--border);
    border-radius: 8px; list-style: none; padding: 0.3rem; margin: 0;
    z-index: 400; display: none; max-height: 200px; overflow-y: auto;
    box-shadow: 0 10px 28px rgba(26,23,20,0.1);
}
.ac-list.show { display: block; }
.ac-item {
    padding: 0.55rem 0.75rem; cursor: pointer; border-radius: 5px;
    font-size: 0.82rem; color: var(--ink); transition: background 0.1s;
}
.ac-item:hover { background: var(--accent-lt); color: var(--accent); }

/* view info rows */
.vinfo-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 0.7rem 0; border-bottom: 1px solid var(--border-lt); gap: 1rem;
}
.vinfo-row:last-child { border-bottom: none; }
.vinfo-lbl { font-size: 0.78rem; color: var(--ink-soft); }
.vinfo-val { font-size: 0.875rem; font-weight: 600; color: var(--ink); text-align: right; }

/* delete confirm */
.del-confirm { text-align: center; padding: 0.5rem 0; }
.del-icon { width: 56px; height: 56px; background: #fee2e2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 1.4rem; color: #dc2626; }
.del-title { font-family: 'Fraunces', serif; font-size: 1.1rem; font-weight: 700; color: var(--ink); margin-bottom: 0.4rem; }
.del-sub   { font-size: 0.875rem; color: var(--ink-soft); line-height: 1.55; }

/* modal buttons */
.btn-ghost {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.58rem 1.15rem;
    border: 1.5px solid var(--border); background: white; color: var(--ink-soft);
    border-radius: 7px; font-family: 'DM Sans', sans-serif;
    font-size: 0.875rem; font-weight: 500; cursor: pointer; transition: all 0.18s;
}
.btn-ghost:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }

.btn-submit {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.58rem 1.4rem;
    background: var(--ink); color: white; border: 1.5px solid var(--ink);
    border-radius: 7px; font-family: 'DM Sans', sans-serif;
    font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all 0.18s;
}
.btn-submit:hover { background: var(--accent); border-color: var(--accent); transform: translateY(-1px); box-shadow: 0 4px 14px rgba(42,88,181,0.3); }
.btn-submit:active { transform: translateY(0); }
.btn-submit.danger { background: #dc2626; border-color: #dc2626; }
.btn-submit.danger:hover { background: #b91c1c; border-color: #b91c1c; box-shadow: 0 4px 14px rgba(220,38,38,0.3); }

/* bills table inside modal */
.bills-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
.bills-table th { padding: 0.6rem 0.75rem; font-size: 0.62rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--ink-mute); background: var(--cream); text-align: left; border-bottom: 1.5px solid var(--border); }
.bills-table td { padding: 0.7rem 0.75rem; vertical-align: middle; border-bottom: 1px solid var(--border-lt); color: var(--ink-soft); }
.bills-table tr:last-child td { border-bottom: none; }
.bills-table tr:hover td { background: #f5f8ff; }

.empty-bills { text-align: center; padding: 2.5rem; border: 2px dashed var(--border); border-radius: 10px; color: var(--ink-mute); }
.empty-bills i { font-size: 2rem; margin-bottom: 0.5rem; display: block; }

/* challan preview */
.challan-preview-box {
    margin-top: 0.85rem;
    padding: 0.75rem 1rem;
    background: var(--accent-lt); border: 1.5px solid var(--accent-md);
    border-radius: 8px; font-size: 0.8rem; color: var(--accent-dk);
    display: none;
}

/* autofill confirm */
.af-icon { width: 52px; height: 52px; background: var(--accent-lt); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 1.25rem; color: var(--accent); }
</style>

<div class="page-wrap">

    <!-- ── Page Header ──────────────── -->
    <div class="page-header">
        <div>
            <div class="eyebrow">Masters</div>
            <h1>Vendor <em>Directory</em></h1>
        </div>
        <div class="hdr-right">
            <button class="btn-filter-toggle" id="toggleFilter">
                <i class="fas fa-sliders-h"></i> Filters
                <?php if ($filters['search'] || $filters['vendor_type'] || $filters['city'] || $filters['gst_status'] || $filters['status'] || $filters['material']): ?>
                    <span class="filter-dot"></span>
                <?php endif; ?>
            </button>
            <a href="create_bill.php" class="btn-add" style="background:var(--ink);text-decoration:none;">
                <i class="fas fa-file-invoice"></i> New Bill
            </a>
        </div>
    </div>

    <!-- ── Stat Cards ───────────────── -->
    <div class="stat-row">
        <div class="stat-card">
            <div class="stat-ic blue"><i class="fas fa-truck"></i></div>
            <div>
                <div class="stat-lbl">Total Vendors</div>
                <div class="stat-val"><?= $totalVendors ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-ic red"><i class="fas fa-file-invoice-dollar"></i></div>
            <div>
                <div class="stat-lbl">Total Payables</div>
                <div class="stat-val red"><?= formatCurrency($totalPending) ?></div>
            </div>
        </div>
    </div>

    <?php if ($fatalError): ?>
        <div style="background:#fee2e2;border:1.5px solid #fecaca;color:#991b1b;padding:1rem 1.25rem;border-radius:10px;margin-bottom:1.25rem;font-size:0.875rem;display:flex;align-items:center;gap:0.75rem;">
            <i class="fas fa-exclamation-triangle" style="font-size:1.1rem;flex-shrink:0;"></i>
            <span><strong>System Error:</strong> <?= $fatalError ?></span>
        </div>
    <?php endif; ?>

    <!-- ── Filter Card ──────────────── -->
    <div class="ch-card delay-1" id="filterCard" style="display:<?= ($filters['search'] || $filters['vendor_type'] || $filters['city'] || $filters['gst_status'] || $filters['status'] || $filters['material']) ? 'block' : 'none' ?>">
        <div class="card-head">
            <div class="card-icon purple"><i class="fas fa-filter"></i></div>
            <h2>Filter Vendors</h2>
        </div>
        <div class="filter-body">
            <form method="GET">
                <div class="filter-grid">
                    <div class="search-wrap">
                        <i class="fas fa-search si"></i>
                        <input type="text" name="search" class="f-ctrl" placeholder="Search name, mobile…" value="<?= htmlspecialchars($filters['search']) ?>">
                    </div>
                    <select name="vendor_type" class="f-ctrl">
                        <option value="">All Types</option>
                        <option value="supplier"         <?= $filters['vendor_type'] == 'supplier'         ? 'selected' : '' ?>>Supplier</option>
                        <option value="contractor"       <?= $filters['vendor_type'] == 'contractor'       ? 'selected' : '' ?>>Contractor</option>
                        <option value="service_provider" <?= $filters['vendor_type'] == 'service_provider' ? 'selected' : '' ?>>Service Provider</option>
                    </select>
                    <input type="text" name="city" class="f-ctrl" placeholder="City" value="<?= htmlspecialchars($filters['city']) ?>">
                    <select name="gst_status" class="f-ctrl">
                        <option value="">GST Status</option>
                        <option value="registered"   <?= $filters['gst_status'] == 'registered'   ? 'selected' : '' ?>>Registered</option>
                        <option value="unregistered" <?= $filters['gst_status'] == 'unregistered' ? 'selected' : '' ?>>Unregistered</option>
                        <option value="composition"  <?= $filters['gst_status'] == 'composition'  ? 'selected' : '' ?>>Composition</option>
                    </select>
                    <select name="status" class="f-ctrl">
                        <option value="">Status</option>
                        <option value="active"   <?= $filters['status'] == 'active'   ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $filters['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                    <div class="search-wrap">
                        <i class="fas fa-box-open si"></i>
                        <input type="text" name="material" class="f-ctrl" placeholder="Filter by material…" value="<?= htmlspecialchars($filters['material']) ?>">
                    </div>
                    <div style="display:flex;gap:0.5rem;">
                        <button type="submit" class="btn-apply"><i class="fas fa-filter"></i> Apply</button>
                        <a href="index.php" class="btn-reset"><i class="fas fa-times"></i></a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Main Card ────────────────── -->
    <div class="ch-card delay-2">
        <div class="card-head">
            <div class="card-icon blue"><i class="fas fa-truck"></i></div>
            <h2>All Vendors</h2>
            <span class="count-tag"><?= $totalVendors ?> vendor<?= $totalVendors !== 1 ? 's' : '' ?></span>
        </div>
        <div class="table-wrap">
            <?php if (empty($parties)): ?>
                <div class="empty-state">
                    <span class="ei"><i class="fas fa-truck"></i></span>
                    <div class="et">No Vendors Found</div>
                    <div>Add your first vendor to get started.</div>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="left">Vendor</th>
                            <th class="center">Type</th>
                            <th class="center">Location</th>
                            <th class="center">GST</th>
                            <th class="center">Material</th>
                            <th class="center">Qty</th>
                            <th class="center">Bills</th>
                            <th class="center">Total Billed</th>
                            <th class="center">Outstanding</th>
                            <th class="center">Status</th>
                            <th class="center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($parties as $i => $party):
                            $color   = ColorHelper::getCustomerColor($party['name']);
                            $initial = ColorHelper::getInitial($party['name']);
                            $delay   = 40 + $i * 38;
                        ?>
                        <tr class="row-anim" style="animation-delay:<?= $delay ?>ms">
                            <td class="center">
                                <div class="vname-cell" style="display:flex; flex-direction:column; align-items:flex-start; gap:0.2rem;">
                                    <span class="vname"><?= htmlspecialchars($party['name'] ?? '') ?></span>
                                    <?php 
                                    if (!empty($party['vendor_projects'])) {
                                        $projList = explode('||', $party['vendor_projects']);
                                        echo '<div style="display:flex; flex-wrap:wrap; gap:4px;">';
                                        foreach ($projList as $pStr) {
                                            $parts = explode(':', $pStr);
                                            if (count($parts) >= 2) {
                                                echo renderProjectBadge($parts[1], $parts[0]);
                                            }
                                        }
                                        echo '</div>';
                                    }
                                    ?>
                                </div>
                            </td>
                            <td class="center">
                                <?php if ($party['vendor_type']): ?>
                                    <span class="pill gray" style="text-transform:capitalize;">
                                        <?= str_replace('_', ' ', $party['vendor_type']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:var(--ink-mute);">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="center" style="font-size:0.82rem;font-weight:500;color:var(--ink-soft);">
                                <?= htmlspecialchars($party['city'] ?? '—') ?>
                            </td>
                            <td class="center" style="text-align:left;">
                                <div class="gst-cell">
                                    <div class="gst-status"><?= htmlspecialchars($party['gst_status'] ?? 'Unregistered') ?></div>
                                    <?php if (!empty($party['gst_number'])): ?>
                                        <div class="gst-num"><?= htmlspecialchars($party['gst_number']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="center" style="font-size:0.78rem;color:var(--ink-soft);line-height:1.4;">
                                <?= !empty($party['supplied_materials']) ? htmlspecialchars($party['supplied_materials']) : '<span style="color:var(--ink-mute)">—</span>' ?>
                            </td>
                            <td class="center" style="font-size:0.82rem;color:var(--ink-soft);">
                                <?= !empty($party['total_quantity']) ? number_format($party['total_quantity'], 2) : '—' ?>
                            </td>
                            <td class="center">
                                <span class="pill blue"><?= $party['bill_count'] ?? 0 ?> Bills</span>
                            </td>
                            <td class="center" style="font-weight:600;color:var(--ink);white-space:nowrap;">
                                <?= formatCurrency($party['total_billed_amount'] ?? 0) ?>
                            </td>
                            <td class="center" style="padding-right:1.25rem; white-space:nowrap;">
                                <?php if (($party['outstanding_balance'] ?? 0) > 0): ?>
                                    <span class="owed red"><?= formatCurrency($party['outstanding_balance']) ?></span>
                                <?php else: ?>
                                    <span class="owed green"><?= formatCurrency(0) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="center">
                                <span class="pill <?= ($party['status'] ?? 'active') === 'active' ? 'active' : 'inactive' ?>">
                                    <?= ucfirst($party['status'] ?? 'active') ?>
                                </span>
                            </td>
                            <td class="center">
                                <div class="act-wrap">
                                    <button class="act-btn bills" onclick="openViewBillsModal(<?= $party['id'] ?>, '<?= htmlspecialchars(addslashes($party['name'])) ?>')" title="Bills">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /page-wrap -->

<!-- ══════════════════════════════════════
     VIEW BILLS MODAL
══════════════════════════════════════ -->
<div id="viewBillsModal" class="modal-overlay">
    <div class="modal-box lg">
        <div class="modal-head">
            <div class="mh-icon sky"><i class="fas fa-file-invoice-dollar"></i></div>
            <div style="flex:1">
                <div class="modal-title">Vendor Bills</div>
                <div class="modal-subtitle" id="vb_vendor_name">—</div>
            </div>
            <button class="modal-close" onclick="closeModal('viewBillsModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" style="background:#fafbff;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
                <div style="display:flex;gap:1.5rem;">
                    <div class="stat-pill">
                        <span class="lbl">Total</span>
                        <span class="val" id="vb_total">—</span>
                    </div>
                    <div class="stat-pill">
                        <span class="lbl">Paid</span>
                        <span class="val green" id="vb_paid">—</span>
                    </div>
                    <div class="stat-pill">
                        <span class="lbl">Pending</span>
                        <span class="val orange" id="vb_pending">—</span>
                    </div>
                </div>
            </div>
            
            <style>
                .stat-pill { background:white; border:1px solid var(--border); padding:0.5rem 1rem; border-radius:8px; display:flex; flex-direction:column; min-width:100px; }
                .stat-pill .lbl { font-size:0.7rem; text-transform:uppercase; font-weight:700; color:var(--ink-mute); margin-bottom:2px; }
                .stat-pill .val { font-family:'Fraunces',serif; font-weight:700; font-size:1.1rem; color:var(--ink); }
                .stat-pill .val.green { color:#059669; }
                .stat-pill .val.orange { color:#d97706; }
            </style>

            <div id="vb_list_container">
                <div style="text-align:center;padding:2.5rem;color:var(--ink-mute);">
                    <i class="fas fa-spinner fa-spin"></i> Loading bills…
                </div>
            </div>
        </div>
    </div>
</div>

<script>
/* ── Modal helpers ──────────────────────── */
function openModal(id) {
    document.getElementById(id).classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
    document.body.style.overflow = '';
}
document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => { if (e.target === o) closeModal(o.id); });
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape')
        document.querySelectorAll('.modal-overlay.open').forEach(o => closeModal(o.id));
});

/* ── Filter toggle ──────────────────────── */
document.getElementById('toggleFilter').addEventListener('click', () => {
    const fc = document.getElementById('filterCard');
    fc.style.display = fc.style.display === 'none' ? 'block' : 'none';
});

/* ── Manage bills ───────────────────────── */
/* ── Bills Modal ────────────────────────── */
function openViewBillsModal(id, name) {
    document.getElementById('vb_vendor_name').textContent = name;
    openModal('viewBillsModal');
    loadVendorBills(id);
}

function loadVendorBills(vendorId) {
    const c = document.getElementById('vb_list_container');
    c.innerHTML = '<div style="text-align:center;padding:2.5rem;color:var(--ink-mute);"><i class="fas fa-spinner fa-spin"></i> Loading…</div>';
    
    fetch(`../api/get_vendor_bills.php?vendor_id=${vendorId}&t=${new Date().getTime()}`)
        .then(r => r.json())
        .then(data => {
            // Update stats
            document.getElementById('vb_total').textContent   = data.stats?.total   || '0.00';
            document.getElementById('vb_paid').textContent    = data.stats?.paid    || '0.00';
            document.getElementById('vb_pending').textContent = data.stats?.pending || '0.00';

            if (!data.success || !data.bills.length) {
                c.innerHTML = '<div class="empty-bills"><i class="fas fa-file-invoice"></i><div>No bills found.</div></div>';
                return;
            }
            
            let html = `<table class="bills-table">
                <thead>
                    <tr>
                        <th>Bill No</th>
                        <th>Date</th>
                        <th>Project</th>
                        <th>Amount</th>
                        <th>Approval</th>
                        <th>Payment</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>`;
                
                
            // Project Badge Helper
            const renderProjectBadge = (name, id) => {
                if (!id || id == 0) return '—';
                const palettes = [
                    {bg:'#eff6ff', text:'#1d4ed8', border:'#dbeafe'},
                    {bg:'#f0fdf4', text:'#15803d', border:'#dcfce7'},
                    {bg:'#fef2f2', text:'#b91c1c', border:'#fee2e2'},
                    {bg:'#fff7ed', text:'#c2410c', border:'#ffedd5'},
                    {bg:'#faf5ff', text:'#7e22ce', border:'#f3e8ff'},
                    {bg:'#ecfeff', text:'#0e7490', border:'#cffafe'},
                    {bg:'#fdf4ff', text:'#a21caf', border:'#fce7f3'},
                    {bg:'#fffbeb', text:'#b45309', border:'#fef3c7'},
                    {bg:'#f8fafc', text:'#334155', border:'#e2e8f0'},
                    {bg:'#f0f9ff', text:'#0369a1', border:'#e0f2fe'}
                ];
                const style = palettes[Math.abs(id) % palettes.length];
                return `<span style="display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:20px; font-size:11px; font-weight:700; background-color:${style.bg}; color:${style.text}; border:1px solid ${style.border}; white-space:nowrap; letter-spacing:0.02em; text-transform:uppercase;">
                        <i class="fas fa-building" style="font-size:10px; opacity:0.7;"></i> ${name}
                    </span>`;
            };

            data.bills.forEach(b => {
                html += `<tr>
                    <td style="font-weight:600;color:var(--ink)">${b.bill_no}</td>
                    <td>${b.date}</td>
                    <td>${renderProjectBadge(b.project_name, b.project_id)}</td>
                    <td style="font-weight:700;">${b.amount}</td>
                    <td><span class="pill ${b.status_class}">${b.status}</span></td>
                    <td><span class="pill ${b.payment_class}">${b.payment_status}</span></td>
                    <td>
                        <a href="view_bill.php?id=${b.id}" class="act-btn view" title="View Details" style="text-decoration:none;"><i class="fas fa-eye"></i></a>
                    </td>
                </tr>`;
            });
            
            html += '</tbody></table>';
            c.innerHTML = html;
        })
        .catch(err => {
            console.error(err);
            c.innerHTML = '<div style="text-align:center;padding:1rem;color:#dc2626;">Failed to load bills.</div>';
        });
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>