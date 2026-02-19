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