<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'accountant']);

$db = Database::getInstance();
$page_title = 'Manage Expense Categories';

// Handle Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        $name        = trim($_POST['name']        ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($name)) {
            $error = "Category name is required.";
        } else {
            try {
                $stmt = $db->query("SELECT id FROM expense_categories WHERE name = ?", [$name]);
                if ($stmt->fetch()) {
                    $error = "Category already exists.";
                } else {
                    $db->insert('expense_categories', [
                        'name'        => $name,
                        'description' => $description,
                        'type'        => 'expense'
                    ]);
                    $success = "Category added successfully!";
                }
            } catch (Exception $e) {
                $error = "Error adding category: " . $e->getMessage();
            }
        }
    }

    // Handle Edit Category
    if (isset($_POST['edit_category'])) {
        $id          = $_POST['category_id'];
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($name)) {
            $error = "Category name is required.";
        } else {
            try {
                // Check if name exists for another category
                $stmt = $db->query("SELECT id FROM expense_categories WHERE name = ? AND id != ?", [$name, $id]);
                if ($stmt->fetch()) {
                    $error = "Category name already exists.";
                } else {
                    $db->query("UPDATE expense_categories SET name = ?, description = ? WHERE id = ?", [$name, $description, $id]);
                    $success = "Category updated successfully!";
                }
            } catch (Exception $e) {
                $error = "Error updating category: " . $e->getMessage();
            }
        }
    }

    // Handle Delete Category
    if (isset($_POST['delete_category'])) {
        $id = $_POST['category_id'];

        try {
            // Check if category is used
            $stmt = $db->query("SELECT COUNT(*) as count FROM expenses WHERE category_id = ?", [$id]);
            $count = $stmt->fetch()['count'];

            if ($count > 0) {
                $error = "Cannot delete category. It is used in $count expense records.";
            } else {
                $db->query("DELETE FROM expense_categories WHERE id = ?", [$id]);
                $success = "Category deleted successfully!";
            }
        } catch (Exception $e) {
            $error = "Error deleting category: " . $e->getMessage();
        }
    }
}

// Get all categories
$categories = $db->query("SELECT * FROM expense_categories ORDER BY name")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,500;0,9..144,600;1,9..144,300&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
  body {
    background: var(--c-bg);
  }

/* ── Variables ───────────────────────────────────────────── */
:root {
  --c-bg:        #f5f3ef;
  --c-surface:   #faf7f2;
  --c-surface2:  #ffffff;
  --c-border:    #e2d9cc;
  --c-border2:   #cfc4b4;
  --c-ink:       #1e1a16;
  --c-ink2:      #5a5248;
  --c-ink3:      #9c8f82;
  --c-accent:    #c0622a;
  --c-accent-bg: #fdf1ea;
  --c-accent2:   #9b4e22;
  --c-green:     #2d7a4f;
  --c-green-bg:  #edf7f2;
  --c-red:       #c0392b;
  --c-red-bg:    #fdf2f1;
  --c-amber:     #b45309;
  --c-amber-bg:  #fffbeb;
}

/* ── Reset ───────────────────────────────────────────────── */
.cat-wrap *, .cat-wrap *::before, .cat-wrap *::after { box-sizing: border-box; }

/* ── Page shell ──────────────────────────────────────────── */
.cat-wrap {
  font-family: 'DM Sans', sans-serif;
  font-size: 14px;
  color: var(--c-ink);
  background: var(--c-bg);
  min-height: 100vh;
  padding: 40px 28px 80px;
  animation: catFadeIn .5s cubic-bezier(.22,1,.36,1) both;
}
@keyframes catFadeIn {
  from { opacity:0; transform:translateY(16px); }
  to   { opacity:1; transform:translateY(0); }
}
.cat-inner { max-width: 1100px; margin: 0 auto; }

/* ── Topbar ──────────────────────────────────────────────── */
.cat-topbar {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  margin-bottom: 36px;
  padding-bottom: 24px;
  border-bottom: 1.5px solid var(--c-border);
  flex-wrap: wrap;
  gap: 16px;
}
.cat-eyebrow {
  font-size: 11px;
  letter-spacing: .2em;
  text-transform: uppercase;
  color: var(--c-accent);
  font-weight: 500;
  margin-bottom: 5px;
}
.cat-page-title {
  font-family: 'Fraunces', serif;
  font-size: 2rem;
  font-weight: 600;
  letter-spacing: -.025em;
  color: var(--c-ink);
  line-height: 1.1;
}
.cat-page-sub {
  font-size: 13px;
  color: var(--c-ink3);
  margin-top: 4px;
}
.cat-back {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  font-size: 12px;
  font-weight: 500;
  letter-spacing: .04em;
  color: var(--c-ink2);
  text-decoration: none;
  padding: 9px 18px;
  border: 1.5px solid var(--c-border2);
  border-radius: 40px;
  background: var(--c-surface2);
  transition: border-color .18s, color .18s, background .18s;
}
.cat-back:hover { border-color: var(--c-accent); color: var(--c-accent); background: var(--c-accent-bg); }

/* ── Layout columns ──────────────────────────────────────── */
.cat-layout {
  display: grid;
  grid-template-columns: 320px 1fr;
  gap: 24px;
  align-items: start;
}
@media (max-width: 860px) { .cat-layout { grid-template-columns: 1fr; } }

/* ── Sticky sidebar ──────────────────────────────────────── */
.cat-sidebar { position: sticky; top: 24px; display: flex; flex-direction: column; gap: 16px; }

/* ── Card ────────────────────────────────────────────────── */
.cat-card {
  background: var(--c-surface2);
  border: 1.5px solid var(--c-border);
  border-radius: 12px;
  overflow: hidden;
}

.cat-card-head {
  padding: 20px 24px 16px;
  border-bottom: 1px solid var(--c-border);
  display: flex;
  align-items: center;
  gap: 12px;
}
.cat-card-icon {
  width: 38px; height: 38px;
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 16px;
  flex-shrink: 0;
}
.cat-icon-terracotta { background: var(--c-accent-bg); color: var(--c-accent); }
.cat-icon-slate      { background: #eef2f7; color: #3b5bdb; }

.cat-card-title {
  font-family: 'Fraunces', serif;
  font-size: 1rem;
  font-weight: 600;
  color: var(--c-ink);
  letter-spacing: -.01em;
}
.cat-card-count {
  font-size: 11px;
  color: var(--c-ink3);
  margin-top: 1px;
}

.cat-card-body { padding: 22px 24px; }

/* ── Alerts ──────────────────────────────────────────────── */
.cat-alert {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 11px 14px;
  border-radius: 8px;
  font-size: 12.5px;
  font-weight: 500;
  margin-bottom: 18px;
  line-height: 1.5;
}
.cat-alert-error   { background: var(--c-red-bg);   color: var(--c-red);   border: 1px solid #f5c6c3; }
.cat-alert-success { background: var(--c-green-bg); color: var(--c-green); border: 1px solid #b7e4cc; }
.cat-alert svg { flex-shrink: 0; margin-top: 1px; }

/* ── Form ────────────────────────────────────────────────── */
.cat-field { margin-bottom: 16px; }
.cat-field:last-of-type { margin-bottom: 0; }

.cat-label {
  display: block;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: var(--c-ink3);
  margin-bottom: 7px;
}
.cat-req { color: var(--c-accent); }

.cat-input,
.cat-textarea {
  font-family: 'DM Sans', sans-serif;
  font-size: 13.5px;
  color: var(--c-ink);
  background: var(--c-surface);
  border: 1.5px solid var(--c-border);
  border-radius: 8px;
  padding: 10px 14px;
  width: 100%;
  outline: none;
  transition: border-color .2s, box-shadow .2s, background .2s;
  -webkit-appearance: none;
}
.cat-input::placeholder, .cat-textarea::placeholder { color: var(--c-ink3); }
.cat-input:focus, .cat-textarea:focus {
  border-color: var(--c-accent);
  box-shadow: 0 0 0 3px rgba(192,98,42,.12);
  background: #fff;
}
.cat-textarea { resize: vertical; min-height: 80px; line-height: 1.6; }
.cat-help { font-size: 11px; color: var(--c-ink3); margin-top: 5px; }

/* ── Submit button ───────────────────────────────────────── */
.cat-submit {
  width: 100%;
  margin-top: 20px;
  font-family: 'DM Sans', sans-serif;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: .06em;
  color: #fff;
  background: var(--c-accent);
  border: none;
  border-radius: 8px;
  padding: 12px 20px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: background .2s, transform .15s, box-shadow .2s;
  box-shadow: 0 2px 8px rgba(192,98,42,.25);
}
.cat-submit:hover { background: var(--c-accent2); box-shadow: 0 4px 16px rgba(192,98,42,.3); }
.cat-submit:active { transform: translateY(1px); }

/* ── Info box ────────────────────────────────────────────── */
.cat-infobox {
  background: var(--c-amber-bg);
  border: 1.5px solid #fde68a;
  border-radius: 10px;
  padding: 16px 18px;
}
.cat-infobox-head {
  display: flex;
  align-items: center;
  gap: 8px;
  font-family: 'Fraunces', serif;
  font-size: 13px;
  font-weight: 600;
  color: var(--c-amber);
  margin-bottom: 8px;
}
.cat-infobox p {
  font-size: 12px;
  color: #78450c;
  line-height: 1.65;
  margin: 0;
}

/* ── Table card ──────────────────────────────────────────── */
.cat-table-wrap { overflow-x: auto; }

table.cat-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13.5px;
}
.cat-table thead tr {
  border-bottom: 2px solid var(--c-border);
}
.cat-table th {
  font-size: 10.5px;
  font-weight: 600;
  letter-spacing: .16em;
  text-transform: uppercase;
  color: var(--c-ink3);
  padding: 12px 18px;
  text-align: left;
  white-space: nowrap;
}
.cat-table th.center { text-align: center; }

.cat-table tbody tr {
  border-bottom: 1px solid var(--c-border);
  transition: background .15s;
  animation: catRowIn .35s ease both;
}
@keyframes catRowIn {
  from { opacity:0; transform:translateX(-8px); }
  to   { opacity:1; transform:translateX(0); }
}
.cat-table tbody tr:hover { background: var(--c-surface); }
.cat-table tbody tr:last-child { border-bottom: none; }

.cat-table td { padding: 14px 18px; vertical-align: middle; color: var(--c-ink2); }
.cat-table td.center { text-align: center; }

/* ── Category avatar ─────────────────────────────────────── */
.cat-avatar {
  width: 40px; height: 40px;
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-family: 'Fraunces', serif;
  font-size: 1rem;
  font-weight: 600;
  flex-shrink: 0;
}
.cat-name-cell { display: flex; align-items: center; gap: 13px; }
.cat-name-text {
  font-weight: 600;
  color: var(--c-ink);
  font-size: 14px;
}
.cat-desc-text { font-size: 12.5px; color: var(--c-ink3); line-height: 1.5; }

/* ── Badges ──────────────────────────────────────────────── */
.cat-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 11px;
  border-radius: 20px;
  font-size: 11.5px;
  font-weight: 600;
  white-space: nowrap;
}
.cat-badge-type   { background: #eef2f7; color: #3b5bdb; }
.cat-badge-active { background: var(--c-green-bg); color: var(--c-green); }
.cat-badge-dot {
  width: 5px; height: 5px;
  border-radius: 50%;
  background: currentColor;
  display: inline-block;
}

/* ── Empty state ─────────────────────────────────────────── */
.cat-empty {
  padding: 60px 20px;
  text-align: center;
}
.cat-empty-icon {
  width: 72px; height: 72px;
  background: var(--c-bg);
  border: 2px dashed var(--c-border2);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 18px;
  font-size: 26px;
  color: var(--c-ink3);
}
.cat-empty h5 {
  font-family: 'Fraunces', serif;
  font-size: 1.05rem;
  font-weight: 600;
  color: var(--c-ink2);
  margin-bottom: 6px;
}
.cat-empty p { font-size: 13px; color: var(--c-ink3); margin: 0; }

/* ── Summary footer ──────────────────────────────────────── */
.cat-summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 24px;
  border-top: 1px solid var(--c-border);
  background: var(--c-surface);
  flex-wrap: wrap;
  gap: 12px;
}
.cat-summary-stat { display: flex; align-items: center; gap: 12px; }
.cat-summary-icon {
  width: 38px; height: 38px;
  background: var(--c-accent-bg);
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  color: var(--c-accent);
  font-size: 16px;
}
.cat-summary-label { font-size: 11px; color: var(--c-ink3); }
.cat-summary-val   { font-family: 'Fraunces', serif; font-size: 1.4rem; font-weight: 600; color: var(--c-ink); line-height: 1; }

.cat-link-btn {
  display: inline-flex; align-items: center; gap: 7px;
  font-size: 12px; font-weight: 500;
  color: var(--c-ink2);
  text-decoration: none;
  padding: 9px 18px;
  border: 1.5px solid var(--c-border2);
  border-radius: 40px;
  background: var(--c-surface2);
  transition: border-color .18s, color .18s;
}
.cat-link-btn:hover { border-color: var(--c-accent); color: var(--c-accent); }

/* ── Modal ───────────────────────────────────────────────── */
.m-backdrop { 
    display:none; position:fixed; inset:0; z-index:10000; 
    background:rgba(26,23,20,.45); backdrop-filter:blur(3px); 
    align-items:center; justify-content:center; padding:1rem; 
}
.m-backdrop.open { display:flex; animation:fadeIn .25s cubic-bezier(.22,1,.36,1); }
@keyframes fadeIn { from{opacity:0} to{opacity:1} }

.m-box { 
    background:white; border-radius:14px; overflow:hidden; width:100%; 
    box-shadow:0 24px 48px rgba(26,23,20,.18); max-height:92vh; 
    display:flex; flex-direction:column; 
}
.m-backdrop.open .m-box { animation:modalIn .32s cubic-bezier(.34,1.56,.64,1); }
@keyframes modalIn { 
    from{opacity:0;transform:scale(.9) translateY(-20px)} 
    to{opacity:1;transform:scale(1) translateY(0)} 
}

/* edit modal */
.m-box.md { max-width:400px; }
.m-head { 
    display:flex; align-items:center; justify-content:space-between; 
    padding:1.1rem 1.5rem; border-bottom:1.5px solid #e8e3db; 
    background:#fafbff; flex-shrink:0; 
}
.m-head-l { display:flex; align-items:center; gap:.6rem; }
.m-hic { 
    width:28px; height:28px; border-radius:7px; flex-shrink:0; 
    display:flex; align-items:center; justify-content:center; 
    font-size:.72rem; background:#eff4ff; color:#2a58b5; 
}
.m-head h3 { 
    font-family:'Fraunces',serif; font-size:1rem; 
    font-weight:600; color:#1a1714; margin:0; 
}
.m-close { 
    width:26px; height:26px; border-radius:5px; 
    border:1.5px solid #e8e3db; background:white; 
    color:#9e9690; cursor:pointer; 
    display:flex; align-items:center; justify-content:center; 
    font-size:.85rem; transition:all .15s; 
}
.m-close:hover { 
    border-color:#dc2626; color:#dc2626; background:#fee2e2; 
}
.m-body { padding:1.4rem 1.5rem; overflow-y:auto; flex:1; }
.m-foot { 
    display:flex; justify-content:flex-end; gap:.65rem; 
    padding:1rem 1.5rem; border-top:1.5px solid #e8e3db; 
    background:#fafbff; flex-shrink:0; 
}

/* delete modal */
.m-box.del { max-width:460px; }
.del-inner { padding:2.5rem 2rem 2rem; text-align:center; }
.del-icon { 
    width:64px; height:64px; border-radius:50%; 
    background:#fee2e2; display:flex; align-items:center; 
    justify-content:center; margin:0 auto 1.25rem; 
    border:2px solid #fca5a5; 
}
.del-icon svg { width:28px; height:28px; color:#dc2626; }
.del-title { 
    font-family:'Fraunces',serif; font-size:1.3rem; 
    font-weight:700; color:#1a1714; margin:0 0 .5rem; 
    line-height:1.2; 
}
.del-msg { 
    font-size:.88rem; color:#6b6560; line-height:1.6; 
    margin:0 0 1.75rem; 
}
.del-msg strong { color:#1a1714; font-weight:700; }
.del-actions { display:flex; gap:.65rem; justify-content:center; }

/* modal buttons */
.btn-modal { 
    display:inline-flex; align-items:center; gap:.4rem; 
    padding:.6rem 1.3rem; border-radius:7px; 
    font-family:'DM Sans',sans-serif; font-size:.875rem; 
    font-weight:600; cursor:pointer; border:1.5px solid transparent; 
    transition:all .18s; 
}
.btn-modal-ghost { 
    background:white; border-color:#e8e3db; color:#6b6560; 
}
.btn-modal-ghost:hover { 
    border-color:#2a58b5; color:#2a58b5; background:#f0f5ff; 
}
.btn-modal-submit { 
    background:#1a1714; color:white; border-color:#1a1714; 
}
.btn-modal-submit:hover { 
    background:#2a58b5; border-color:#2a58b5; 
    transform:translateY(-1px); box-shadow:0 4px 12px rgba(42,88,181,.3); 
}
.btn-modal-danger { 
    background:#dc2626; color:white; border-color:#dc2626; 
}
.btn-modal-danger:hover { 
    background:#b91c1c; transform:translateY(-1px); 
    box-shadow:0 4px 12px rgba(220,38,38,.3); 
}

/* Action Buttons */
.act-btn {
    width: 28px; height: 28px; border-radius: 6px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 0.72rem; text-decoration: none; cursor: pointer;
    border: 1.5px solid var(--c-border2); background: var(--c-surface);
    color: var(--c-ink2); transition: all 0.16s; margin-left: 4px;
}
.act-btn:hover { border-color: var(--c-accent); color: var(--c-accent); background: var(--c-accent-bg); }
.act-btn.del:hover { border-color: var(--c-red); color: var(--c-red); background: var(--c-red-bg); }
.act-btn.edit:hover { border-color: var(--c-green); color: var(--c-green); background: var(--c-green-bg); }

</style>

<div class="cat-wrap">
  <div class="cat-inner">

    <!-- Topbar -->
    <div class="cat-topbar">
      <div>
        <div class="cat-eyebrow">Accounts · Configuration</div>
        <div class="cat-page-title">Expense Categories</div>
        <div class="cat-page-sub">Manage cost centres and expense types</div>
      </div>
      <a href="<?= BASE_URL ?>modules/accounts/index.php" class="cat-back">
        ← Back to Dashboard
      </a>
    </div>

    <div class="cat-layout">

      <!-- ── Sidebar: Add Form ─────────────────────────── -->
      <div class="cat-sidebar">

        <div class="cat-card">
          <div class="cat-card-head">
            <div class="cat-card-icon cat-icon-terracotta">＋</div>
            <div>
              <div class="cat-card-title">New Category</div>
              <div class="cat-card-count">Add a new expense type</div>
            </div>
          </div>

          <div class="cat-card-body">

            <?php if (isset($error)): ?>
              <div class="cat-alert cat-alert-error">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="8" r="7"/><path d="M8 5v3M8 11h.01"/></svg>
                <?= htmlspecialchars($error) ?>
              </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
              <div class="cat-alert cat-alert-success">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="8" r="7"/><path d="M5 8l2 2 4-4"/></svg>
                <?= htmlspecialchars($success) ?>
              </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="add_category" value="1">
              <div class="cat-field">
                <label class="cat-label">Category Name <span class="cat-req">*</span></label>
                <input type="text" name="name" class="cat-input"
                       placeholder="e.g. Travel, Utilities, Salaries"
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                       required autofocus>
              </div>

              <div class="cat-field">
                <label class="cat-label">Description</label>
                <textarea name="description" class="cat-textarea"
                          placeholder="Optional details about this category…"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                <div class="cat-help">Provide context for this expense category</div>
              </div>

              <button type="submit" class="cat-submit">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="8" cy="8" r="7"/><path d="M5 8l2 2 4-4"/></svg>
                Add Category
              </button>
            </form>

          </div>
        </div>

        <!-- Info box -->
        <div class="cat-infobox">
          <div class="cat-infobox-head">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="8" r="7"/><path d="M8 7v4M8 5h.01"/></svg>
            Category Guidelines
          </div>
          <p>Create specific categories for better expense tracking. Common examples: Office Supplies, Travel, Utilities, Salaries, and Marketing.</p>
        </div>

      </div>

      <!-- ── Main: Category List ───────────────────────── -->
      <div class="cat-card">
        <div class="cat-card-head">
          <div class="cat-card-icon cat-icon-slate">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
          </div>
          <div>
            <div class="cat-card-title">All Categories</div>
            <div class="cat-card-count">
              <?= count($categories) ?> <?= count($categories) === 1 ? 'category' : 'categories' ?> configured
            </div>
          </div>
        </div>

        <div class="cat-table-wrap">
          <table class="cat-table">
            <thead>
              <tr>
                <th>Category Name</th>
                <th>Description</th>
                <th class="center">Type</th>
                <th class="center">Status</th>
                <th class="center">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($categories)): ?>
                <tr>
                  <td colspan="4">
                    <div class="cat-empty">
                      <div class="cat-empty-icon">📂</div>
                      <h5>No Categories Yet</h5>
                      <p>Create your first category to start organising expenses</p>
                    </div>
                  </td>
                </tr>
              <?php else: ?>
                <?php
                  // Warm palette for avatars
                  $palettes = [
                    ['bg'=>'#fdebd0','fg'=>'#c0622a'],
                    ['bg'=>'#dbeafe','fg'=>'#2563eb'],
                    ['bg'=>'#d1fae5','fg'=>'#065f46'],
                    ['bg'=>'#fce7f3','fg'=>'#9d174d'],
                    ['bg'=>'#ede9fe','fg'=>'#5b21b6'],
                    ['bg'=>'#fef9c3','fg'=>'#854d0e'],
                    ['bg'=>'#e0f2fe','fg'=>'#0369a1'],
                  ];
                  foreach ($categories as $i => $cat):
                    $p = $palettes[$i % count($palettes)];
                    $initial = mb_strtoupper(mb_substr($cat['name'], 0, 1));
                ?>
                  <tr style="animation-delay: <?= $i * 0.04 ?>s">
                    <td>
                      <div class="cat-name-cell">
                        <div class="cat-avatar" style="background:<?= $p['bg'] ?>;color:<?= $p['fg'] ?>">
                          <?= htmlspecialchars($initial) ?>
                        </div>
                        <div class="cat-name-text"><?= htmlspecialchars($cat['name']) ?></div>
                      </div>
                    </td>
                    <td>
                      <div class="cat-desc-text">
                        <?= htmlspecialchars($cat['description'] ?: 'No description provided') ?>
                      </div>
                    </td>
                    <td class="center">
                      <span class="cat-badge cat-badge-type">
                        <?= htmlspecialchars(ucfirst($cat['type'])) ?>
                      </span>
                    </td>
                    <td class="center">
                      <span class="cat-badge cat-badge-active">
                        <span class="cat-badge-dot"></span>
                        Active
                      </span>
                    </td>
                    <td class="center">
                        <div style="display:flex;justify-content:center;">
                            <button type="button" class="act-btn edit" title="Edit" 
                                onclick="openEditModal('<?= $cat['id'] ?>', '<?= htmlspecialchars(addslashes($cat['name'])) ?>', '<?= htmlspecialchars(addslashes($cat['description'])) ?>')">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            <button type="button" class="act-btn del" title="Delete" onclick="openDeleteModal('<?= $cat['id'] ?>', '<?= htmlspecialchars(addslashes($cat['name'])) ?>')">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                            </button>
                        </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php if (!empty($categories)): ?>
          <div class="cat-summary">
            <div class="cat-summary-stat">
              <div class="cat-summary-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
              </div>
              <div>
                <div class="cat-summary-label">Total Categories</div>
                <div class="cat-summary-val"><?= count($categories) ?></div>
              </div>
            </div>
            <a href="<?= BASE_URL ?>modules/accounts/index.php" class="cat-link-btn">
              View Reports →
            </a>
          </div>
        <?php endif; ?>

      </div>
      <!-- end main card -->

    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<!-- Edit Modal -->
<div class="m-backdrop" id="editModal">
    <div class="m-box md">
        <form method="POST">
            <input type="hidden" name="edit_category" value="1">
            <input type="hidden" name="category_id" id="edit_id">
            
            <div class="m-head">
                <div class="m-head-l">
                    <div class="m-hic">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                    </div>
                    <h3>Edit Category</h3>
                </div>
                <button type="button" class="m-close" onclick="closeEditModal()">×</button>
            </div>
            
            <div class="m-body">
                <div class="cat-field">
                    <label class="cat-label">Category Name <span class="cat-req">*</span></label>
                    <input type="text" name="name" id="edit_name" class="cat-input" required>
                </div>

                <div class="cat-field">
                    <label class="cat-label">Description</label>
                    <textarea name="description" id="edit_description" class="cat-textarea"></textarea>
                </div>
            </div>

            <div class="m-foot">
                <button type="button" class="btn-modal btn-modal-ghost" onclick="closeEditModal()">
                    Cancel
                </button>
                <button type="submit" class="btn-modal btn-modal-submit">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5">
                        <circle cx="8" cy="8" r="7"/><path d="M5 8l2 2 4-4"/>
                    </svg>
                    Update Category
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="m-backdrop" id="deleteModal">
    <div class="m-box del">
        <div class="del-inner">
            <div class="del-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            </div>
            
            <h3 class="del-title">Delete Category?</h3>
            <p class="del-msg">
                Are you sure you want to delete 
                <strong id="delete_cat_name"></strong>? 
                This action cannot be undone.
            </p>

            <form method="POST">
                <input type="hidden" name="delete_category" value="1">
                <input type="hidden" name="category_id" id="delete_cat_id">
                
                <div class="del-actions">
                    <button type="button" class="btn-modal btn-modal-ghost" onclick="closeDeleteModal()">
                        Cancel
                    </button>
                    <button type="submit" class="btn-modal btn-modal-danger">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            <line x1="10" y1="11" x2="10" y2="17"/>
                            <line x1="14" y1="11" x2="14" y2="17"/>
                        </svg>
                        Yes, Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
function openEditModal(id, name, description) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_description').value = description;
    document.getElementById('editModal').classList.add('open');
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('open');
}

document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

function openDeleteModal(id, name) {
    document.getElementById('delete_cat_id').value = id;
    document.getElementById('delete_cat_name').textContent = name;
    document.getElementById('deleteModal').classList.add('open');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('open');
}

document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});

// Close modals on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditModal();
        closeDeleteModal();
    }
});
</script>