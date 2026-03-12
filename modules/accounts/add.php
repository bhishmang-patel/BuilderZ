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
$id = $_GET['id'] ?? null;
$edit_mode = false;
$expense = null;
$page_title = 'Record New Expense';
$current_page = 'accounts';

if ($id) {
    $stmt = $db->query("SELECT * FROM expenses WHERE id = ?", [$id]);
    $expense = $stmt->fetch();

    if ($expense) {
        $edit_mode = true;
        $page_title = 'Edit Expense';
        
        // Pre-fill $_POST for the view if not a POST request (Sticky Form pattern)
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_POST['date']           = $expense['date'];
            $_POST['category_id']    = $expense['category_id'];
            $_POST['amount']         = $expense['amount'];
            $_POST['description']    = $expense['description'];
            $_POST['payment_method'] = $expense['payment_method'];
            $_POST['gst_amount']     = $expense['gst_amount'];
            $_POST['gst_percentage'] = $expense['gst_percentage'] ?? 0;
            $_POST['reference_no']   = $expense['reference_no'];
            $_POST['project_id']     = $expense['project_id'];
            $_POST['bank_account_id']= $expense['bank_account_id'];
            if ($expense['gst_included']) {
                $_POST['gst_included'] = 1;
            }
        }
    }
}

// Get categories
$categories = $db->query("SELECT * FROM expense_categories ORDER BY name")->fetchAll();
$bank_accounts = $db->query("SELECT * FROM company_accounts WHERE status = 'active' ORDER BY account_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id    = $_POST['category_id']    ?? '';
    $date           = $_POST['date']           ?? date('Y-m-d');
    $amount         = $_POST['amount']         ?? 0;
    $description    = $_POST['description']    ?? '';
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $gst_included   = isset($_POST['gst_included']) ? 1 : 0;
    $gst_percentage  = (float)($_POST['gst_percentage'] ?? 0);
    $gst_amount     = $_POST['gst_amount']     ?? 0;
    $reference_no   = $_POST['reference_no']   ?? '';
    $project_id     = !empty($_POST['project_id']) ? $_POST['project_id'] : null;
    $bank_account_id = !empty($_POST['bank_account_id']) ? $_POST['bank_account_id'] : null;
    
    // Keep original creator if editing, else current user
    $created_by     = $edit_mode ? $expense['created_by'] : $_SESSION['user_id'];

    if (empty($category_id) || empty($amount) || empty($date)) {
        $error = "Please fill all required fields.";
    } elseif ($payment_method !== 'cash' && empty($bank_account_id)) {
        $error = "Please select a bank account for non-cash payments.";
    } else {
        $data = [
            'category_id'    => $category_id,
            'date'           => $date,
            'amount'         => $amount,
            'description'    => $description,
            'payment_method' => $payment_method,
            'gst_included'   => $gst_included,
            'gst_percentage' => $gst_percentage,
            'gst_amount'     => $gst_amount,
            'reference_no'   => $reference_no,
            'project_id'     => $project_id,
            'bank_account_id'=> $bank_account_id,
            'created_by'     => $created_by
        ];

        $db->beginTransaction();
        try {
            if ($edit_mode) {
                // 1. Revert Old Balance (base + GST)
                if ($expense['bank_account_id']) {
                    $old_total = $expense['amount'] + ($expense['gst_amount'] ?? 0);
                    $db->query("UPDATE company_accounts SET current_balance = current_balance + ? WHERE id = ?", [$old_total, $expense['bank_account_id']]);
                }

                // 2. Update Expense
                $db->update('expenses', $data, 'id = :id', ['id' => $id]);
                $_SESSION['success'] = "Expense updated successfully!";
            } else {
                // 3. Create Expense
                $db->insert('expenses', $data);
                $_SESSION['success'] = "Expense recorded successfully!";
            }

            // 4. Deduct New Balance (base + GST)
            $total_deduction = (float)$amount + (float)$gst_amount;
            if ($bank_account_id) {
                $db->query("UPDATE company_accounts SET current_balance = current_balance - ? WHERE id = ?", [$total_deduction, $bank_account_id]);
            }

            $db->commit();
            header("Location: index.php");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Error recording expense: " . $e->getMessage();
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<style>
  body {
    background: var(--exp-bg);
  }

  /* ── Reset & Variables ────────────────────────────────── */
  .exp-wrap *, .exp-wrap *::before, .exp-wrap *::after { box-sizing: border-box; }

:root {
  --exp-bg:        #f5f3ef;  
  --exp-surface:   #ffffff;  
  --exp-surface2:  #fdfcfa;  
  --exp-surface3:  #f5f3ef;  
  --exp-line:      #e8e3db;  
  --exp-line2:     #dcd5cb;  
  --exp-text:      #1a1714;  
  --exp-text2:     #6b6560;  
  --exp-text3:     #9e9690;  
  --exp-accent:     #b5622a; 
  --exp-accent2:    #9e521f; 
  --exp-accent-bg:  #fdf8f3; 
  --exp-accent-glow: rgba(181,98,42,0.15);
  --exp-red:        #c0392b;
  --exp-red-bg:     #fdf2f1;
  --exp-amber:      #b45309;
  --exp-blue:       #3b5bdb;
}


  /* ── Page wrapper ─────────────────────────────────────── */
  .exp-wrap {
    font-family: 'DM Sans', sans-serif;
    font-size: 13px;
    color: var(--exp-text);
    background: var(--exp-bg);
    min-height: 100vh;
    padding: 36px 24px 80px;
    animation: expPageIn 0.45s cubic-bezier(0.22,1,0.36,1) both;
  }
  @keyframes expPageIn {
    from { opacity: 0; transform: translateY(18px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  .exp-inner { max-width: 1060px; margin: 0 auto; }

  /* ── Topbar ───────────────────────────────────────────── */
  .exp-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 40px;
    padding-bottom: 18px;
    border-bottom: 1px solid var(--exp-line);
    flex-wrap: wrap;
    gap: 14px;
  }
  .exp-topbar-left { display: flex; align-items: center; gap: 16px; }

  .exp-back {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    color: var(--exp-text3);
    text-decoration: none;
    font-size: 10px;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    padding: 8px 14px;
    border: 1px solid var(--exp-line2);
    border-radius: 3px;
    transition: color .15s, border-color .15s;
  }
  .exp-back:hover { color: var(--exp-text); border-color: var(--exp-text3); }

  .exp-breadcrumb {
    font-size: 10px;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--exp-text3);
    margin-bottom: 3px;
  }
  .exp-breadcrumb span { color: var(--exp-accent); }

  .exp-page-title {
    font-family: 'Fraunces', serif;
    font-size: 1.7rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    color: var(--exp-text);
    line-height: 1.1;
  }

  .exp-badge {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 10px;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: var(--exp-accent);
    padding: 6px 12px;
    border: 1px solid var(--exp-accent2);
    border-radius: 2px;
    background: var(--exp-accent-bg);
  }
  .exp-dot {
    width: 6px; height: 6px;
    background: var(--exp-accent);
    border-radius: 50%;
    animation: expPulse 1.8s ease-in-out infinite;
  }
  @keyframes expPulse {
    0%,100% { opacity:1; transform:scale(1); }
    50%      { opacity:.4; transform:scale(.65); }
  }

  /* ── Alert ────────────────────────────────────────────── */
  .exp-alert {
    display: flex;
    align-items: center;
    gap: 10px;
    background: var(--exp-red-bg);
    border: 1px solid var(--exp-red);
    border-left-width: 3px;
    border-radius: 3px;
    padding: 12px 16px;
    margin-bottom: 24px;
    font-size: 12px;
    color: var(--exp-red);
  }

  /* ── Form grid ────────────────────────────────────────── */
  .exp-grid {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 20px;
    align-items: start;
  }
  @media (max-width: 860px) {
    .exp-grid { grid-template-columns: 1fr; }
    .exp-action-bar { grid-column: 1; }
  }

  /* ── Panel ────────────────────────────────────────────── */
  .exp-panel {
    background: var(--exp-surface);
    border: 1px solid var(--exp-line);
    border-radius: 4px;
    overflow: hidden;
    transition: border-color .2s;
  }
  .exp-panel:focus-within { border-color: var(--exp-line2); }

  .exp-panel-head {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 22px;
    border-bottom: 1px solid var(--exp-line);
    background: var(--exp-surface2);
  }
  .exp-panel-icon {
    width: 34px; height: 34px;
    border-radius: 3px;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px;
    flex-shrink: 0;
  }
  .exp-icon-blue  { background: rgba(59,130,246,0.12); color: var(--exp-blue); }
  .exp-icon-teal  { background: rgba(0,212,170,0.10);  color: var(--exp-accent); }
  .exp-icon-amber { background: rgba(245,158,11,0.10); color: var(--exp-amber); }

  .exp-panel-title {
    font-family: 'Fraunces', serif;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--exp-text);
  }
  .exp-panel-sub {
    font-size: 10px;
    color: var(--exp-text3);
    margin-top: 1px;
    letter-spacing: 0.04em;
  }

  .exp-panel-body { padding: 20px 22px 24px; }

  /* ── Section tag ──────────────────────────────────────── */
  .exp-section-tag {
    font-size: 9.5px;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    color: var(--exp-text3);
    padding: 14px 0 8px;
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .exp-section-tag::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--exp-line);
  }
  .exp-section-tag:first-child { padding-top: 0; }

  /* ── Row helpers ──────────────────────────────────────── */
  .exp-row { display: flex; gap: 14px; }
  .exp-row > * { flex: 1; min-width: 0; }
  .exp-row > .exp-w55 { flex: 0 0 55%; }
  @media (max-width: 480px) { .exp-row { flex-direction: column; } }

  /* ── Field ────────────────────────────────────────────── */
  .exp-field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
  .exp-field:last-child { margin-bottom: 0; }

  .exp-label {
    font-size: 9.5px;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--exp-text3);
    display: flex;
    align-items: center;
    gap: 5px;
  }
  .exp-req { color: var(--exp-red); font-size: 13px; line-height: 1; }

  .exp-control {
    font-family: 'JetBrains Mono', 'Fira Mono', 'Courier New', monospace;
    font-size: 12.5px;
    color: var(--exp-text);
    background: var(--exp-surface3);
    border: 1px solid var(--exp-line2);
    border-radius: 3px;
    padding: 10px 13px;
    outline: none;
    width: 100%;
    transition: border-color .18s, box-shadow .18s, background .18s;
    -webkit-appearance: none;
    appearance: none;
  }
  .exp-control::placeholder { color: var(--exp-text3); }
  .exp-control:focus {
    border-color: var(--exp-accent);
    box-shadow: 0 0 0 3px var(--exp-accent-glow);
    background: var(--exp-surface2);
  }
  .exp-control[type="date"] { cursor: pointer; }
  .exp-control[type="date"]::-webkit-calendar-picker-indicator { filter: invert(.45); cursor: pointer; }

  textarea.exp-control { resize: vertical; min-height: 78px; line-height: 1.6; }

  .exp-select-wrap { position: relative; }
  .exp-select-wrap::after {
    content: '▾';
    position: absolute;
    right: 12px; top: 50%;
    transform: translateY(-50%);
    color: var(--exp-text3);
    font-size: 11px;
    pointer-events: none;
  }
  .exp-select-wrap .exp-control { padding-right: 30px; }

  /* ── Rupee prefix ─────────────────────────────────────── */
  .exp-prefix-wrap { position: relative; }
  .exp-prefix {
    position: absolute;
    left: 0; top: 0; bottom: 0;
    display: flex; align-items: center;
    padding: 0 13px;
    font-family: 'Fraunces', serif;
    font-size: 14px;
    font-weight: 700;
    color: var(--exp-accent);
    border-right: 1px solid var(--exp-line2);
    background: rgba(0,212,170,0.06);
    border-radius: 3px 0 0 3px;
    user-select: none;
    transition: border-color .18s, background .18s;
  }
  .exp-prefix-wrap .exp-control { padding-left: 44px; }
  .exp-prefix-wrap:focus-within .exp-prefix {
    border-color: var(--exp-accent);
    background: rgba(0,212,170,0.10);
  }

  .exp-help { font-size: 10px; color: var(--exp-text3); margin-top: 3px; letter-spacing: .03em; }

  /* ── GST checkbox row ─────────────────────────────────── */
  .exp-check-row {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    padding: 10px 13px;
    border: 1px solid var(--exp-line2);
    border-radius: 3px;
    background: var(--exp-surface3);
    transition: border-color .18s, background .18s;
    user-select: none;
    margin-bottom: 0;
  }
  .exp-check-row:hover { background: var(--exp-surface2); }
  .exp-check-row input[type="checkbox"] { position: absolute; opacity: 0; pointer-events: none; }

  .exp-check-box {
    width: 16px; height: 16px;
    border: 1.5px solid var(--exp-line2);
    border-radius: 2px;
    flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    transition: all .15s;
    background: var(--exp-surface);
    font-size: 9px;
    color: transparent;
    font-weight: 700;
  }
  .exp-check-row.is-checked {
    border-color: var(--exp-accent2);
    background: var(--exp-accent-bg);
  }
  .exp-check-row.is-checked .exp-check-box {
    background: var(--exp-accent);
    border-color: var(--exp-accent);
    color: #000;
  }
  .exp-check-label {
    font-size: 11.5px;
    color: var(--exp-text2);
    letter-spacing: .02em;
  }
  .exp-check-row.is-checked .exp-check-label { color: var(--exp-accent); }

  /* ── GST reveal ───────────────────────────────────────── */
  .exp-gst-reveal {
    overflow: hidden;
    max-height: 0;
    transition: max-height .35s cubic-bezier(.22,1,.36,1), opacity .25s;
    opacity: 0;
  }
  .exp-gst-reveal.is-open { max-height: 320px; opacity: 1; }
  .exp-gst-reveal .exp-gst-inner { padding-top: 14px; }

  /* ── Cost Breakdown Panel ───────────────────────────── */
  .exp-breakdown {
    background: var(--exp-surface2);
    border: 1px solid var(--exp-line);
    border-radius: 4px;
    overflow: hidden;
    max-height: 0;
    opacity: 0;
    transition: max-height .35s cubic-bezier(.22,1,.36,1), opacity .25s;
  }
  .exp-breakdown.is-open { max-height: 180px; opacity: 1; }
  .exp-breakdown-head {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 14px;
    border-bottom: 1px solid var(--exp-line);
    font-size: 9.5px; letter-spacing: .18em; text-transform: uppercase;
    color: var(--exp-text3); font-weight: 600;
  }
  .exp-brow {
    display: flex; justify-content: space-between; align-items: center;
    padding: 7px 14px; font-size: 11.5px; color: var(--exp-text2);
    border-bottom: 1px solid var(--exp-line);
  }
  .exp-brow:last-child { border-bottom: none; }
  .exp-brow.final {
    background: var(--exp-accent-bg); color: var(--exp-accent);
    font-weight: 700; font-family: 'Fraunces', serif; font-size: 13px;
  }
  .exp-brow-val { font-family: 'JetBrains Mono', monospace; font-size: 11px; }
  .exp-brow.final .exp-brow-val { font-size: 13px; }

  /* ── Total Summary Bar ──────────────────────────────── */
  .exp-total-bar {
    margin-top: 18px;
    background: var(--exp-surface2);
    border: 1px solid var(--exp-line);
    border-radius: 4px;
    overflow: hidden;
  }
  .exp-total-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 8px 16px;
    font-size: 11.5px; color: var(--exp-text2);
    border-bottom: 1px solid var(--exp-line);
  }
  .exp-total-row:last-child { border-bottom: none; }
  .exp-total-final {
    background: var(--exp-accent-bg);
    padding: 10px 16px;
    font-weight: 700; font-family: 'Fraunces', serif; font-size: 14px;
    color: var(--exp-accent);
  }
  .exp-total-label { letter-spacing: .03em; }
  .exp-total-val {
    font-family: 'JetBrains Mono', monospace; font-size: 11.5px;
    font-weight: 600;
  }
  .exp-total-final .exp-total-val { font-size: 14px; color: var(--exp-accent); }

  /* ── Tips panel ───────────────────────────────────────── */
  .exp-tips {
    background: var(--exp-surface2);
    border: 1px solid var(--exp-line);
    border-radius: 4px;
    overflow: hidden;
  }
  .exp-tips-head {
    display: flex; align-items: center; gap: 10px;
    padding: 14px 18px;
    border-bottom: 1px solid var(--exp-line);
  }
  .exp-tips-icon {
    width: 32px; height: 32px;
    background: rgba(245,158,11,0.12);
    border-radius: 3px;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px;
  }
  .exp-tips-title {
    font-family: 'Fraunces', serif;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--exp-text);
  }
  .exp-tips-body { padding: 4px 18px 6px; }
  .exp-tip-item {
    display: flex;
    gap: 10px;
    padding: 10px 0;
    border-bottom: 1px solid var(--exp-line);
  }
  .exp-tip-item:last-child { border-bottom: none; }
  .exp-tip-num { font-size: 9px; letter-spacing: .12em; color: var(--exp-accent); min-width: 18px; padding-top: 1px; }
  .exp-tip-text { font-size: 11px; color: var(--exp-text2); line-height: 1.65; }

  /* ── Right col ────────────────────────────────────────── */
  .exp-right { display: flex; flex-direction: column; gap: 20px; }

  /* ── Action bar ───────────────────────────────────────── */
  .exp-action-bar {
    grid-column: 1 / -1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    background: var(--exp-surface);
    border: 1px solid var(--exp-line);
    border-radius: 4px;
    padding: 15px 22px;
    flex-wrap: wrap;
  }
  .exp-action-hint {
    font-size: 10.5px;
    color: var(--exp-text3);
    display: flex; align-items: center; gap: 6px;
    letter-spacing: .03em;
  }
  .exp-action-hint .exp-red-dot { color: var(--exp-red); font-size: 14px; line-height: 0; }
  .exp-action-btns { display: flex; gap: 10px; }

  /* ── Buttons ──────────────────────────────────────────── */
  .exp-btn {
    font-family: 'DM Sans', sans-serif;
    font-size: 11px;
    letter-spacing: .16em;
    text-transform: uppercase;
    font-weight: 500;
    border-radius: 3px;
    padding: 11px 20px;
    cursor: pointer;
    border: 1px solid transparent;
    text-decoration: none;
    display: inline-flex; align-items: center; gap: 7px;
    transition: all .18s;
    white-space: nowrap;
  }
  .exp-btn-ghost {
    background: var(--exp-surface2);
    color: var(--exp-text2);
    border-color: var(--exp-line2);
  }
  .exp-btn-ghost:hover { color: var(--exp-text); border-color: var(--exp-text3); background: var(--exp-surface3); }

  .exp-btn-primary {
    background: var(--exp-accent);
    color: #fff;
    border-color: var(--exp-accent);
    font-weight: 700;
  }
  .exp-btn-primary:hover { background: var(--exp-accent2); border-color: var(--exp-accent2); }
  .exp-btn-primary:active { transform: translateY(1px); }
</style>

<!-- Syne font (pairs with monospace for headings) -->
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,500;0,9..144,600;1,9..144,300&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

<div class="exp-wrap">
  <div class="exp-inner">

    <!-- Topbar -->
    <div class="exp-topbar">
      <div class="exp-topbar-left">
        <a href="<?= BASE_URL ?>modules/accounts/index.php" class="exp-back">← Back</a>
        <div>
          <div class="exp-breadcrumb">Accounts / <span>Expenses</span></div>
          <div class="exp-page-title"><?= $edit_mode ? 'Edit Expense' : 'Record Expense' ?></div>
        </div>
      </div>
      <div class="exp-badge">
        <div class="exp-dot"></div>
        <?= $edit_mode ? 'Edit Transaction' : 'New Transaction' ?>
      </div>
    </div>

    <!-- Error alert -->
    <?php if (isset($error)): ?>
    <div class="exp-alert">
      <svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="8" cy="8" r="7"/><path d="M8 5v3M8 11h.01"/>
      </svg>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST">

      <div class="exp-grid">

        <!-- ── LEFT: Transaction Details ───────────────── -->
        <div class="exp-panel">
          <div class="exp-panel-head">
            <div class="exp-panel-icon exp-icon-blue">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>
              </svg>
            </div>
            <div>
              <div class="exp-panel-title">Transaction Details</div>
              <div class="exp-panel-sub">Core expense information</div>
            </div>
          </div>

          <div class="exp-panel-body">

            <div class="exp-section-tag">01 — Identification</div>

            <div class="exp-row">
              <div class="exp-field">
                <label class="exp-label">Date <span class="exp-req">*</span></label>
                <input type="date" name="date"
                       class="exp-control"
                       value="<?= htmlspecialchars($_POST['date'] ?? date('Y-m-d')) ?>"
                       required>
              </div>
              <div class="exp-field">
                <label class="exp-label">Category <span class="exp-req">*</span></label>
                <div class="exp-select-wrap">
                  <select name="category_id" class="exp-control" required>
                    <option value="" disabled <?= empty($_POST['category_id']) ? 'selected' : '' ?>>Select…</option>
                    <?php foreach ($categories as $cat): ?>
                      <option value="<?= $cat['id'] ?>"
                        <?= (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>

            <!-- Project Selection -->
            <div class="exp-field">
                <label class="exp-label">Project (Optional)</label>
                <div class="exp-select-wrap">
                    <?php
                    $projects = $db->query("SELECT id, project_name FROM projects WHERE status = 'active' ORDER BY project_name")->fetchAll();
                    ?>
                    <select name="project_id" class="exp-control">
                        <option value="">— General / Overhead —</option>
                        <?php foreach ($projects as $proj): ?>
                            <option value="<?= $proj['id'] ?>" <?= (isset($_POST['project_id']) && $_POST['project_id'] == $proj['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($proj['project_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="exp-field">
              <label class="exp-label">Description / Particulars</label>
              <textarea name="description" class="exp-control"
                placeholder="e.g. Office electricity bill for January 2026…"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
              <div class="exp-help">Provide a clear description for future audit reference</div>
            </div>

            <div class="exp-section-tag">02 — Payment</div>

            <?php
              $gst_rates = [0, 5, 12, 18, 28];
              $saved_pct = (float)($_POST['gst_percentage'] ?? 0);
            ?>

            <div class="exp-row">
              <div class="exp-field exp-w55">
                <label class="exp-label">Amount <span class="exp-req">*</span></label>
                <div class="exp-prefix-wrap">
                  <span class="exp-prefix">₹</span>
                  <input type="number" step="0.01" name="amount" id="expAmountInput"
                         class="exp-control"
                         value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>"
                         placeholder="0.00" required>
                </div>
              </div>
              <div class="exp-field">
                <label class="exp-label">GST %</label>
                <div class="exp-select-wrap">
                  <select name="gst_percentage" id="gstPctSelect" class="exp-control">
                    <?php foreach ($gst_rates as $rate): ?>
                      <option value="<?= $rate ?>" <?= $saved_pct == $rate ? 'selected' : '' ?>>
                        <?= $rate ?>%<?= $rate === 0 ? ' — No GST' : '' ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>

            <div class="exp-row">
              <div class="exp-field">
                <label class="exp-label">Payment Mode</label>
                <div class="exp-select-wrap">
                  <select name="payment_method" class="exp-control">
                    <?php
                      $methods = [
                        'cash'          => 'Cash',
                        'bank_transfer' => 'Bank Transfer',
                        'upi'           => 'UPI',
                        'cheque'        => 'Cheque',
                        'card'          => 'Card',
                      ];
                      $selected_method = $_POST['payment_method'] ?? 'cash';
                      foreach ($methods as $val => $label):
                    ?>
                      <option value="<?= $val ?>" <?= $selected_method === $val ? 'selected' : '' ?>>
                        <?= $label ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="exp-field" id="bankAccountField" style="display:none;">
                <label class="exp-label">Paid From Account <span class="exp-req">*</span></label>
                <div class="exp-select-wrap">
                    <?php
                    $bank_accounts = $db->query("SELECT id, bank_name, account_number, account_type FROM company_accounts WHERE status = 'active' ORDER BY bank_name")->fetchAll();
                    ?>
                    <select name="bank_account_id" class="exp-control" id="bankSelect">
                        <option value="">Select Bank Account...</option>
                        <?php foreach ($bank_accounts as $bank): ?>
                            <option value="<?= $bank['id'] ?>" <?= (isset($_POST['bank_account_id']) && $_POST['bank_account_id'] == $bank['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($bank['bank_name']) . ' - ' . substr($bank['account_number'], -4) . ' (' . ucfirst($bank['account_type']) . ')' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
              </div>
            </div>

            <div class="exp-field">
              <label class="exp-label">Reference No. / Transaction ID</label>
              <input type="text" name="reference_no"
                     class="exp-control"
                     value="<?= htmlspecialchars($_POST['reference_no'] ?? '') ?>"
                     placeholder="Optional — e.g. TXN8834920">
              <div class="exp-help">Bank ref, cheque number, or UPI transaction ID</div>
            </div>

            <!-- Hidden GST amount field (auto-calculated by JS) -->
            <input type="hidden" name="gst_amount" id="gstAmountInput" value="<?= htmlspecialchars($_POST['gst_amount'] ?? '0') ?>">
            <input type="hidden" name="gst_included" value="1">

            <!-- Total Summary Bar -->
            <div class="exp-total-bar" id="expTotalBar">
              <div class="exp-total-row">
                <span class="exp-total-label">Amount</span>
                <span class="exp-total-val" id="tbAmt">₹ 0.00</span>
              </div>
              <div class="exp-total-row">
                <span class="exp-total-label">+ GST (<span id="tbPct">0</span>%)</span>
                <span class="exp-total-val" id="tbGst">₹ 0.00</span>
              </div>
              <div class="exp-total-row exp-total-final">
                <span class="exp-total-label">Total Amount</span>
                <span class="exp-total-val" id="tbTotal">₹ 0.00</span>
              </div>
            </div>

          </div>
        </div>

        <!-- ── RIGHT column ─────────────────────────────── -->
        <div class="exp-right">

          <!-- Tips Panel -->
          <div class="exp-tips">
            <div class="exp-tips-head">
              <div class="exp-tips-icon">💡</div>
              <div class="exp-tips-title">Quick Tips</div>
            </div>
            <div class="exp-tips-body">
              <div class="exp-tip-item">
                <div class="exp-tip-num">01</div>
                <div class="exp-tip-text">Select the correct category for accurate financial reporting.</div>
              </div>
              <div class="exp-tip-item">
                <div class="exp-tip-num">02</div>
                <div class="exp-tip-text">Always include GST details for tax compliance and input credit claims.</div>
              </div>
              <div class="exp-tip-item">
                <div class="exp-tip-num">03</div>
                <div class="exp-tip-text">Add a reference number for bank transfers and cheque payments.</div>
              </div>
            </div>
          </div>

        </div>

        <!-- ── Action bar (full width) ──────────────────── -->
        <div class="exp-action-bar exp-action-bar">
          <div class="exp-action-hint">
            <span class="exp-red-dot">*</span>
            Required fields must be filled before saving
          </div>
          <div class="exp-action-btns">
            <a href="<?= BASE_URL ?>modules/accounts/index.php" class="exp-btn exp-btn-ghost">
              Cancel
            </a>
            <button type="submit" class="exp-btn exp-btn-primary">
              <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M13 2H3a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V3a1 1 0 0 0-1-1z"/>
                <path d="M10 2v5H6V2"/><path d="M4 14v-5h8v5"/>
              </svg>
              <?= $edit_mode ? 'Update Transaction' : 'Save Transaction' ?>
            </button>
          </div>
        </div>

      </div><!-- .exp-grid -->

    </form>

  </div><!-- .exp-inner -->
</div><!-- .exp-wrap -->

<script>
function expCalcGST() {
  var amountInput  = document.getElementById('expAmountInput');
  var gstPctSelect = document.getElementById('gstPctSelect');
  var gstAmtInput  = document.getElementById('gstAmountInput');
  var tbAmt        = document.getElementById('tbAmt');
  var tbPct        = document.getElementById('tbPct');
  var tbGst        = document.getElementById('tbGst');
  var tbTotal      = document.getElementById('tbTotal');

  if (!amountInput || !gstPctSelect) return;

  var amount = parseFloat(amountInput.value) || 0;
  var pct    = parseFloat(gstPctSelect.value) || 0;
  var gstAmt = parseFloat((amount * pct / 100).toFixed(2));
  var total  = parseFloat((amount + gstAmt).toFixed(2));

  // Update hidden GST amount
  if (gstAmtInput) gstAmtInput.value = gstAmt.toFixed(2);

  // Update summary bar
  var fmt = function(v) {
    return '\u20b9 ' + v.toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2});
  };
  if (tbAmt)   tbAmt.textContent   = fmt(amount);
  if (tbPct)   tbPct.textContent   = pct;
  if (tbGst)   tbGst.textContent   = fmt(gstAmt);
  if (tbTotal) tbTotal.textContent = fmt(total);
}

// Wire up events
document.addEventListener('DOMContentLoaded', function() {
  var amtInput = document.getElementById('expAmountInput');
  var gstSelect = document.getElementById('gstPctSelect');

  if (amtInput) amtInput.addEventListener('input', expCalcGST);
  if (gstSelect) gstSelect.addEventListener('change', expCalcGST);

  expCalcGST(); // initial calculation on load
});

// Payment Method Toggle
const paymentMethodSelect = document.querySelector('select[name="payment_method"]');
const bankField = document.getElementById('bankAccountField');
const bankSelect = document.getElementById('bankSelect');

function toggleBankField() {
    if (paymentMethodSelect.value === 'cash') {
        bankField.style.display = 'none';
        bankSelect.required = false;
        bankSelect.value = '';
    } else {
        bankField.style.display = 'flex';
        bankSelect.required = true;
    }
}

if(paymentMethodSelect) {
    paymentMethodSelect.addEventListener('change', toggleBankField);
    toggleBankField();
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>