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
$page_title = 'Compliance & Audit';
$current_page = 'compliance';

// Default to last month
$selected_month = $_GET['month'] ?? date('Y-m', strtotime('last month'));
$start_date = $selected_month . '-01';
$end_date = date('Y-m-t', strtotime($start_date));

// Fetch quick stats for the selected month to show on the dashboard
// 1. Sales (Money In)
$sales_sql = "SELECT COALESCE(SUM(amount), 0) as total_received, 
                     COALESCE(SUM(gst_amount), 0) as gst_output 
              FROM bookings 
              WHERE booking_date BETWEEN ? AND ?";
// Note: Bookings track Agreement Value, but GST is on receipts? 
// Actually, GST liability arises on Invoice or Payment, whichever is earlier. 
// For simplicity in this system, we'll track GST on Payments received nicely if 'gst_amount' is there.
// But wait, the `payments` table has `gst_payment` type? No, `customer_receipt`. 
// Let's rely on `payments` table for actual money movement.

$sales_receipts_sql = "SELECT COALESCE(SUM(amount), 0) as total_received 
                       FROM payments 
                       WHERE payment_type = 'customer_receipt' 
                       AND payment_date BETWEEN ? AND ?";
$sales_stat = $db->query($sales_receipts_sql, [$start_date, $end_date])->fetch();

// 2. Purchases (Money Out - Vendor Bills)
$purchases_sql = "SELECT COALESCE(SUM(basic_amount), 0) as taxable, 
                         COALESCE(SUM(gst_amount), 0) as gst_input,
                         COALESCE(SUM(tds_amount), 0) as tds_deducted
                  FROM contractor_bills 
                  WHERE bill_date BETWEEN ? AND ? AND status != 'rejected'
                  UNION ALL
                  SELECT COALESCE(SUM(total_amount - tax_amount), 0) as taxable,
                         COALESCE(SUM(tax_amount), 0) as gst_input,
                         0 as tds_deducted
                  FROM challans 
                  WHERE challan_date BETWEEN ? AND ? AND status != 'rejected' AND challan_type = 'material'";
// This is a rough approx for the dashboard cards. The actual export will be more detailed.
$purchases_stat = [
    'gst_input' => 0,
    'tds_deducted' => 0
];
// Aggregating complex union is hard in one go without subquery.
// Let's just do a simple query for Vendor Bills + Contractor Bills for the cards.

// Vendor Bills (Material) - We need to check if we have a table for 'bills' or just 'challans'
// We have a 'bills' table.
$vendor_bills_sql = "SELECT COALESCE(SUM(tax_amount), 0) as gst_input 
                     FROM bills 
                     WHERE bill_date BETWEEN ? AND ? AND status != 'rejected'";
$vendor_gst = $db->query($vendor_bills_sql, [$start_date, $end_date])->fetchColumn();

// Contractor Bills (Labour/Service)
$contractor_bills_sql = "SELECT COALESCE(SUM(gst_amount), 0) as gst_input, 
                                COALESCE(SUM(tds_amount), 0) as tds_deducted
                         FROM contractor_bills 
                         WHERE bill_date BETWEEN ? AND ? AND status != 'rejected'";
$contractor_stat = $db->query($contractor_bills_sql, [$start_date, $end_date])->fetch();

$total_gst_input = $vendor_gst + $contractor_stat['gst_input'];
$total_tds = $contractor_stat['tds_deducted'];

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-wrap">
    
    <!-- ── Header ────────────────────────────── -->
    <div style="display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:2rem;">
        <div>
            <div class="eyebrow">CA & Tax</div>
            <h1>Audit <em>Exports</em></h1>
        </div>
        
        <!-- Month Filter -->
        <form method="GET" style="display:flex; align-items:center; gap:0.5rem; background:white; padding:0.5rem; border-radius:8px; border:1px solid var(--border);">
            <label for="month" style="font-size:0.8rem; font-weight:600; color:var(--ink-soft); padding-left:0.5rem;">Period:</label>
            <input type="month" id="month" name="month" value="<?= $selected_month ?>" 
                   style="border:none; outline:none; font-family:'DM Sans'; font-weight:600; color:var(--ink); cursor:pointer;"
                   onchange="this.form.submit()">
        </form>
    </div>

    <!-- ── Stats Overview ────────────────────── -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="s-icon ico-green"><i class="fas fa-hand-holding-usd"></i></div>
            <div class="stat-content">
                <div class="stat-label">Total Collections</div>
                <div class="stat-value"><?= formatCurrencyShort($sales_stat['total_received']) ?></div>
                <div class="stat-sub">Money In (<?= date('M Y', strtotime($start_date)) ?>)</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="s-icon ico-blue"><i class="fas fa-file-invoice-dollar"></i></div>
            <div class="stat-content">
                <div class="stat-label">Input GST (Approx)</div>
                <div class="stat-value"><?= formatCurrencyShort($total_gst_input) ?></div>
                <div class="stat-sub">Vendor + Contractor Bills</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="s-icon ico-purple"><i class="fas fa-cut"></i></div>
            <div class="stat-content">
                <div class="stat-label">TDS Deducted</div>
                <div class="stat-value"><?= formatCurrencyShort($total_tds) ?></div>
                <div class="stat-sub">Liability to Deposit</div>
            </div>
        </div>
    </div>

    <!-- ── Download Section ──────────────────── -->
    <div class="chart-card" style="max-width: 600px; margin: 0 auto; text-align: center; padding: 3rem 2rem;">
        <div style="font-size: 3rem; color: var(--accent); margin-bottom: 1.5rem;">
            <i class="fas fa-file-archive"></i>
        </div>
        <h2 style="font-family: 'Fraunces', serif; margin-bottom: 0.5rem;">Download Monthly Audit Pack</h2>
        <p style="color: var(--ink-soft); margin-bottom: 2rem;">
            This will generate a ZIP file containing Excel sheets for <strong>Sales (GSTR-1)</strong>, 
            <strong>Purchases (ITC)</strong>, <strong>TDS Liability</strong>, and <strong>General Expenses</strong> for 
            <span style="font-weight:700; color:var(--ink);"><?= date('F Y', strtotime($start_date)) ?></span>.
        </p>

        <a href="export_ca.php?month=<?= $selected_month ?>" class="btn-download-pack">
            <i class="fas fa-download"></i> Download Audit Pack
        </a>
    </div>

</div>

<style>
    .page-wrap { max-width: 1000px; margin: 2.5rem auto; padding: 0 1.5rem 4rem; }
    .eyebrow { font-size: 0.68rem; font-weight: 700; letter-spacing: 0.15em; text-transform: uppercase; color: var(--accent); margin-bottom: 0.3rem; }
    h1 { font-family: 'Fraunces', serif; font-size: 1.7rem; font-weight: 700; margin: 0; color: var(--ink); }
    h1 em { color: var(--accent); font-style: italic; }

    .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 3rem; }
    @media (max-width: 768px) { .stats-grid { grid-template-columns: 1fr; } }
    
    .stat-card { background: white; border: 1.5px solid var(--border); border-radius: 12px; padding: 1.5rem; display: flex; align-items: center; gap: 1.25rem; }
    .s-icon { width: 48px; height: 48px; border-radius: 11px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
    .ico-green { background: #ecfdf5; color: #059669; }
    .ico-blue { background: #eff6ff; color: #3b82f6; }
    .ico-purple { background: #f3e8ff; color: #9333ea; }
    
    .stat-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: var(--ink-soft); letter-spacing: 0.05em; margin-bottom: 0.3rem; }
    .stat-value { font-family: 'Fraunces', serif; font-size: 1.6rem; font-weight: 700; color: var(--ink); line-height: 1; }
    .stat-sub { font-size: 0.75rem; color: var(--ink-mute); margin-top: 0.3rem; }
    
    .chart-card { background: white; border: 1.5px solid var(--border); border-radius: 14px; position: relative; overflow: hidden; }
    
    /* Premium Download Button */
    .btn-download-pack {
        display: inline-flex; align-items: center; gap: 0.75rem;
        padding: 1rem 2rem; 
        background: var(--ink); color: white;
        border-radius: 12px; text-decoration: none;
        font-family: 'DM Sans', sans-serif; font-size: 1.05rem; font-weight: 700;
        transition: all 0.25s cubic-bezier(0.2, 0.8, 0.2, 1);
        box-shadow: 0 4px 12px rgba(26, 23, 20, 0.15);
        border: 2px solid var(--ink);
    }
    .btn-download-pack:hover {
        background: var(--accent); border-color: var(--accent);
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(42, 88, 181, 0.25);
        color: white;
    }
    .btn-download-pack i { transition: transform 0.25s; }
    .btn-download-pack:hover i { transform: translateY(2px); }
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
