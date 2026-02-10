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
$page_title = 'Record New Expense';

// Get categories
$categories = $db->query("SELECT * FROM expense_categories ORDER BY name")->fetchAll();

// Get projects
$projects = $db->query("SELECT id, project_name FROM projects WHERE status = 'active' ORDER BY project_name")->fetchAll();

$edit_id = $_GET['id'] ?? null;
$expense = null;
$page_title = 'Record New Expense';

if ($edit_id) {
    $stmt = $db->query("SELECT * FROM expenses WHERE id = ?", [$edit_id]);
    $expense = $stmt->fetch();
    
    if ($expense) {
        $page_title = 'Edit Expense';
    } else {
        $error = "Expense not found.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $edit_id_post   = $_POST['edit_id'] ?? null;
    $project_id     = !empty($_POST['project_id']) ? $_POST['project_id'] : null;
    $category_id    = $_POST['category_id']    ?? '';
    $date           = $_POST['date']           ?? date('Y-m-d');
    $amount         = $_POST['amount']         ?? 0;
    $description    = $_POST['description']    ?? '';
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $gst_included   = isset($_POST['gst_included']) ? 1 : 0;
    $gst_amount     = $_POST['gst_amount']     ?? 0;
    $reference_no   = $_POST['reference_no']   ?? '';
    $created_by     = $_SESSION['user_id'];

    if (empty($category_id) || empty($amount) || empty($date)) {
        $error = "Please fill all required fields.";
    } else {
        $data = [
            'project_id'     => $project_id,
            'category_id'    => $category_id,
            'date'           => $date,
            'amount'         => $amount,
            'description'    => $description,
            'payment_method' => $payment_method,
            'gst_included'   => $gst_included,
            'gst_amount'     => $gst_amount,
            'reference_no'   => $reference_no,
        ];

        try {
            if ($edit_id_post) {
                // UPDATE
                 $fields = [];
                 $params = [];
                 foreach ($data as $key => $value) {
                     $fields[] = "$key = ?";
                     $params[] = $value;
                 }
                 $params[] = $edit_id_post;
                 
                 $sql = "UPDATE expenses SET " . implode(', ', $fields) . " WHERE id = ?";
                 $db->query($sql, $params);
                 
                 $_SESSION['success'] = "Expense updated successfully!";
            } else {
                // INSERT
                $data['created_by'] = $created_by;
                $db->insert('expenses', $data);
                $_SESSION['success'] = "Expense recorded successfully!";
            }
            
            header("Location: list.php"); // Redirect to list view after edit/add
            exit;
        } catch (Exception $e) {
            $error = "Error saving expense: " . $e->getMessage();
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<style>
  /* ── Reset & Variables ────────────────────────────────── */
  .exp-wrap *, .exp-wrap *::before, .exp-wrap *::after { box-sizing: border-box; }

  :root {
    --exp-bg:         #f8f9fc;
    --exp-surface:    #ffffff;
    --exp-surface2:   #f8f9fc;
    --exp-surface3:   #f1f3f8;
    --exp-line:       #e3e6f0;
    --exp-line2:      #d1d3e2;
    --exp-text:       #1e293b;
    --exp-text2:      #64748b;
    --exp-text3:      #858796;
    --exp-accent:     #4e73df;
    --exp-accent2:    #3a5bbf;
    --exp-accent-bg:  rgba(78,115,223,0.07);
    --exp-accent-glow:rgba(78,115,223,0.20);
    --exp-red:        #e74a3b;
    --exp-red-bg:     rgba(231,74,59,0.08);
    --exp-amber:      #f6c23e;
    --exp-blue:       #4e73df;
    --exp-teal:       #1cc88a;
    --exp-violet:     #8b5cf6;
  }

  /* ── Page wrapper ─────────────────────────────────────── */
  .exp-wrap {
    font-family: inherit;
    font-size: 14px;
    color: var(--exp-text);
    padding: 0 0 40px;
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
    border-bottom: 1px solid #e2e8f0;
    flex-wrap: wrap;
    gap: 14px;
  }
  .exp-topbar-left { display: flex; align-items: center; gap: 16px; }

  .exp-breadcrumb {
    font-size: 10px;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: #64748b;
    margin-bottom: 3px;
  }
  .exp-breadcrumb span { color: var(--exp-accent); }

  .exp-page-title {
    font-family: 'Syne', 'Segoe UI', sans-serif;
    font-size: 1.7rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    color: #1e293b;
    line-height: 1.1;
  }

  .exp-badge {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 10px;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: var(--exp-violet);
    padding: 6px 12px;
    border: 1px solid rgba(139,92,246,0.4);
    border-radius: 2px;
    background: rgba(139,92,246,0.07);
  }
  .exp-dot {
    width: 6px; height: 6px;
    background: var(--exp-violet);
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
    border-radius: 16px;
    overflow: hidden;
    transition: border-color .2s;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
  }
  .exp-panel:focus-within { border-color: var(--exp-accent); }

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
  .exp-icon-blue  { background: rgba(78,115,223,0.12); color: var(--exp-blue); }
  .exp-icon-teal  { background: rgba(28,200,138,0.10);  color: var(--exp-teal); }
  .exp-icon-amber { background: rgba(246,194,62,0.10); color: var(--exp-amber); }

  .exp-panel-title {
    font-family: 'Syne', 'Segoe UI', sans-serif;
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
    font-family: inherit;
    font-size: 13px;
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
  .exp-control::placeholder { color: #b0b5c0; }
  .exp-control:focus {
    border-color: var(--exp-accent);
    box-shadow: 0 0 0 3px var(--exp-accent-glow);
    background: #fff;
  }
  .exp-control[type="date"] { cursor: pointer; }
  .exp-control[type="date"]::-webkit-calendar-picker-indicator { filter: none; cursor: pointer; }

  textarea.exp-control { resize: vertical; min-height: 78px; line-height: 1.6; }

  .exp-select-wrap { position: relative; }
  .exp-select-wrap::after {
    content: '▾';
    position: absolute;
    right: 12px; top: 50%;
    transform: translateY(-50%);
    color: #858796;
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
    font-family: inherit;
    font-size: 14px;
    font-weight: 700;
    color: var(--exp-accent);
    border-right: 1px solid var(--exp-line);
    background: rgba(78,115,223,0.06);
    border-radius: 3px 0 0 3px;
    user-select: none;
    transition: border-color .18s, background .18s;
  }
  .exp-prefix-wrap .exp-control { padding-left: 44px; }
  .exp-prefix-wrap:focus-within .exp-prefix {
    border-color: var(--exp-accent);
    background: rgba(78,115,223,0.10);
  }

  .exp-help { font-size: 10px; color: #858796; margin-top: 3px; letter-spacing: .03em; }

  /* ── GST checkbox row ─────────────────────────────────── */
  .exp-check-row {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    padding: 10px 13px;
    border: 1px solid var(--exp-line);
    border-radius: 8px;
    background: var(--exp-surface2);
    transition: border-color .18s, background .18s;
    user-select: none;
    margin-bottom: 0;
  }
  .exp-check-row:hover { background: #eef0f8; }
  .exp-check-row input[type="checkbox"] { position: absolute; opacity: 0; pointer-events: none; }

  .exp-check-box {
    width: 16px; height: 16px;
    border: 1.5px solid var(--exp-line);
    border-radius: 2px;
    flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    transition: all .15s;
    background: #fff;
    font-size: 9px;
    color: transparent;
    font-weight: 700;
  }
  .exp-check-row.is-checked {
    border-color: var(--exp-teal);
    background: rgba(28,200,138,0.07);
  }
  .exp-check-row.is-checked .exp-check-box {
    background: var(--exp-teal);
    border-color: var(--exp-teal);
    color: #fff;
  }
  .exp-check-label {
    font-size: 11.5px;
    color: var(--exp-text);
    letter-spacing: .02em;
  }
  .exp-check-row.is-checked .exp-check-label { color: var(--exp-teal); }

  /* ── GST reveal ───────────────────────────────────────── */
  .exp-gst-reveal {
    overflow: hidden;
    max-height: 0;
    transition: max-height .35s cubic-bezier(.22,1,.36,1), opacity .25s;
    opacity: 0;
  }
  .exp-gst-reveal.is-open { max-height: 160px; opacity: 1; }
  .exp-gst-reveal .exp-gst-inner { padding-top: 14px; }

  /* ── Tips panel ───────────────────────────────────────── */
  .exp-tips {
    background: #fff;
    border: 1px solid var(--exp-line);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
  }
  .exp-tips-head {
    display: flex; align-items: center; gap: 10px;
    padding: 14px 18px;
    border-bottom: 1px solid var(--exp-line);
  }
  .exp-tips-icon {
    width: 32px; height: 32px;
    background: rgba(246,194,62,0.12);
    border-radius: 3px;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px;
  }
  .exp-tips-title {
    font-family: inherit;
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
  .exp-tip-text { font-size: 11px; color: #64748b; line-height: 1.65; }

  /* ── Right col ────────────────────────────────────────── */
  .exp-right { display: flex; flex-direction: column; gap: 20px; }

  /* ── Action bar ───────────────────────────────────────── */
  .exp-action-bar {
    grid-column: 1 / -1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 4px;
    padding: 15px 22px;
    flex-wrap: wrap;
  }
  .exp-action-hint {
    font-size: 10.5px;
    color: #64748b;
    display: flex; align-items: center; gap: 6px;
    letter-spacing: .03em;
  }
  .exp-action-hint .exp-red-dot { color: var(--exp-red); font-size: 14px; line-height: 0; }
  .exp-action-btns { display: flex; gap: 10px; }

  /* ── Buttons ──────────────────────────────────────────── */
  .exp-btn {
    font-family: inherit;
    font-size: 12px;
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
    background: #fff;
    color: #64748b;
    border-color: #e2e8f0;
  }
  .exp-btn-ghost:hover { color: #1e293b; border-color: #94a3b8; background: #f1f5f9; }

  .exp-btn-primary {
    background: var(--exp-accent);
    color: #fff;
    border-color: var(--exp-accent);
    font-weight: 700;
  }
  .exp-btn-primary:hover { background: var(--exp-accent2); border-color: var(--exp-accent2); }
  .exp-btn-primary:active { transform: translateY(1px); }
</style>



<div class="exp-wrap">
  <div class="exp-inner">

    <!-- Topbar -->
    <div class="exp-topbar">
      <div class="exp-topbar-left">
        <div>
          <div class="exp-breadcrumb">Accounts / <span>Expenses</span></div>
          <div class="exp-page-title"><?= $page_title ?></div>
        </div>
      </div>
      <div class="exp-badge">
        <div class="exp-dot"></div>
        <?= $edit_id ? 'Edit Transaction' : 'New Transaction' ?>
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
      <?php if ($edit_id): ?>
        <input type="hidden" name="edit_id" value="<?= $edit_id ?>">
      <?php endif; ?>

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
                <label class="exp-label">Project / Site</label>
                <div class="exp-select-wrap">
                  <select name="project_id" class="exp-control">
                    <option value="">General / Head Office</option>
                    <?php foreach ($projects as $proj): ?>
                      <option value="<?= $proj['id'] ?>"
                        <?= ($expense && $expense['project_id'] == $proj['id']) ? 'selected' : 
                           ((isset($_POST['project_id']) && $_POST['project_id'] == $proj['id']) ? 'selected' : '') ?>>
                        <?= htmlspecialchars($proj['project_name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>

            <div class="exp-row">
              <div class="exp-field">
                <label class="exp-label">Date <span class="exp-req">*</span></label>
                <input type="date" name="date"
                       class="exp-control"
                       value="<?= htmlspecialchars($expense ? ($expense['date'] ?? '') : ($_POST['date'] ?? date('Y-m-d'))) ?>"
                       required>
              </div>
              <div class="exp-field">
                <label class="exp-label">Category <span class="exp-req">*</span></label>
                <div class="exp-select-wrap">
                  <select name="category_id" class="exp-control" required>
                    <option value="" disabled <?= empty($expense) && empty($_POST['category_id']) ? 'selected' : '' ?>>Select…</option>
                    <?php foreach ($categories as $cat): ?>
                      <option value="<?= $cat['id'] ?>"
                        <?= ($expense && $expense['category_id'] == $cat['id']) ? 'selected' :
                           ((isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : '') ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>

            <div class="exp-field">
              <label class="exp-label">Description / Particulars</label>
              <textarea name="description" class="exp-control"
                placeholder="e.g. Office electricity bill for January 2026…"><?= htmlspecialchars($expense ? ($expense['description'] ?? '') : ($_POST['description'] ?? '')) ?></textarea>
              <div class="exp-help">Provide a clear description for future audit reference</div>
            </div>

            <div class="exp-section-tag">02 — Payment</div>

            <div class="exp-row">
              <div class="exp-field exp-w55">
                <label class="exp-label">Total Amount <span class="exp-req">*</span></label>
                <div class="exp-prefix-wrap">
                  <span class="exp-prefix">₹</span>
                  <input type="number" step="0.01" name="amount"
                         class="exp-control"
                         value="<?= htmlspecialchars($expense ? ($expense['amount'] ?? '') : ($_POST['amount'] ?? '')) ?>"
                         placeholder="0.00" required>
                </div>
              </div>
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
                      $selected_method = $expense ? $expense['payment_method'] : ($_POST['payment_method'] ?? 'cash');
                      foreach ($methods as $val => $label):
                    ?>
                      <option value="<?= $val ?>" <?= $selected_method === $val ? 'selected' : '' ?>>
                        <?= $label ?>
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
                     value="<?= htmlspecialchars($expense ? ($expense['reference_no'] ?? '') : ($_POST['reference_no'] ?? '')) ?>"
                     placeholder="Optional — e.g. TXN8834920">
              <div class="exp-help">Bank ref, cheque number, or UPI transaction ID</div>
            </div>

          </div>
        </div>

        <!-- ── RIGHT column ─────────────────────────────── -->
        <div class="exp-right">

          <!-- GST Panel -->
          <div class="exp-panel">
            <div class="exp-panel-head">
              <div class="exp-panel-icon exp-icon-teal">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                  <path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>
                </svg>
              </div>
              <div>
                <div class="exp-panel-title">GST Information</div>
                <div class="exp-panel-sub">Tax &amp; input credit</div>
              </div>
            </div>

            <div class="exp-panel-body">

              <?php 
                $gst_checked = $expense ? $expense['gst_included'] : isset($_POST['gst_included']); 
              ?>
              <div class="exp-field">
                <label class="exp-check-row <?= $gst_checked ? 'is-checked' : '' ?>"
                       id="expGstRow" onclick="expToggleGST()">
                  <input type="checkbox" name="gst_included" id="expGstCheck"
                         <?= $gst_checked ? 'checked' : '' ?>>
                  <span class="exp-check-box"><?= $gst_checked ? '✓' : '' ?></span>
                  <span class="exp-check-label">GST is included in total amount</span>
                </label>
              </div>

              <div class="exp-gst-reveal <?= $gst_checked ? 'is-open' : '' ?>" id="expGstReveal">
                <div class="exp-gst-inner">
                  <div class="exp-field">
                    <label class="exp-label">GST Amount (Input Credit)</label>
                    <div class="exp-prefix-wrap">
                      <span class="exp-prefix">₹</span>
                      <input type="number" step="0.01" name="gst_amount"
                             class="exp-control"
                             value="<?= htmlspecialchars($expense ? ($expense['gst_amount'] ?? '') : ($_POST['gst_amount'] ?? '')) ?>"
                             placeholder="0.00">
                    </div>
                    <div class="exp-help">Enter the tax portion included in the total</div>
                  </div>
                </div>
              </div>

            </div>
          </div>

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
            <a href="index.php" class="exp-btn exp-btn-ghost">Cancel</a>
            <button type="submit" class="exp-btn exp-btn-primary">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                <polyline points="17 21 17 13 7 13 7 21"/>
                <polyline points="7 3 7 8 15 8"/>
              </svg>
              <?= $edit_id ? 'Update Expense' : 'Save Expense' ?>
            </button>
          </div>
        </div>

      </div><!-- .exp-grid -->

    </form>

  </div><!-- .exp-inner -->
</div><!-- .exp-wrap -->

<script>
function expToggleGST() {
  var check  = document.getElementById('expGstCheck');
  var row    = document.getElementById('expGstRow');
  var reveal = document.getElementById('expGstReveal');
  var box    = row.querySelector('.exp-check-box');

  check.checked = !check.checked;
  row.classList.toggle('is-checked', check.checked);
  reveal.classList.toggle('is-open', check.checked);
  box.textContent = check.checked ? '✓' : '';
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>