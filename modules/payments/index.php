<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/ColorHelper.php';
require_once __DIR__ . '/../../includes/EmailService.php';
require_once __DIR__ . '/../../includes/pdf_excel_helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'accountant']);

$db = Database::getInstance();
$page_title = 'Payments';
$current_page = 'payments';

// Fetch active bank accounts
require_once __DIR__ . '/../../includes/MasterService.php';
$masterService = new MasterService();
$bankAccounts = $masterService->getActiveBankAccounts();

// Handle payment operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
         setFlashMessage('error', 'Security token expired. Please reload and try again.');
         redirect('modules/payments/index.php');
    }

    $action = $_POST['action'] ?? '';
    
    if ($action === 'make_payment') {
        $payment_type = $_POST['payment_type'];
        $reference_id = intval($_POST['reference_id']);
        $party_id = intval($_POST['party_id']);
        $payment_date = $_POST['payment_date'];
        $amount = round((float)$_POST['payment_amount_final'], 2);
        $payment_mode = $_POST['payment_mode'];
        $reference_no = sanitize($_POST['reference_no']);
        $reference_no = sanitize($_POST['reference_no']);
        $remarks = sanitize($_POST['remarks']);
        $company_account_id = !empty($_POST['company_account_id']) ? intval($_POST['company_account_id']) : null;
        
        $db->beginTransaction();
        try {
            if ($payment_type === 'vendor_payment') {
                $bills = $db->query("SELECT id, amount, paid_amount FROM bills WHERE party_id = ? AND payment_status != 'paid' ORDER BY bill_date ASC FOR UPDATE", [$party_id])->fetchAll();
                $remaining_payment = $amount;
                $payments_made = 0;

                foreach ($bills as $bill) {
                    if ($remaining_payment <= 0) break;
                    $pending = round($bill['amount'] - $bill['paid_amount'], 2);
                    if ($pending <= 0.01) continue; 
                    $allocate = round(min($pending, $remaining_payment), 2);
                    if ($allocate > $remaining_payment) $allocate = $remaining_payment;

                    if ($allocate > 0) {
                        $payment_data = [
                            'payment_type' => 'vendor_bill_payment',
                            'reference_type' => 'bill',
                            'reference_id' => $bill['id'],
                            'party_id' => $party_id,
                            'payment_date' => $payment_date,
                            'amount' => $allocate,
                            'payment_mode' => $payment_mode,
                            'reference_no' => $reference_no,
                            'remarks' => $remarks . " (Allocated from global payment)",
                            'company_account_id' => $company_account_id,
                            'created_by' => $_SESSION['user_id']
                        ];
                        $pid = $db->insert('payments', $payment_data);
                        logAudit('create', 'payments', $pid, null, $payment_data);
                        updateBillPaidAmount($bill['id']);
                        $remaining_payment = round($remaining_payment - $allocate, 2);
                        $payments_made++;
                    }
                }
                
                if ($payments_made === 0 && $amount > 0) {
                    throw new Exception("No pending bills found to allocate this payment.");
                }
                if ($remaining_payment > 1.00) {
                     throw new Exception("Payment amount exceeds total pending bills. Excess: " . $remaining_payment);
                }

            } elseif ($payment_type === 'contractor_account_payment') {
                $bills = $db->query("SELECT id, total_payable, paid_amount FROM contractor_bills WHERE contractor_id = ? AND paid_amount < total_payable AND status != 'rejected' ORDER BY bill_date ASC FOR UPDATE", [$party_id])->fetchAll();
                $remaining_payment = $amount;
                $payments_made = 0;

                foreach ($bills as $bill) {
                    if ($remaining_payment <= 0) break;
                    $pending = round($bill['total_payable'] - $bill['paid_amount'], 2);
                    if ($pending <= 0.01) continue; 
                    $allocate = round(min($pending, $remaining_payment), 2);
                    if ($allocate > $remaining_payment) $allocate = $remaining_payment;

                    if ($allocate > 0) {
                        $payment_data = [
                            'payment_type' => 'contractor_payment', 
                            'reference_type' => 'contractor_bill',
                            'reference_id' => $bill['id'],
                            'party_id' => $party_id,
                            'payment_date' => $payment_date,
                            'amount' => $allocate,
                            'payment_mode' => $payment_mode,
                            'reference_no' => $reference_no,
                            'remarks' => $remarks . " (Allocated from global payment)",
                            'company_account_id' => $company_account_id,
                            'created_by' => $_SESSION['user_id']
                        ];
                        $pid = $db->insert('payments', $payment_data);
                        logAudit('create', 'payments', $pid, null, $payment_data);
                        updateContractorBillPaidAmount($bill['id']); 
                        $remaining_payment = round($remaining_payment - $allocate, 2);
                        $payments_made++;
                    }
                }
                
                if ($payments_made === 0 && $amount > 0) {
                    throw new Exception("No pending contractor bills found to allocate this payment.");
                }
                if ($remaining_payment > 1.00) {
                     throw new Exception("Payment amount exceeds total pending bills. Excess: " . $remaining_payment);
                }

            } else {
                if ($payment_type === 'vendor_bill_payment') {
                    $bill = $db->query("SELECT amount, paid_amount FROM bills WHERE id = ? FOR UPDATE", [$reference_id])->fetch();
                    if (!$bill) throw new Exception("Bill not found.");
                    $pending = round($bill['amount'] - $bill['paid_amount'], 2);
                    if ($amount > $pending + 1.00) {
                         throw new Exception("Payment amount ($amount) exceeds pending bill amount ($pending).");
                    }
                } elseif ($payment_type === 'contractor_payment' || $payment_type === 'challan_payment') {
                    $bill = $db->query("SELECT total_payable, paid_amount FROM contractor_bills WHERE id = ? FOR UPDATE", [$reference_id])->fetch();
                    if (!$bill) throw new Exception("Contractor Bill not found.");
                    $pending = round($bill['total_payable'] - $bill['paid_amount'], 2);
                    if ($amount > $pending + 1.00) {
                        throw new Exception("Payment amount ($amount) exceeds pending bill amount ($pending).");
                    }
                } elseif ($payment_type === 'customer_receipt') {
                    $booking = $db->query("SELECT total_pending, agreement_value, total_received FROM bookings WHERE id = ? FOR UPDATE", [$reference_id])->fetch();
                    if (!$booking) throw new Exception("Booking not found.");
                } elseif (in_array($payment_type, ['gst_payment', 'tds_payment', 'tax_refund'])) {
                    // Tax payments don't necessarily have a reference ID or limit
                    // But we might want to ensure amount > 0
                    if ($amount <= 0) throw new Exception("Amount must be greater than 0");
                }

                $payment_data = [
                    'payment_type' => $payment_type,
                    'reference_type' => $payment_type === 'customer_receipt' ? 'booking' : ($payment_type === 'vendor_bill_payment' ? 'bill' : ($payment_type === 'contractor_payment' ? 'contractor_bill' : null)),
                    'reference_id' => $reference_id,
                    'demand_id' => !empty($_POST['demand_id']) && $payment_type === 'customer_receipt' ? intval($_POST['demand_id']) : null,
                    'party_id' => $party_id,
                    'payment_date' => $payment_date,
                    'amount' => $amount,
                    'payment_mode' => $payment_mode,
                    'reference_no' => $reference_no,
                    'remarks' => $remarks,
                    'company_account_id' => $company_account_id,
                    'created_by' => $_SESSION['user_id']
                ];
                
                $payment_id = $db->insert('payments', $payment_data);
                
                if ($payment_type === 'customer_receipt') {
                    updateBookingTotals($reference_id);
                } elseif ($payment_type === 'vendor_bill_payment') {
                    updateBillPaidAmount($reference_id);
                } elseif ($payment_type === 'contractor_payment') {
                    updateContractorBillPaidAmount($reference_id);
                }
                
                logAudit('create', 'payments', $payment_id, null, $payment_data);

                // ── Notification Trigger ──
                require_once __DIR__ . '/../../includes/NotificationService.php';
                $ns = new NotificationService();
                $notifTitle = ""; 
                $notifMsg   = "";
                $notifLink  = BASE_URL . "modules/payments/index.php";

                // We can fetch party name for better message, or just use ID/Amount
                // Fetch party name for clearer notification
                $partyName = $db->query("SELECT name FROM parties WHERE id = ?", [$party_id])->fetchColumn() ?: "Party #$party_id";
                $formattedAmount = number_format($amount, 2);

                if ($payment_type === 'customer_receipt') {
                    $notifTitle = "Payment Received";
                    $notifMsg   = "Received ₹{$formattedAmount} from Customer: {$partyName}";
                    $notifLink  = BASE_URL . "modules/booking/view.php?id=" . $reference_id; // Link to booking
                    
                    $custQuery = $db->query("SELECT name, email FROM parties WHERE id = ?", [$party_id])->fetch();
                    if ($custQuery && !empty($custQuery['email'])) {
                        $bk = $db->query("SELECT total_pending FROM bookings WHERE id = ?", [$reference_id])->fetch();
                        $paymentDetails = [
                            'amount' => $amount,
                            'remaining_balance' => $bk ? $bk['total_pending'] : 0
                        ];
                        
                        // Generate PDF specifically as a string
                        $pdfResult = generatePaymentReceipt($payment_id);
                        $pdfContent = null;
                        $pdfFilename = null;
                        if ($pdfResult && $pdfResult['success']) {
                            $pdfContent = $pdfResult['content'];
                            $pdfFilename = $pdfResult['filename'];
                        }
                        
                        EmailService::sendInstallmentReceipt($custQuery['email'], $custQuery['name'], $paymentDetails, $pdfContent, $pdfFilename);
                    }
                } elseif (strpos($payment_type, 'vendor') !== false) {
                    $notifTitle = "Vendor Payment Made";
                    $notifMsg   = "Paid ₹{$formattedAmount} to Vendor: {$partyName}";
                    $notifLink  = BASE_URL . "modules/payments/index.php?tab=vendor";
                } elseif (strpos($payment_type, 'contractor') !== false) {
                    $notifTitle = "Contractor Payment Made";
                    $notifMsg   = "Paid ₹{$formattedAmount} to Contractor: {$partyName}";
                    $notifLink  = BASE_URL . "modules/payments/index.php?tab=contractor";
                }

                if ($notifTitle) {
                    // Notify Admin + Finance Team
                    $ns->notifyUsersWithPermission('finance', $notifTitle, $notifMsg . " (Recorded by " . $_SESSION['username'] . ")", 'info', $notifLink);
                }
            } // Restore missing brace here

            $db->commit();
            setFlashMessage('success', 'Payment recorded successfully');
            
            $activeTab = 'customer';
            if (in_array($payment_type, ['vendor_payment', 'vendor_bill_payment'])) {
                $activeTab = 'vendor';
            } elseif ($payment_type === 'contractor_payment' || $payment_type === 'contractor_account_payment') {
                $activeTab = 'contractor';
            } elseif (in_array($payment_type, ['gst_payment', 'tds_payment', 'tax_refund'])) {
                $activeTab = 'tax';
            }
            
            $redirect_url = $_POST['redirect_url']?? "modules/payments/index.php?tab={$activeTab}";
            redirect($redirect_url);
            
        } catch (Exception $e) {
            $db->rollback();
            setFlashMessage('error', 'Failed to record payment: ' . $e->getMessage());
            
            $activeTab = 'customer';
            if (in_array($payment_type, ['vendor_payment', 'vendor_bill_payment'])) {
                $activeTab = 'vendor';
            } elseif ($payment_type === 'contractor_payment' || $payment_type === 'contractor_account_payment') {
                $activeTab = 'contractor';
            } elseif (in_array($payment_type, ['gst_payment', 'tds_payment', 'tax_refund'])) {
                $activeTab = 'tax';
            }

            $redirect_url = $_POST['redirect_url']?? "modules/payments/index.php?tab={$activeTab}";
            redirect($redirect_url);
        }
    }
}

// Get filter values
$payment_type_filter = $_GET['type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

try {
    $pending_bookings = $db->query("SELECT b.id, b.booking_date, b.agreement_value, b.total_received, b.total_pending,
                                            f.flat_no, p.name as customer_name, p.id as party_id, pr.project_name, pr.id as project_id, p.id as customer_id
                                     FROM bookings b
                                     JOIN flats f ON b.flat_id = f.id
                                     JOIN parties p ON b.customer_id = p.id
                                     JOIN projects pr ON b.project_id = pr.id
                                     WHERE b.total_pending > 0 AND b.status = 'active'
                                     ORDER BY b.booking_date DESC")->fetchAll();

    $pending_vendor_bills = $db->query("SELECT b.id, b.bill_no, b.bill_date, b.amount as total_amount, b.paid_amount, (b.amount - b.paid_amount) as pending_amount,
                                          p.name as vendor_name, p.id as party_id, 
                                          (SELECT GROUP_CONCAT(c2.challan_no SEPARATOR ', ') FROM challans c2 WHERE c2.bill_id = b.id) as challan_no
                                   FROM bills b
                                   JOIN parties p ON b.party_id = p.id
                                   WHERE b.status != 'rejected' AND b.payment_status != 'paid' AND (b.amount - b.paid_amount) > 0
                                   ORDER BY b.bill_date")->fetchAll();

    $pending_contractor_bills = $db->query("SELECT c.id, c.bill_no as challan_no, c.bill_date as challan_date, c.total_payable as final_payable_amount, c.basic_amount as total_amount, c.paid_amount, c.pending_amount,
                                                  p.name as contractor_name, p.id as party_id, pr.project_name, pr.id as project_id
                                           FROM contractor_bills c
                                           JOIN parties p ON c.contractor_id = p.id
                                           JOIN projects pr ON c.project_id = pr.id
                                           WHERE c.pending_amount > 0 AND c.status = 'approved' AND c.payment_status != 'paid'
                                           ORDER BY c.bill_date")->fetchAll();

} catch (Exception $e) {
    die("Error fetching data: " . $e->getMessage());
}

// Fetch recent payments with filters
$where = '1=1';
$params = [];

if ($payment_type_filter) {
    $where .= ' AND p.payment_type = ?';
    $params[] = $payment_type_filter;
}
if ($date_from) {
    $where .= ' AND p.payment_date >= ?';
    $params[] = $date_from;
}
if ($date_to) {
    $where .= ' AND p.payment_date <= ?';
    $params[] = $date_to;
}

$sql = "SELECT p.*, 
               pt.name as party_name,
               u.full_name as created_by_name,
               bd.stage_name as demand_stage
        FROM payments p
        LEFT JOIN parties pt ON p.party_id = pt.id
        LEFT JOIN users u ON p.created_by = u.id
        LEFT JOIN booking_demands bd ON p.demand_id = bd.id
        WHERE $where
        ORDER BY p.created_at DESC
        LIMIT 50";

$stmt = $db->query($sql, $params);
$payments = $stmt->fetchAll();

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
    .pay-wrap { max-width: 1280px; margin: 2.5rem auto; padding: 0 1.5rem 4rem; }

    /* ── Header ──────────────────────────────── */
    .pay-header {
        margin-bottom: 2rem; padding-bottom: 1.5rem;
        border-bottom: 1.5px solid var(--border);
    }

    .pay-header .eyebrow {
        font-size: 0.68rem; font-weight: 700; letter-spacing: 0.15em;
        text-transform: uppercase; color: var(--accent); margin-bottom: 0.3rem;
    }
    .pay-header h1 {
        font-family: 'Fraunces', serif; font-size: 1.7rem; font-weight: 700;
        line-height: 1.1; color: var(--ink); margin: 0;
    }
    .pay-header h1 em { color: var(--accent); font-style: italic; }

    /* ── Tabs ────────────────────────────────── */
    .tab-bar {
        display: flex; gap: 0.5rem; flex-wrap: wrap;
        border-bottom: 1.5px solid var(--border);
        padding-bottom: 0.25rem; margin-bottom: 1.75rem;
    }

    .tab-btn {
        display: flex; align-items: center; gap: 0.5rem;
        padding: 0.7rem 1.1rem; background: transparent;
        border: none; border-bottom: 2.5px solid transparent;
        font-size: 0.85rem; font-weight: 600; color: var(--ink-mute);
        cursor: pointer; transition: all 0.18s;
    }
    .tab-btn:hover { color: var(--accent); }
    .tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); }

    .tab-badge {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 20px; height: 20px; padding: 0 0.4rem;
        background: #f0ece5; color: var(--ink-soft);
        border-radius: 10px; font-size: 0.68rem; font-weight: 700;
    }
    .tab-btn.active .tab-badge { background: var(--accent-bg); color: var(--accent); }

    .tab-content { display: none; }
    .tab-content.active { display: block; }

    /* ── Main Panel ──────────────────────────── */
    .pay-panel {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 14px; overflow: hidden;
        animation: fadeUp 0.4s ease both;
    }

    /* ── Table ───────────────────────────────── */
    .pay-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }

    .pay-table thead tr { background: #fdfcfa; border-bottom: 1.5px solid var(--border); }
    .pay-table thead th {
        padding: 0.7rem 1rem; text-align: left;
        font-size: 0.64rem; font-weight: 700; letter-spacing: 0.1em;
        text-transform: uppercase; color: var(--ink-soft); white-space: nowrap;
    }
    .pay-table thead th.th-c { text-align: center; }
    .pay-table thead th.th-r { text-align: right; }

    .pay-table tbody tr { border-bottom: 1px solid var(--border-lt); transition: background 0.13s; }
    .pay-table tbody tr:last-child { border-bottom: none; }
    .pay-table tbody tr:hover { background: #fdfcfa; }

    .pay-table td { padding: 0.8rem 1rem; vertical-align: middle; }
    .pay-table td.td-c { text-align: center; }
    .pay-table td.td-r { text-align: right; }

    /* Avatar */
    .av-sq {
        width: 28px; height: 28px; border-radius: 7px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.7rem; font-weight: 700; color: white;
        margin-right: 0.65rem; flex-shrink: 0;
    }
    .av-circ {
        width: 28px; height: 28px; border-radius: 50%;
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
    .pill.blue   { background: #eff6ff; color: #1e40af; }
    .pill.green  { background: #ecfdf5; color: #065f46; }
    .pill.orange { background: var(--accent-lt); color: var(--accent); }
    .pill.gray   { background: #f0ece5; color: var(--ink-soft); }

    /* Empty state */
    .empty-state {
        padding: 4rem 1rem; text-align: center;
    }
    .empty-state i {
        font-size: 2.5rem; color: #10b981;
        margin-bottom: 0.75rem; display: block;
    }
    .empty-state h4 {
        font-size: 1rem; font-weight: 700; color: var(--ink-soft);
        margin: 0 0 0.35rem;
    }
    .empty-state p {
        font-size: 0.82rem; color: var(--ink-mute); margin: 0;
    }

    /* ── Filter Section ──────────────────────── */
    .filter-section {
        padding: 1.25rem 1.5rem; border-bottom: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }

    .filter-form { display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap; }

    .f-input, .f-select {
        height: 38px; padding: 0 0.75rem;
        border: 1.5px solid var(--border); border-radius: 7px;
        font-size: 0.82rem; color: var(--ink); background: white;
        outline: none; transition: border-color 0.15s;
    }
    .f-input { flex: 0 0 160px; }
    .f-select {
        flex: 0 0 180px; -webkit-appearance: none; appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 0.6rem center;
        padding-right: 2rem;
    }
    .f-input:focus, .f-select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(181,98,42,0.1); }

    .btn-go, .btn-reset {
        height: 38px; padding: 0 1.25rem; border: none; border-radius: 7px;
        display: flex; align-items: center; gap: 0.4rem;
        font-size: 0.8rem; font-weight: 600; cursor: pointer;
        transition: all 0.18s; text-decoration: none;
    }
    .btn-go { background: var(--ink); color: white; }
    .btn-go:hover { background: var(--accent); }
    .btn-reset { background: #f0ece5; color: var(--ink-soft); }
    .btn-reset:hover { background: var(--border); color: var(--ink); }

    /* ── Action Buttons ──────────────────────── */
    .btn-collect {
        display: inline-flex; align-items: center; gap: 0.4rem;
        padding: 0.5rem 1rem; border: 1.5px solid var(--border);
        background: var(--ink); color: white;
        border-radius: 7px; font-size: 0.78rem; font-weight: 600;
        cursor: pointer; transition: all 0.18s; text-decoration: none;
    }
    .btn-collect:hover { background: var(--accent); border-color: var(--accent); color: white; }

    .act-btn {
        width: 28px; height: 28px; border-radius: 6px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.72rem; text-decoration: none; cursor: pointer;
        border: 1.5px solid var(--border); background: var(--surface);
        color: var(--ink-soft); transition: all 0.16s;
    }
    .act-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }

    /* ── Modals ──────────────────────────────── */
    .pay-modal-backdrop {
        display: none; position: fixed; inset: 0; z-index: 10000;
        background: rgba(26,23,20,0.5); backdrop-filter: blur(3px);
        align-items: center; justify-content: center; padding: 1rem;
    }
    .pay-modal-backdrop.open { display: flex; }

    .pay-modal {
        background: white; border-radius: 16px; overflow: hidden;
        width: 100%; max-width: 580px;
        box-shadow: 0 25px 50px rgba(26,23,20,0.2);
        animation: modalIn 0.25s ease;
        display: flex; flex-direction: column;
        max-height: 90vh; /* Prevent overflow */
    }
    
    .pay-modal form {
        display: flex;
        flex-direction: column;
        height: 100%;
        overflow: hidden;
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

    .modal-body { padding: 1.75rem 1.6rem; overflow-y: auto; flex: 1; }

    .modal-footer {
        display: flex; justify-content: flex-end; gap: 0.65rem;
        padding: 1.25rem 1.6rem; border-top: 1.5px solid var(--border-lt);
        background: #fdfcfa;
    }

    /* Summary box */
    .sum-box {
        background: #ecfdf5; border: 1.5px solid #a7f3d0;
        border-radius: 10px; padding: 1.15rem;
        margin-bottom: 1.5rem;
    }
    .sum-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 0.4rem 0;
    }
    .sum-row .lbl { font-size: 0.8rem; font-weight: 600; color: #065f46; }
    .sum-row .val { font-weight: 700; color: #065f46; font-size: 0.9rem; }

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

    .calc-box {
        background: #fdfcfa; border: 1px solid var(--border-lt);
        border-radius: 8px; padding: 0.85rem 1rem;
        display: flex; justify-content: space-between; align-items: center;
        margin-top: 1.25rem;
    }
    .calc-box .lbl { font-size: 0.8rem; font-weight: 600; color: var(--ink-soft); }
    .calc-box .val { font-family: 'Fraunces', serif; font-size: 1.1rem; font-weight: 700; color: var(--ink); }

    .warn-text {
        color: #ef4444; font-size: 0.75rem; margin-top: 0.5rem;
        display: none; text-align: right; font-weight: 600;
    }
    .warn-text.show { display: block; }

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

    /* Animations */
    @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
</style>

<div class="pay-wrap">

    <!-- Header -->
    <div class="pay-header">
        <div class="eyebrow">Transaction Management</div>
        <h1>Payment <em>Management</em></h1>
    </div>

    <!-- Tabs -->
    <div class="tab-bar">
        <button class="tab-btn active" onclick="switchTab('customer')">
            <i class="fas fa-user-circle"></i> Customer Receipts
            <span class="tab-badge"><?= count($pending_bookings) ?></span>
        </button>
        <button class="tab-btn" onclick="switchTab('vendor')">
            <i class="fas fa-truck"></i> Vendor Bills
            <span class="tab-badge"><?= count($pending_vendor_bills) ?></span>
        </button>
        <button class="tab-btn" onclick="switchTab('contractor')">
            <i class="fas fa-hard-hat"></i> Contractor Bills
            <span class="tab-badge"><?= count($pending_contractor_bills) ?></span>
        </button>
        <button class="tab-btn" onclick="switchTab('tax')">
            <i class="fas fa-university"></i> Taxes & Refunds
        </button>
        <button class="tab-btn" onclick="switchTab('history')">
            <i class="fas fa-history"></i> Payment History
        </button>
    </div>

    <!-- Customer Receipts Tab -->
    <div id="customer-tab" class="tab-content active">
        <div class="pay-panel">
            <?php if (empty($pending_bookings)): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <h4>All customer payments are up to date!</h4>
                    <p>No pending payments at this time. 🎉</p>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto">
                    <table class="pay-table">
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th class="th-c">Flat</th>
                                <th>Customer</th>
                                <th class="th-c">Booking Date</th>
                                <th class="th-r">Agreement</th>
                                <th class="th-r">Received</th>
                                <th class="th-r">Pending</th>
                                <th class="th-r">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            foreach ($pending_bookings as $booking): 
                                $color = ColorHelper::getProjectColor($booking['project_id']);
                                $initial = ColorHelper::getInitial($booking['project_name']);
                                $custColor = ColorHelper::getCustomerColor($booking['customer_id'] ?? 0);
                                $custInitial = ColorHelper::getInitial($booking['customer_name']);
                            ?>
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center">
                                        <?= renderProjectBadge($booking['project_name'], $booking['project_id']) ?>
                                    </div>
                                </td>
                                <td class="td-c"><span class="pill blue"><?= htmlspecialchars($booking['flat_no']) ?></span></td>
                                <td>
                                    <div style="display:flex;align-items:center">
                                        <span style="font-weight:600;font-size:0.875rem"><?= htmlspecialchars($booking['customer_name']) ?></span>
                                    </div>
                                </td>
                                <td class="td-c"><span style="font-weight:600;color:var(--ink-soft);font-size:0.82rem"><?= formatDate($booking['booking_date']) ?></span></td>
                                <td class="td-r"><strong style="color:var(--ink)"><?= formatCurrencyShort($booking['agreement_value']) ?></strong></td>
                                <td class="td-r"><span style="font-weight:600;color:#10b981"><?= formatCurrencyShort($booking['total_received']) ?></span></td>
                                <td class="td-r"><span style="font-weight:600;color:#f59e0b"><?= formatCurrencyShort($booking['total_pending']) ?></span></td>
                                <td class="td-r">
                                    <button class="btn-collect" onclick="showPaymentModal('customer_receipt', <?= $booking['id'] ?>, <?= $booking['party_id'] ?>, <?= $booking['total_pending'] ?>, '<?= htmlspecialchars(addslashes($booking['customer_name'])) ?>')">
                                        <i class="fas fa-plus"></i> Collect
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Vendor Bills Tab -->
    <div id="vendor-tab" class="tab-content">
        <div class="pay-panel">
            <?php if (empty($pending_vendor_bills)): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <h4>No pending vendor bills</h4>
                    <p>All vendor payments are settled.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto">
                    <table class="pay-table">
                        <thead>
                            <tr>
                                <th>Vendor</th>
                                <th class="th-c">Bill No</th>
                                <th class="th-c">Date</th>
                                <th class="th-c">Challan No</th>
                                <th class="th-r">Total</th>
                                <th class="th-r">Paid</th>
                                <th class="th-r">Pending</th>
                                <th class="th-r">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            foreach ($pending_vendor_bills as $bill): 
                                $vendorColor = ColorHelper::getCustomerColor($bill['vendor_name']);
                                $vendorInitial = ColorHelper::getInitial($bill['vendor_name']);
                            ?>
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center">
                                        <span style="font-weight:600;font-size:0.875rem"><?= htmlspecialchars($bill['vendor_name']) ?></span>
                                    </div>
                                </td>
                                <td class="td-c"><strong style="font-size:0.82rem"><?= htmlspecialchars($bill['bill_no']) ?></strong></td>
                                <td class="td-c"><span style="font-weight:600;color:var(--ink-soft);font-size:0.82rem"><?= formatDate($bill['bill_date']) ?></span></td>
                                <td class="td-c">
                                    <?php if($bill['challan_no']): ?>
                                        <span class="pill blue"><?= htmlspecialchars($bill['challan_no']) ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--border)">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="td-r"><strong style="color:var(--ink)"><?= formatCurrencyShort($bill['total_amount']) ?></strong></td>
                                <td class="td-r"><span style="font-weight:600;color:#10b981"><?= formatCurrencyShort($bill['paid_amount']) ?></span></td>
                                <td class="td-r"><span style="font-weight:600;color:#f59e0b"><?= formatCurrencyShort($bill['pending_amount']) ?></span></td>
                                <td class="td-r">
                                    <button class="btn-collect" onclick="showPaymentModal('vendor_bill_payment', <?= $bill['id'] ?>, <?= $bill['party_id'] ?>, <?= $bill['pending_amount'] ?>, '<?= htmlspecialchars(addslashes($bill['vendor_name'])) ?>')">
                                        <i class="fas fa-file-invoice-dollar"></i> Pay
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Contractor Payments Tab -->
    <div id="contractor-tab" class="tab-content">
        <div class="pay-panel">
            <?php if (empty($pending_contractor_bills)): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <h4>No pending contractor bills</h4>
                    <p>All contractor payments are settled.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto">
                    <table class="pay-table">
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Contractor</th>
                                <th class="th-c">Bill No</th>
                                <th class="th-c">Date</th>
                                <th class="th-r">Payable</th>
                                <th class="th-r">Paid</th>
                                <th class="th-r">Pending</th>
                                <th class="th-r">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            foreach ($pending_contractor_bills as $challan): 
                                $color = ColorHelper::getProjectColor($challan['project_id']);
                                $initial = ColorHelper::getInitial($challan['project_name']);
                                $conColor = ColorHelper::getCustomerColor($challan['party_id']);
                                $conInitial = ColorHelper::getInitial($challan['contractor_name']);
                                
                                $payable = $challan['final_payable_amount'] > 0 ? $challan['final_payable_amount'] : $challan['total_amount'];
                            ?>
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center">
                                        <?= renderProjectBadge($challan['project_name'], $challan['project_id']) ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="display:flex;align-items:center">
                                        <span style="font-weight:600;font-size:0.875rem"><?= htmlspecialchars($challan['contractor_name']) ?></span>
                                    </div>
                                </td>
                                <td class="td-c"><strong style="font-size:0.82rem"><?= htmlspecialchars($challan['challan_no']) ?></strong></td>
                                <td class="td-c"><span style="font-weight:600;color:var(--ink-soft);font-size:0.82rem"><?= formatDate($challan['challan_date']) ?></span></td>
                                <td class="td-r"><strong style="color:var(--ink)"><?= formatCurrencyShort($payable) ?></strong></td>
                                <td class="td-r"><span style="font-weight:600;color:#10b981"><?= formatCurrencyShort($challan['paid_amount']) ?></span></td>
                                <td class="td-r"><span style="font-weight:600;color:#f59e0b"><?= formatCurrencyShort($challan['pending_amount']) ?></span></td>
                                <td class="td-r">
                                    <button class="btn-collect" onclick="showPaymentModal('contractor_payment', <?= $challan['id'] ?>, <?= $challan['party_id'] ?>, <?= $challan['pending_amount'] ?>, '<?= htmlspecialchars(addslashes($challan['contractor_name'])) ?>')">
                                        <i class="fas fa-hand-holding-usd"></i> Pay
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Tax Payments Tab -->
    <div id="tax-tab" class="tab-content">
        <div class="pay-panel">
            <div style="padding: 2rem; display: flex; flex-direction: column; gap: 20px; align-items: center; text-align: center;">
                 <div style="max-width: 600px;">
                    <h3>Tax Payments & Refunds</h3>
                    <p style="color: var(--ink-soft); margin-bottom: 20px;">Record direct payments to government authorities (GST/TDS) or tax refunds received.</p>
                    
                    <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                        <button class="btn btn-primary" onclick="showTaxModal('gst_payment')">
                            <i class="fas fa-file-invoice"></i> Record GST Payment
                        </button>
                        <button class="btn btn-primary" onclick="showTaxModal('tds_payment')">
                            <i class="fas fa-percent"></i> Record TDS Payment
                        </button>
                        <button class="btn btn-secondary" onclick="showTaxModal('tax_refund')">
                            <i class="fas fa-undo"></i> Record Tax Refund (Money In)
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment History Tab -->
    <div id="history-tab" class="tab-content">
        <div class="pay-panel">
            
            <!-- Filters -->
            <div class="filter-section">
                <form method="GET" class="filter-form">
                    <input type="hidden" name="tab" value="history">
                    
                    <select name="type" class="f-select">
                        <option value="">All Payment Types</option>
                        <option value="customer_receipt" <?= $payment_type_filter === 'customer_receipt' ? 'selected' : '' ?>>Customer Receipt</option>
                        <option value="customer_refund" <?= $payment_type_filter === 'customer_refund' ? 'selected' : '' ?>>Customer Refund</option>
                        <option value="vendor_bill_payment" <?= $payment_type_filter === 'vendor_bill_payment' ? 'selected' : '' ?>>Vendor Bill</option>
                        <option value="vendor_bill_payment" <?= $payment_type_filter === 'vendor_bill_payment' ? 'selected' : '' ?>>Vendor Bill</option>
                        <option value="contractor_payment" <?= $payment_type_filter === 'contractor_payment' ? 'selected' : '' ?>>Contractor Bill</option>
                        <option value="gst_payment" <?= $payment_type_filter === 'gst_payment' ? 'selected' : '' ?>>GST Payment</option>
                        <option value="tds_payment" <?= $payment_type_filter === 'tds_payment' ? 'selected' : '' ?>>TDS Payment</option>
                        <option value="tax_refund" <?= $payment_type_filter === 'tax_refund' ? 'selected' : '' ?>>Tax Refund</option>
                    </select>
                    
                    <input type="date" name="date_from" class="f-input" value="<?= htmlspecialchars($date_from) ?>" placeholder="From">
                    <input type="date" name="date_to" class="f-input" value="<?= htmlspecialchars($date_to) ?>" placeholder="To">
                    
                    <button type="submit" class="btn-go"><i class="fas fa-search"></i> Apply</button>
                    
                    <?php if ($payment_type_filter || $date_from || $date_to): ?>
                        <a href="<?= BASE_URL ?>modules/payments/index.php?tab=history" class="btn-reset"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <div style="overflow-x:auto">
                <table class="pay-table">
                    <thead>
                        <tr>
                            <th class="th-c">Date</th>
                            <th>Type</th>
                            <th>Party</th>
                            <th class="th-r">Amount</th>
                            <th>Mode</th>
                            <th>Ref No</th>
                            <th>Remarks</th>
                            <th class="th-c">By</th>
                            <th class="th-c">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="9">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox" style="color:var(--border)"></i>
                                        <h4>No payment records found</h4>
                                        <p>Try adjusting your filters.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payments as $payment): 
                                $typeClass = 'gray';
                                $typeLabel = 'Other';
                                if($payment['payment_type'] === 'customer_receipt') { $typeClass = 'green'; $typeLabel = 'Receipt'; }
                                if($payment['payment_type'] === 'vendor_payment') { $typeClass = 'blue'; $typeLabel = 'Vendor'; }
                                if($payment['payment_type'] === 'vendor_bill_payment') { $typeClass = 'blue'; $typeLabel = 'Vendor'; }
                                if($payment['payment_type'] === 'contractor_payment') { $typeClass = 'orange'; $typeLabel = 'Contractor'; }
                                if($payment['payment_type'] === 'gst_payment') { $typeClass = 'gray'; $typeLabel = 'GST'; }
                                if($payment['payment_type'] === 'tds_payment') { $typeClass = 'gray'; $typeLabel = 'TDS'; }
                                if($payment['payment_type'] === 'tax_refund') { $typeClass = 'green'; $typeLabel = 'Refund'; }

                                $isVendor = in_array($payment['payment_type'], ['vendor_payment', 'vendor_bill_payment']);
                                $colorKey = $isVendor ? $payment['party_name'] : $payment['party_id'];
                                $partyColor = ColorHelper::getCustomerColor($colorKey);
                                $partyInitial = strtoupper(substr($payment['party_name'] ?? 'U', 0, 1));
                                
                                $creatorName = !empty($payment['created_by_name']) ? $payment['created_by_name'] : 'System';
                                $creatorInitial = strtoupper(substr($creatorName, 0, 1));
                            ?>
                            <tr>
                                <td class="td-c"><span style="font-weight:600;color:var(--ink-soft);font-size:0.82rem"><?= formatDate($payment['payment_date']) ?></span></td>
                                <td><span class="pill <?= $typeClass ?>"><?= $typeLabel ?></span></td>
                                <td>
                                    <div style="display:flex;align-items:center">
                                        <span style="font-weight:600;font-size:0.875rem"><?= htmlspecialchars($payment['party_name'] ?? 'Unknown') ?></span>
                                    </div>
                                </td>
                                <td class="td-r"><strong style="font-weight:700;color:#10b981"><?= formatCurrency($payment['amount'] ?? 0) ?></strong></td>
                                <td>
                                    <div>
                                        <span style="font-size:0.82rem;text-transform:capitalize;font-weight:600"><?= htmlspecialchars($payment['payment_mode']) ?></span>
                                        <?php if(!empty($payment['demand_stage'])): ?>
                                            <div style="font-size:0.7rem;color:#6366f1;margin-top:0.15rem">
                                                For: <?= htmlspecialchars($payment['demand_stage']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><span style="font-size:0.78rem;color:var(--ink-mute)"><?= htmlspecialchars($payment['reference_no'] ?: '—') ?></span></td>
                                <td><span style="font-size:0.78rem;color:var(--ink-mute)"><?= htmlspecialchars(substr($payment['remarks'], 0, 30)) ?></span></td>
                                <td class="td-c">
                                    <span style="font-size:0.82rem;color:var(--ink-soft);font-weight:500"><?= htmlspecialchars($creatorName) ?></span>
                                </td>
                                <td class="td-c">
                                    <?php if($payment['payment_type'] === 'customer_receipt'): ?>
                                        <a href="<?= BASE_URL ?>modules/reports/download.php?action=payment_receipt&id=<?= $payment['id'] ?>" class="act-btn" target="_blank" title="Print Receipt">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- Payment Modal -->
<div class="pay-modal-backdrop" id="paymentModal">
    <div class="pay-modal">
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="make_payment">
            <input type="hidden" name="payment_type" id="payment_type">
            <input type="hidden" name="reference_id" id="reference_id">
            <input type="hidden" name="party_id" id="party_id">
            <input type="hidden" id="max_pending_amount" value="0">

            <div class="modal-head">
                <div>
                    <h3><i class="fas fa-cash-register"></i> Record Payment</h3>
                    <p>Process a new transaction</p>
                </div>
                <button type="button" class="modal-close" onclick="closeModal()">×</button>
            </div>

            <div class="modal-body">
                
                <div class="sum-box">
                    <div class="sum-row">
                        <span class="lbl">Party Name</span>
                        <strong class="val" id="party_name_display">—</strong>
                    </div>
                    <div class="sum-row">
                        <span class="lbl">Total Pending</span>
                        <strong class="val" id="pending_amount_display">₹ 0.00</strong>
                    </div>
                </div>

                <!-- Demand Selection -->
                <div id="demand_selection_group" style="display:none" class="field">
                    <label>Payment For (Optional)</label>
                    <select name="demand_id" id="demand_dropdown">
                        <option value="">General / Oldest Unpaid (Default)</option>
                    </select>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label>Date *</label>
                        <input type="date" name="payment_date" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="field">
                        <label>Amount (₹) *</label>
                        <input type="text" id="payment_amount" name="payment_amount_final" required 
                               inputmode="decimal" placeholder="0.00" autocomplete="off" 
                               style="font-weight:700;color:#10b981" 
                               oninput="sanitizeAmount(this);calculateBalance()">
                    </div>
                </div>
                
                <div class="field-row">
                    <div class="field">
                        <label>Payment Mode *</label>
                        <select name="payment_mode" required>
                            <option value="cash">Cash</option>
                            <option value="bank">Bank Transfer</option>
                            <option value="upi">UPI</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>
                </div>
                
                <!-- Added Bank Account Selection -->
                <div class="field">
                     <label>Bank Account (Source/Dest)</label>
                     <select name="company_account_id" class="f-select" style="width:100%">
                         <option value="">-- Select Bank Account --</option>
                         <?php foreach ($bankAccounts as $acc): ?>
                             <?php $display = $acc['bank_name'] . ' - ' . $acc['account_name'] . ' (' . substr($acc['account_number'], -4) . ')'; ?>
                             <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($display) ?></option>
                         <?php endforeach; ?>
                     </select>
                     <small style="color:var(--ink-mute); font-size:0.75rem;">Select which company account creates/receives this payment.</small>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label>Reference / UTR No</label>
                        <input type="text" name="reference_no" placeholder="Transaction ID">
                    </div>
                </div>
                
                <div class="field">
                    <label>Remarks</label>
                    <textarea name="remarks" rows="2" placeholder="Add optional notes..."></textarea>
                </div>

                <div class="calc-box">
                    <span class="lbl">Remaining Balance</span>
                    <strong class="val" id="remaining_calc">₹ 0.00</strong>
                </div>
                <div id="amount_warning" class="warn-text">
                    <i class="fas fa-exclamation-circle"></i> Requires admin approval for overpayment
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check"></i> Record Payment
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById(tab + '-tab').classList.add('active');
    const tabs = document.querySelectorAll('.tab-btn');
    if(tab === 'customer') tabs[0].classList.add('active');
    if(tab === 'vendor') tabs[1].classList.add('active');
    if(tab === 'contractor') tabs[2].classList.add('active');
    if(tab === 'tax') tabs[3].classList.add('active');
    if(tab === 'history') tabs[4].classList.add('active');
}

function openModal() { document.getElementById('paymentModal').classList.add('open'); }
function closeModal() { document.getElementById('paymentModal').classList.remove('open'); }

document.getElementById('paymentModal').addEventListener('click', e => {
    if (e.target.id === 'paymentModal') closeModal();
});

function round2(num) { return Math.round((num + Number.EPSILON) * 100) / 100; }

function showPaymentModal(type, refId, partyId, pendingAmount, partyName) {
    document.getElementById('payment_type').value = type;
    document.getElementById('reference_id').value = refId;
    document.getElementById('party_id').value = partyId;
    document.getElementById('payment_amount').value = '';
    document.getElementById('max_pending_amount').value = round2(pendingAmount);
    document.getElementById('party_name_display').textContent = partyName;
    
    const formattedPending = round2(pendingAmount).toFixed(2);
    document.getElementById('pending_amount_display').textContent = '₹ ' + formattedPending;
    document.getElementById('remaining_calc').textContent = '₹ ' + formattedPending;
    document.getElementById('remaining_calc').style.color = 'var(--ink)';
    document.getElementById('amount_warning').classList.remove('show');

    const demandGroup = document.getElementById('demand_selection_group');
    const demandDropdown = document.getElementById('demand_dropdown');
    demandDropdown.innerHTML = '<option value="">General / Oldest Unpaid (Default)</option>';
    demandGroup.style.display = 'none';

    if (type === 'customer_receipt') {
        fetch(`<?= BASE_URL ?>modules/api/get_booking_demands.php?booking_id=${refId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.demands.length > 0) {
                    data.demands.forEach(d => {
                        const option = document.createElement('option');
                        option.value = d.id;
                        option.textContent = d.label;
                        demandDropdown.appendChild(option);
                    });
                    demandGroup.style.display = 'block';
                }
            })
            .catch(err => console.error('Error fetching demands:', err));
    }

    openModal();
}

function showTaxModal(type) {
    document.getElementById('payment_type').value = type;
    document.getElementById('reference_id').value = '0';
    document.getElementById('party_id').value = '0'; // No party for now, or could select Authority
    document.getElementById('payment_amount').value = '';
    document.getElementById('max_pending_amount').value = '9999999999'; // No limit
    
    let title = 'Record Tax Payment';
    if(type === 'gst_payment') title = 'GST Payment to Govt';
    if(type === 'tds_payment') title = 'TDS Payment to Govt';
    if(type === 'tax_refund') title = 'Tax Refund Received';
    
    document.getElementById('party_name_display').textContent = 'Government / Authority';
    document.getElementById('pending_amount_display').textContent = 'N/A';
    document.getElementById('remaining_calc').textContent = 'N/A';
    
    document.getElementById('paymentModal').querySelector('h3').innerHTML = '<i class="fas fa-university"></i> ' + title;
    
    openModal();
}

function sanitizeAmount(input) {
    let value = input.value.replace(/[^0-9.]/g, '');
    const parts = value.split('.');
    if (parts.length > 2) value = parts[0] + '.' + parts.slice(1).join('');
    if (parts[1]?.length > 2) value = parts[0] + '.' + parts[1].slice(0, 2);
    input.value = value;
}

function calculateBalance() {
    const pendingAmount = round2(parseFloat(document.getElementById('max_pending_amount').value) || 0);
    const paymentAmount = round2(parseFloat(document.getElementById('payment_amount').value) || 0);
    const remaining = round2(pendingAmount - paymentAmount);

    const displayEl = document.getElementById('remaining_calc');
    const warningEl = document.getElementById('amount_warning');

    if (remaining >= 0) {
        displayEl.textContent = '₹ ' + remaining.toFixed(2);
        displayEl.style.color = remaining === 0 ? '#10b981' : 'var(--ink)';
        warningEl.classList.remove('show');
    } else {
        displayEl.textContent = '₹ ' + Math.abs(remaining).toFixed(2) + ' (Excess)';
        displayEl.style.color = '#ef4444';
        warningEl.classList.add('show');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const url = new URL(window.location.href);
    const tab = url.searchParams.get('tab');
    if (tab) {
        switchTab(tab);
        url.searchParams.delete('tab');
        window.history.replaceState({}, document.title, url.pathname + url.search);
    } else {
        switchTab('customer');
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>