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
        $party_id = !empty($_POST['party_id']) ? intval($_POST['party_id']) : null;
        $payment_date = $_POST['payment_date'];
        $amount = round((float)$_POST['payment_amount_final'], 2);
        $payment_mode = $_POST['payment_mode'];
        $reference_no = sanitize($_POST['reference_no']);
        $remarks = sanitize($_POST['remarks']);
        $company_account_id = !empty($_POST['company_account_id']) ? intval($_POST['company_account_id']) : null;
        
        // Require bank account for non-cash payment modes
        if (in_array($payment_mode, ['bank', 'upi', 'cheque']) && empty($company_account_id)) {
            setFlashMessage('error', 'A bank account must be selected for ' . ucfirst($payment_mode) . ' payments.');
            redirect($_POST['redirect_url'] ?? 'modules/payments/index.php');
        }
        
        $db->beginTransaction();
        try {
            // Restore strict rule for non-tax payments
            if (in_array($payment_type, ['customer_receipt', 'vendor_payment', 'vendor_bill_payment', 'contractor_payment', 'contractor_account_payment'])) {
                if (empty($party_id) || $party_id <= 0) {
                    throw new Exception("Strict Rule Enforced: A valid Party (Customer/Vendor/Contractor) MUST be selected for this payment type.");
                }
            }

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
                            'cash_account_id' => $cash_account_id,
                            'created_by' => $_SESSION['user_id']
                        ];
                        $pid = $db->insert('payments', $payment_data);
                        logAudit('create', 'payments', $pid, null, $payment_data);
                        updateBillPaidAmount($bill['id']);

                        // Sync Bank Balance
                        if ($company_account_id) {
                            $db->execute("UPDATE company_accounts SET current_balance = current_balance - ? WHERE id = ?", [$allocate, $company_account_id]);
                        }

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
                            'cash_account_id' => $cash_account_id,
                            'created_by' => $_SESSION['user_id']
                        ];
                        $pid = $db->insert('payments', $payment_data);
                        logAudit('create', 'payments', $pid, null, $payment_data);
                        updateContractorBillPaidAmount($bill['id']); 

                        // Sync Bank Balance
                        if ($company_account_id) {
                            $db->execute("UPDATE company_accounts SET current_balance = current_balance - ? WHERE id = ?", [$allocate, $company_account_id]);
                        }

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
                    'reference_type' => $payment_type === 'customer_receipt' ? 'booking' : ($payment_type === 'vendor_bill_payment' ? 'bill' : (in_array($payment_type, ['contractor_payment', 'gst_payment', 'tds_payment']) && $reference_id > 0 ? 'contractor_bill' : null)),
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

                // Sync Bank Balance
                if ($payment_type === 'tax_refund') {
                    // Money In
                    if ($company_account_id) {
                        $db->query("UPDATE company_accounts SET current_balance = current_balance + ? WHERE id = ?", [$amount, $company_account_id]);
                    }
                } elseif ($payment_type === 'customer_receipt') {
                    // Money In
                    if ($company_account_id) {
                        $db->query("UPDATE company_accounts SET current_balance = current_balance + ? WHERE id = ?", [$amount, $company_account_id]);
                    }
                } else {
                    // Money Out
                    if ($company_account_id) {
                        $db->query("UPDATE company_accounts SET current_balance = current_balance - ? WHERE id = ?", [$amount, $company_account_id]);
                    }
                }
                
                if ($payment_type === 'customer_receipt') {
                    updateBookingTotals($reference_id);
                } elseif ($payment_type === 'vendor_bill_payment') {
                    updateBillPaidAmount($reference_id);
                } elseif ($payment_type === 'contractor_payment') {
                    updateContractorBillPaidAmount($reference_id);
                } elseif ($payment_type === 'gst_payment' && $reference_id > 0) {
                    $db->query("UPDATE contractor_bills SET gst_paid_amount = gst_paid_amount + ? WHERE id = ?", [$amount, $reference_id]);
                } elseif ($payment_type === 'tds_payment' && $reference_id > 0) {
                    $db->query("UPDATE contractor_bills SET tds_paid_amount = tds_paid_amount + ? WHERE id = ?", [$amount, $reference_id]);
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

    $pending_taxes = $db->query("
        SELECT 'TDS' as tax_type, cb.id as ref_id, cb.bill_no, cb.bill_date, p.name as party_name, p.id as party_id, p.contractor_type,
               cb.tds_amount as total_amount, cb.tds_paid_amount as paid_amount,
               (cb.tds_amount - cb.tds_paid_amount) as pending_amount
        FROM contractor_bills cb
        JOIN parties p ON cb.contractor_id = p.id
        WHERE cb.status = 'approved' AND (cb.tds_amount - cb.tds_paid_amount) > 0
        
        UNION ALL
        
        SELECT 'GST' as tax_type, cb.id as ref_id, cb.bill_no, cb.bill_date, p.name as party_name, p.id as party_id, p.contractor_type,
               cb.gst_amount as total_amount, cb.gst_paid_amount as paid_amount,
               (cb.gst_amount - cb.gst_paid_amount) as pending_amount
        FROM contractor_bills cb
        JOIN parties p ON cb.contractor_id = p.id
        WHERE cb.status = 'approved' AND cb.is_rcm = 1 AND (cb.gst_amount - cb.gst_paid_amount) > 0
        
        ORDER BY bill_date DESC
    ")->fetchAll();

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
        --accent:    #2a58b5;
        --accent-bg: #f0f5ff;
        --accent-lt: #eff4ff;
        --accent-md: #c7d9f9;
    }

    /* ── Page Wrapper ─────────────────────────────────────────────────── */
    .pay-wrap { max-width: 1280px; margin: 2.5rem auto; padding: 0 1.5rem 4rem; }

    /* ── Header ───────────────────────────────────────────────────────── */
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

    /* ── Tabs ─────────────────────────────────────────────────────────── */
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

    /* ── Main Panel ───────────────────────────────────────────────────── */
    .pay-panel {
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 14px; overflow: hidden;
        animation: fadeUp 0.4s ease both;
    }

    /* ── Table ────────────────────────────────────────────────────────── */
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

    /* ── Pill Badges ──────────────────────────────────────────────────── */
    .pill { display: inline-block; padding: 0.24rem 0.7rem; border-radius: 20px; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.03em; }
    .pill.blue   { background: #eff6ff; color: #1e40af; }
    .pill.green  { background: #ecfdf5; color: #065f46; }
    .pill.orange { background: #fef3c7; color: #92400e; }
    .pill.gray   { background: #f0ece5; color: var(--ink-soft); }

    /* ── Empty State ──────────────────────────────────────────────────── */
    .empty-state { padding: 4rem 1rem; text-align: center; }
    .empty-state i { font-size: 2.5rem; color: #10b981; margin-bottom: 0.75rem; display: block; }
    .empty-state h4 { font-size: 1rem; font-weight: 700; color: var(--ink-soft); margin: 0 0 0.35rem; }
    .empty-state p { font-size: 0.82rem; color: var(--ink-mute); margin: 0; }

    /* ── Filter Section ───────────────────────────────────────────────── */
    .filter-section {
        padding: 1.25rem 1.5rem; border-bottom: 1.5px solid var(--border-lt); background: #fdfcfa;
    }
    .filter-form { display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap; }
    .f-input, .f-select {
        height: 38px; padding: 0 0.75rem; border: 1.5px solid var(--border); border-radius: 7px;
        font-size: 0.82rem; color: var(--ink); background: white; outline: none; transition: border-color 0.15s;
    }
    .f-input { flex: 0 0 160px; }
    .f-select {
        flex: 0 0 180px; -webkit-appearance: none; appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 0.6rem center; padding-right: 2rem;
    }
    .f-input:focus, .f-select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(42,88,181,0.1); }
    .btn-go, .btn-reset {
        height: 38px; padding: 0 1.25rem; border: none; border-radius: 7px;
        display: flex; align-items: center; gap: 0.4rem;
        font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: all 0.18s; text-decoration: none;
    }
    .btn-go { background: var(--ink); color: white; }
    .btn-go:hover { background: var(--accent); }
    .btn-reset { background: #f0ece5; color: var(--ink-soft); }
    .btn-reset:hover { background: var(--border); color: var(--ink); }

    /* ── Action Buttons ───────────────────────────────────────────────── */
    .btn-collect {
        display: inline-flex; align-items: center; gap: 0.4rem;
        padding: 0.5rem 1rem; border: 1.5px solid var(--border);
        background: var(--ink); color: white;
        border-radius: 7px; font-size: 0.78rem; font-weight: 600;
        cursor: pointer; transition: all 0.18s; text-decoration: none;
    }
    .btn-collect:hover { background: var(--accent); border-color: var(--accent); color: white; }
    .btn-collect-outline { background: white; color: var(--ink); border: 1.5px solid var(--border); }
    .btn-collect-outline:hover { background: var(--accent-bg); border-color: var(--accent); color: var(--accent); }
    .act-btn {
        width: 28px; height: 28px; border-radius: 6px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.72rem; text-decoration: none; cursor: pointer;
        border: 1.5px solid var(--border); background: var(--surface);
        color: var(--ink-soft); transition: all 0.16s;
    }
    .act-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-bg); }

    /* ── General Buttons ──────────────────────────────────────────────── */
    .btn {
        padding: 0.7rem 1.4rem; border-radius: 8px;
        font-size: 0.875rem; font-weight: 600; cursor: pointer;
        transition: all 0.18s; display: inline-flex;
        align-items: center; gap: 0.5rem; text-decoration: none;
    }
    .btn-secondary { background: black; color: white; border: 1.5px solid var(--border); }
    .btn-secondary:hover { background: var(--accent); border-color: var(--accent); color: white; }
    .btn-primary { background: var(--ink); color: white; border: 1.5px solid var(--ink); }
    .btn-primary:hover { background: var(--accent); border-color: var(--accent); box-shadow: 0 4px 14px rgba(42,88,181,0.3); }

    /* ── Animations ───────────────────────────────────────────────────── */
    @keyframes fadeUp { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:translateY(0); } }

    /* ══════════════════════════════════════════════════════════════════
       PAYMENT MODAL
       ══════════════════════════════════════════════════════════════════ */

    /* Backdrop */
    .pay-modal-backdrop {
        display: none; position: fixed; inset: 0; z-index: 10000;
        background: rgba(15,12,10,0.6);
        backdrop-filter: blur(8px) saturate(0.7);
        -webkit-backdrop-filter: blur(8px) saturate(0.7);
        align-items: center; justify-content: center; padding: 1.25rem;
    }
    .pay-modal-backdrop.open { display: flex; }

    /* Shell */
    .pay-modal {
        width: 100%; max-width: 560px; max-height: 92vh;
        display: flex; flex-direction: column;
        background: #ffffff; border-radius: 22px; overflow: hidden;
        box-shadow:
            0 0 0 1px rgba(26,23,20,0.07),
            0 4px 16px rgba(26,23,20,0.06),
            0 24px 64px rgba(26,23,20,0.18),
            0 48px 96px rgba(26,23,20,0.12);
        animation: pmIn 0.35s cubic-bezier(0.16,1,0.3,1);
    }
    @keyframes pmIn {
        from { opacity:0; transform:translateY(24px) scale(0.96); }
        to   { opacity:1; transform:translateY(0) scale(1); }
    }
    .pay-modal form { display: flex; flex-direction: column; height: 100%; margin: 0; overflow: hidden; }
    
    .pay-modal .modal-body {
        overflow-y: auto; flex: 1; min-height: 0;
        padding-bottom: 2rem;
        scrollbar-width: thin; scrollbar-color: var(--border) transparent;
    }
    .pay-modal .modal-body::-webkit-scrollbar { width: 4px; }
    .pay-modal .modal-body::-webkit-scrollbar-track { background: transparent; }
    .pay-modal .modal-body::-webkit-scrollbar-thumb { background: var(--border); border-radius: 99px; }

    /* ── Hero Header ──────────────────────────────────────────────────── */
    .pm-hero {
        position: relative; padding: 1.75rem 1.75rem 1.5rem;
        background: linear-gradient(135deg, #f0f5ff 0%, #e8f0fe 100%); overflow: hidden; flex-shrink: 0;
        border-bottom: 1.5px solid var(--border);
    }
    /* Dot-grid texture */
    .pm-hero::before {
        content: ''; position: absolute; inset: 0;
        background-image: radial-gradient(circle, rgba(42,88,181,0.04) 1px, transparent 1px);
        background-size: 20px 20px; pointer-events: none;
    }
    /* Glow orb */
    .pm-hero::after {
        content: ''; position: absolute; top: -60px; right: -40px;
        width: 200px; height: 200px; border-radius: 50%;
        background: radial-gradient(circle, rgba(42,88,181,0.12) 0%, transparent 70%);
        pointer-events: none;
    }
    .pm-hero-top {
        display: flex; align-items: flex-start; justify-content: space-between;
        gap: 1rem; margin-bottom: 1.25rem; position: relative; z-index: 1;
    }
    .pm-icon-wrap {
        width: 44px; height: 44px; border-radius: 12px;
        background: var(--accent-bg); border: 1px solid var(--accent-md);
        display: flex; align-items: center; justify-content: center;
        font-size: 1.05rem; color: var(--accent); flex-shrink: 0;
    }
    .pm-title-block { flex: 1; }
    .pm-title-block h3 {
        font-family: 'Fraunces', serif; font-size: 1.3rem; font-weight: 700;
        color: var(--ink); margin: 0 0 0.2rem; letter-spacing: -0.02em; line-height: 1.15;
    }
    .pm-title-block h3 em { font-style: italic; color: var(--accent); }
    .pm-title-block p { font-size: 0.74rem; color: var(--ink-mute); margin: 0; }
    .pm-close {
        width: 32px; height: 32px; border-radius: 9px; flex-shrink: 0;
        border: 1.5px solid var(--border); background: var(--surface);
        color: var(--ink-mute); font-size: 1rem; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        transition: all 0.18s; line-height: 1;
    }
    .pm-close:hover { background: #fee2e2; border-color: #fca5a5; color: #b91c1c; transform: rotate(90deg); }

    /* Party summary strip */
    .pm-party-strip {
        display: flex; align-items: center; justify-content: space-between;
        gap: 0.75rem; flex-wrap: wrap;
        background: var(--surface); border: 1.5px solid var(--border);
        border-radius: 12px; padding: 0.9rem 1.1rem;
        position: relative; z-index: 1;
    }
    .pm-party-info { display: flex; align-items: center; gap: 0.75rem; }
    .pm-avatar {
        width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0;
        background: var(--accent-bg); border: 1px solid var(--accent-md);
        display: flex; align-items: center; justify-content: center;
        font-size: 0.85rem; font-weight: 700; color: var(--accent);
    }
    .pm-party-name { font-size: 0.9rem; font-weight: 700; color: var(--ink); }
    .pm-party-sub  { font-size: 0.68rem; color: var(--ink-mute); margin-top: 0.1rem; }
    .pm-pending-chip { display: flex; flex-direction: column; align-items: flex-end; }
    .pm-pending-label { font-size: 0.6rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--ink-mute); margin-bottom: 0.15rem; }
    .pm-pending-val { font-family: 'Fraunces', serif; font-size: 1.25rem; font-weight: 700; color: #b45309; letter-spacing: -0.02em; line-height: 1; }

    /* ── Modal Body ───────────────────────────────────────────────────── */
    .pm-body {
        padding: 1.6rem 1.75rem; overflow-y: auto; flex: 1 1 auto; min-height: 0;
        scrollbar-width: thin; scrollbar-color: var(--border) transparent;
    }
    .pm-body::-webkit-scrollbar { width: 4px; }
    .pm-body::-webkit-scrollbar-track { background: transparent; }
    .pm-body::-webkit-scrollbar-thumb { background: var(--border); border-radius: 99px; }

    /* ── Section Labels ───────────────────────────────────────────────── */
    .pm-section { margin-bottom: 1.5rem; }
    .pm-section-lbl {
        display: flex; align-items: center; gap: 0.45rem;
        font-size: 0.6rem; font-weight: 800; letter-spacing: 0.14em;
        text-transform: uppercase; color: var(--ink-mute); margin-bottom: 0.85rem;
    }
    .pm-section-lbl::after { content: ''; flex: 1; height: 1px; background: var(--border-lt); }
    .pm-section-lbl span {
        width: 16px; height: 16px; border-radius: 4px;
        background: var(--accent-bg); border: 1px solid var(--accent-md);
        display: flex; align-items: center; justify-content: center;
        font-size: 0.5rem; color: var(--accent);
    }

    /* ── Big Amount Input ─────────────────────────────────────────────── */
    .pm-amount-wrap {
        background: linear-gradient(135deg, #f8faff 0%, #f0f5ff 100%);
        border: 2px solid var(--accent-md); border-radius: 14px;
        padding: 1.25rem 1.35rem; transition: border-color 0.2s, box-shadow 0.2s;
        position: relative; overflow: hidden; margin-bottom: 1.5rem;
    }
    .pm-amount-wrap::before {
        content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px;
        background: linear-gradient(90deg, var(--accent), #60a5fa); opacity: 0.5;
    }
    .pm-amount-wrap:focus-within { border-color: var(--accent); box-shadow: 0 0 0 4px rgba(42,88,181,0.1); }
    .pm-amount-lbl {
        font-size: 0.63rem; font-weight: 800; letter-spacing: 0.12em;
        text-transform: uppercase; color: var(--accent); margin-bottom: 0.6rem;
        display: flex; align-items: center; gap: 0.4rem;
    }
    .pm-amount-lbl i { font-size: 0.55rem; }
    .pm-amount-input-row { display: flex; align-items: center; gap: 0.5rem; }
    .pm-rupee {
        font-family: 'Fraunces', serif; font-size: 1.8rem; font-weight: 700;
        color: var(--accent); line-height: 1; flex-shrink: 0;
    }
    .pm-amount-input {
        flex: 1; border: none; outline: none; background: transparent;
        font-family: 'Fraunces', serif; font-size: 2rem; font-weight: 700;
        color: var(--ink); letter-spacing: -0.03em; line-height: 1; min-width: 0;
    }
    .pm-amount-input::placeholder { color: #c4bdb5; }
    .pm-balance-row {
        display: flex; align-items: center; justify-content: space-between;
        margin-top: 0.9rem; padding-top: 0.85rem;
        border-top: 1px solid rgba(42,88,181,0.12);
    }
    .pm-bal-lbl { font-size: 0.72rem; font-weight: 600; color: var(--ink-mute); }
    .pm-bal-val { font-family: 'Fraunces', serif; font-size: 1rem; font-weight: 700; color: var(--ink); transition: color 0.2s; }
    .pm-bal-val.paid-off { color: #10b981; }
    .pm-bal-val.overpaid { color: #ef4444; }
    .pm-excess-warn {
        display: none; align-items: center; gap: 0.4rem;
        background: #fef2f2; border: 1.5px solid #fca5a5; border-radius: 8px;
        padding: 0.6rem 0.85rem; margin-top: 0.75rem;
        font-size: 0.75rem; font-weight: 600; color: #b91c1c;
        animation: shakeIn 0.3s ease;
    }
    .pm-excess-warn.show { display: flex; }
    @keyframes shakeIn { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-4px)} 75%{transform:translateX(4px)} }

    /* ── Form Fields ──────────────────────────────────────────────────── */
    .pm-field { position: relative; margin-bottom: 0; }
    .pm-field label {
        display: block; font-size: 0.7rem; font-weight: 700;
        letter-spacing: 0.05em; text-transform: uppercase;
        color: var(--ink-soft); margin-bottom: 0.38rem; transition: color 0.16s;
    }
    .pm-field:focus-within label { color: var(--accent); }
    .pm-field input,
    .pm-field select,
    .pm-field textarea {
        width: 100%; padding: 0.72rem 0.9rem;
        border: 1.5px solid var(--border); border-radius: 10px;
        font-size: 0.875rem; color: var(--ink); background: #fdfcfa; outline: none;
        transition: border-color 0.18s, box-shadow 0.18s, background 0.18s;
        font-family: 'DM Sans', sans-serif;
    }
    .pm-field input::placeholder, .pm-field textarea::placeholder { color: #c4bdb5; }
    .pm-field select {
        -webkit-appearance: none; appearance: none; cursor: pointer;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b6560' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 0.85rem center; padding-right: 2.4rem;
    }
    .pm-field input:focus, .pm-field select:focus, .pm-field textarea:focus {
        border-color: var(--accent); background: white;
        box-shadow: 0 0 0 3.5px rgba(42,88,181,0.1);
    }
    .pm-field textarea { resize: vertical; min-height: 68px; line-height: 1.55; }
    .pm-field small { display: block; font-size: 0.69rem; color: var(--ink-mute); margin-top: 0.35rem; }

    /* Icon prefix */
    .pm-icon-field { position: relative; }
    .pm-icon-field .pi-icon {
        position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%);
        font-size: 0.72rem; color: var(--ink-mute); pointer-events: none; transition: color 0.16s;
    }
    .pm-icon-field:focus-within .pi-icon { color: var(--accent); }
    .pm-icon-field input, .pm-icon-field select { padding-left: 2.3rem; }

    /* Grid */
    .pm-row2  { display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; }
    .pm-stack { display: flex; flex-direction: column; gap: 0.85rem; }

    /* ── Payment Mode Pills ───────────────────────────────────────────── */
    .pm-mode-pills { display: flex; gap: 0.45rem; flex-wrap: wrap; }
    .pm-mode-pill { position: relative; flex: 1; min-width: 68px; }
    .pm-mode-pill input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
    .pm-mode-pill label {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: 0.3rem; padding: 0.65rem 0.5rem;
        border: 1.5px solid var(--border); border-radius: 10px;
        cursor: pointer; font-size: 0.68rem; font-weight: 700;
        letter-spacing: 0.04em; text-transform: uppercase;
        color: var(--ink-soft); background: white; transition: all 0.18s; margin: 0;
    }
    .pm-mode-pill label:hover { border-color: var(--accent); background: var(--accent-bg); }
    .pm-mode-pill label i { font-size: 0.9rem; transition: color 0.18s; }
    .pm-mode-pill input:checked + label {
        border-color: var(--accent); color: var(--accent);
        background: var(--accent-bg);
        box-shadow: 0 2px 8px rgba(42,88,181,0.15);
    }

    /* ── Modal Footer ─────────────────────────────────────────────────── */
    .pm-footer {
        display: flex; align-items: center; justify-content: flex-end; gap: 0.65rem;
        padding: 1.2rem 1.75rem; border-top: 1.5px solid var(--border-lt);
        background: #fafcff; flex-shrink: 0;
    }
    .pm-footer-meta {
        flex: 1; display: flex; align-items: center; gap: 0.4rem;
        font-size: 0.7rem; color: var(--ink-mute);
    }
    .pm-footer-meta i { color: var(--accent); }
    .pm-btn {
        display: inline-flex; align-items: center; gap: 0.5rem;
        padding: 0.72rem 1.5rem; border-radius: 10px;
        font-size: 0.85rem; font-weight: 700; cursor: pointer;
        transition: all 0.2s cubic-bezier(0.16,1,0.3,1);
        font-family: 'DM Sans', sans-serif; letter-spacing: 0.01em; line-height: 1;
        border: 1.5px solid transparent; text-decoration: none;
    }
    .pm-btn-cancel { background: white; color: var(--ink-soft); border-color: var(--border); }
    .pm-btn-cancel:hover { border-color: var(--ink-soft); color: var(--ink); background: #f5f3ef; }
    .pm-btn-submit { background: var(--ink); color: white; border-color: var(--ink); box-shadow: 0 2px 10px rgba(26,23,20,0.2); }
    .pm-btn-submit:hover { background: var(--accent); border-color: var(--accent); box-shadow: 0 6px 20px rgba(42,88,181,0.35); transform: translateY(-1px); }
    .pm-btn-submit:active { transform: translateY(0); }

    /* Demand dropdown hidden by default */
    #demand_selection_group { display: none; }

    /* ── Inline Toast ─────────────────────────────────────────────────── */
    .pm-toast {
        display: flex; align-items: center; gap: 0.6rem;
        padding: 0.75rem 1rem; margin: 0 1.75rem 1rem;
        border-radius: 10px; font-size: 0.8rem; font-weight: 600;
        animation: toastIn 0.35s cubic-bezier(0.16,1,0.3,1);
        line-height: 1.35;
    }
    .pm-toast.error {
        background: #fef2f2; border: 1.5px solid #fca5a5; color: #991b1b;
    }
    .pm-toast i {
        font-size: 0.9rem; flex-shrink: 0;
    }
    .pm-toast-close {
        margin-left: auto; background: none; border: none;
        color: #991b1b; opacity: 0.5; cursor: pointer; font-size: 0.85rem;
        padding: 0.2rem; line-height: 1; transition: opacity 0.15s;
    }
    .pm-toast-close:hover { opacity: 1; }
    @keyframes toastIn {
        from { opacity: 0; transform: translateY(-8px); }
        to   { opacity: 1; transform: translateY(0); }
    }
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
            <span class="tab-badge"><?= count($pending_taxes) ?></span>
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
            <div style="padding: 1.5rem 2rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1.5px solid var(--border-lt); background: #fafcff; border-radius: 12px 12px 0 0;">
                <div>
                    <h3 style="margin:0; font-family:'Fraunces', serif; font-size:1.2rem; display:flex; align-items:center; gap:0.5rem">
                        <i class="fas fa-landmark" style="color:var(--accent);"></i> Tax Payments & Refunds
                    </h3>
                    <p style="color: var(--ink-soft); margin: 5px 0 0 0; font-size: 0.85rem;">Record direct payments to government authorities (GST/TDS) or generic tax refunds.</p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-secondary" onclick="showTaxModal('tax_refund')">
                        <i class="fas fa-undo"></i> Record Tax Refund
                    </button>
                </div>
            </div>
            
            <?php if (empty($pending_taxes)): ?>
                <div class="empty-state">
                    <i class="fas fa-check-double"></i>
                    <h4>No pending tax liabilities</h4>
                    <p>All auto-generated GST and TDS liabilities from bills are settled.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto">
                    <table class="pay-table">
                        <thead>
                            <tr>
                                <th>Tax Type</th>
                                <th>Party</th>
                                <th class="th-c">Bill Ref</th>
                                <th class="th-c">Date</th>
                                <th class="th-r">Total</th>
                                <th class="th-r">Paid</th>
                                <th class="th-r">Pending</th>
                                <th class="th-r">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_taxes as $tax): 
                                $isTds = $tax['tax_type'] === 'TDS';
                                $pillClass = $isTds ? 'orange' : 'blue';
                            ?>
                            <tr>
                                <td><span class="pill <?= $pillClass ?>"><?= htmlspecialchars($tax['tax_type']) ?> Payment</span></td>
                                <td>
                                    <div style="display:flex;align-items:center">
                                        <span style="font-weight:600;font-size:0.875rem"><?= htmlspecialchars($tax['party_name']) ?></span>
                                    </div>
                                    <div style="font-size:0.75rem; color:var(--ink-mute);">
                                        <?= htmlspecialchars($tax['contractor_type'] ?? 'Contractor') ?>
                                    </div>
                                </td>
                                <td class="td-c"><strong style="font-size:0.82rem"><?= htmlspecialchars($tax['bill_no']) ?></strong></td>
                                <td class="td-c"><span style="font-weight:600;color:var(--ink-soft);font-size:0.82rem"><?= formatDate($tax['bill_date']) ?></span></td>
                                <td class="td-r"><strong style="color:var(--ink)"><?= formatCurrencyShort($tax['total_amount']) ?></strong></td>
                                <td class="td-r"><span style="font-weight:600;color:#10b981"><?= formatCurrencyShort($tax['paid_amount']) ?></span></td>
                                <td class="td-r"><span style="font-weight:600;color:#ef4444"><?= formatCurrencyShort($tax['pending_amount']) ?></span></td>
                                <td class="td-r">
                                    <button class="btn-collect btn-collect-outline" onclick="showTaxBillModal('<?= $isTds ? 'tds_payment' : 'gst_payment' ?>', <?= $tax['ref_id'] ?>, <?= $tax['party_id'] ?>, <?= $tax['pending_amount'] ?>, '<?= htmlspecialchars(addslashes($tax['party_name'])) ?>')">
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
                                $isCredit = in_array($payment['payment_type'], ['customer_receipt', 'tax_refund']);
                            ?>
                            <tr>
                                <td class="td-c"><span style="font-weight:600;color:var(--ink-soft);font-size:0.82rem"><?= formatDate($payment['payment_date']) ?></span></td>
                                <td><span class="pill <?= $typeClass ?>"><?= $typeLabel ?></span></td>
                                <td>
                                    <div style="display:flex;align-items:center">
                                        <span style="font-weight:600;font-size:0.875rem">
                                            <?php 
                                            $dispParty = $payment['party_name'] ?? 'Unknown';
                                            if ($payment['payment_type'] === 'gst_payment') echo 'GST Authority (' . htmlspecialchars($dispParty) . ')';
                                            elseif ($payment['payment_type'] === 'tds_payment') echo 'TDS Authority (' . htmlspecialchars($dispParty) . ')';
                                            elseif ($payment['payment_type'] === 'tax_refund') echo 'Government / Tax Authority';
                                            else echo htmlspecialchars($dispParty);
                                            ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="td-r">
                                    <div>
                                        <strong style="font-weight:700;color:<?= $isCredit ? '#10b981' : '#ef4444' ?>"><?= $isCredit ? '+' : '−' ?> <?= formatCurrency($payment['amount'] ?? 0) ?></strong>
                                        <div style="font-size:0.65rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;margin-top:0.15rem;color:<?= $isCredit ? '#10b981' : '#ef4444' ?>"><?= $isCredit ? 'Credit' : 'Debit' ?></div>
                                    </div>
                                </td>
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
            <input type="hidden" name="action"          value="make_payment">
            <input type="hidden" name="payment_type"    id="payment_type">
            <input type="hidden" name="reference_id"    id="reference_id">
            <input type="hidden" name="party_id"        id="party_id">
            <input type="hidden" id="max_pending_amount" value="0">

            <!-- ── Hero Header ─────────────────────────────────── -->
            <div class="pm-hero">
                <div class="pm-hero-top">
                    <div class="pm-icon-wrap" id="pmModalIcon">
                        <i class="fas fa-cash-register"></i>
                    </div>
                    <div class="pm-title-block">
                        <h3 id="pmModalTitle">Record <em>Payment</em></h3>
                        <p id="pmModalSubtitle">Process a new financial transaction</p>
                    </div>
                    <button type="button" class="pm-close" onclick="closeModal()">×</button>
                </div>

                <!-- Party + Pending strip -->
                <div class="pm-party-strip">
                    <div class="pm-party-info">
                        <div class="pm-avatar" id="pmAvatarInitial">—</div>
                        <div>
                            <div class="pm-party-name" id="party_name_display">—</div>
                            <div class="pm-party-sub" id="pmPartyType">Transaction Party</div>
                        </div>
                    </div>
                    <div class="pm-pending-chip" id="pmPendingChip">
                        <div class="pm-pending-label">Pending</div>
                        <div class="pm-pending-val" id="pending_amount_display">₹ 0.00</div>
                    </div>
                </div>
            </div>

            <!-- ── Body ────────────────────────────────────────── -->
            <div class="pm-body">

                <!-- Amount — the hero input -->
                <div class="pm-amount-wrap">
                    <div class="pm-amount-lbl"><i class="fas fa-indian-rupee-sign"></i> Transaction Amount</div>
                    <div class="pm-amount-input-row">
                        <span class="pm-rupee">₹</span>
                        <input class="pm-amount-input" type="text" id="payment_amount"
                               name="payment_amount_final" required
                               inputmode="decimal" placeholder="0.00" autocomplete="off"
                               oninput="sanitizeAmount(this); calculateBalance()">
                    </div>
                    <div class="pm-balance-row">
                        <span class="pm-bal-lbl" id="pmBalLabel">Remaining after payment</span>
                        <span class="pm-bal-val" id="remaining_calc">₹ 0.00</span>
                    </div>
                    <div class="pm-excess-warn" id="amount_warning">
                        <i class="fas fa-triangle-exclamation"></i>
                        Amount exceeds pending balance — requires admin approval
                    </div>
                </div>

                <!-- Demand -->
                <div id="demand_selection_group" class="pm-section">
                    <div class="pm-section-lbl"><span><i class="fas fa-tag"></i></span> Allocate To</div>
                    <div class="pm-field">
                        <label>Payment Demand</label>
                        <div class="pm-icon-field">
                            <i class="pi-icon fas fa-layer-group"></i>
                            <select name="demand_id" id="demand_dropdown">
                                <option value="">General / Oldest Unpaid (Default)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Date & Mode -->
                <div class="pm-section">
                    <div class="pm-section-lbl"><span><i class="fas fa-calendar"></i></span> Schedule</div>
                    <div class="pm-field">
                        <label>Payment Date *</label>
                        <div class="pm-icon-field">
                            <i class="pi-icon fas fa-calendar-alt"></i>
                            <input type="date" name="payment_date" required value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                </div>

                <!-- Payment Mode pills -->
                <div class="pm-section">
                    <div class="pm-section-lbl"><span><i class="fas fa-credit-card"></i></span> Payment Mode</div>
                    <div class="pm-mode-pills">
                        <div class="pm-mode-pill">
                            <input type="radio" name="payment_mode" id="mode_bank" value="bank" checked>
                            <label for="mode_bank"><i class="fas fa-building-columns"></i> Bank</label>
                        </div>
                        <div class="pm-mode-pill">
                            <input type="radio" name="payment_mode" id="mode_upi" value="upi">
                            <label for="mode_upi"><i class="fas fa-qrcode"></i> UPI</label>
                        </div>
                        <div class="pm-mode-pill">
                            <input type="radio" name="payment_mode" id="mode_cash" value="cash">
                            <label for="mode_cash"><i class="fas fa-money-bill-wave"></i> Cash</label>
                        </div>
                        <div class="pm-mode-pill">
                            <input type="radio" name="payment_mode" id="mode_cheque" value="cheque">
                            <label for="mode_cheque"><i class="fas fa-file-lines"></i> Cheque</label>
                        </div>
                    </div>
                </div>

                <!-- Bank Account -->
                <div class="pm-section" id="bank_account_group">
                    <div class="pm-section-lbl"><span><i class="fas fa-bank"></i></span> Company Account</div>
                    <div class="pm-field">
                        <label>Source / Destination Account <span class="bank-required-star" style="color:#ef4444">*</span></label>
                        <div class="pm-icon-field">
                            <i class="pi-icon fas fa-building-columns"></i>
                            <select name="company_account_id" id="company_account_id" class="pm-bank-select" style="padding-left:2.3rem">
                                <option value="">— Select bank account —</option>
                                <?php foreach ($bankAccounts as $acc): ?>
                                    <?php $display = $acc['bank_name'] . ' · ' . $acc['account_name'] . ' (···' . substr($acc['account_number'], -4) . ')'; ?>
                                    <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($display) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <small>Which company account sends or receives this amount?</small>
                    </div>
                </div>

                <!-- Reference & Remarks -->
                <div class="pm-section">
                    <div class="pm-section-lbl"><span><i class="fas fa-file-lines"></i></span> Reference & Notes</div>
                    <div class="pm-stack">
                        <div class="pm-field">
                            <label>Reference / UTR No.</label>
                            <div class="pm-icon-field">
                                <i class="pi-icon fas fa-hashtag"></i>
                                <input type="text" name="reference_no" placeholder="Bank transaction ID or UTR number">
                            </div>
                        </div>
                        <div class="pm-field">
                            <label>Remarks</label>
                            <textarea name="remarks" placeholder="Add optional notes or context…"></textarea>
                        </div>
                    </div>
                </div>

            </div><!-- /pm-body -->

            <!-- ── Footer ──────────────────────────────────────── -->
            <div class="pm-footer">
                <div class="pm-footer-meta">
                    <i class="fas fa-shield-halved"></i> Logged in audit trail
                </div>
                <button type="button" class="pm-btn pm-btn-cancel" onclick="closeModal()">
                    <i class="fas fa-xmark"></i> Cancel
                </button>
                <button type="submit" class="pm-btn pm-btn-submit">
                    <i class="fas fa-check"></i> <span id="pmSubmitLabel">Record Payment</span>
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
    if(tab === 'customer')   tabs[0].classList.add('active');
    if(tab === 'vendor')     tabs[1].classList.add('active');
    if(tab === 'contractor') tabs[2].classList.add('active');
    if(tab === 'tax')        tabs[3].classList.add('active');
    if(tab === 'history')    tabs[4].classList.add('active');
}

function openModal()  { document.getElementById('paymentModal').classList.add('open');    }
function closeModal() { document.getElementById('paymentModal').classList.remove('open'); }

document.getElementById('paymentModal').addEventListener('click', e => {
    if (e.target.id === 'paymentModal') closeModal();
});

function round2(num) { return Math.round((num + Number.EPSILON) * 100) / 100; }

/* ── Type → display config ──────────────────────────────────── */
const pmTypeConfig = {
    customer_receipt:             { icon:'fa-arrow-down-to-line', title:'Collect <em>Receipt</em>',    sub:'Inbound payment from customer',     partyType:'Customer',   submitLabel:'Collect Payment',  color:'#10b981' },
    vendor_bill_payment:          { icon:'fa-file-invoice-dollar',title:'Vendor <em>Payment</em>',     sub:'Settle a vendor bill',              partyType:'Vendor',     submitLabel:'Pay Vendor',       color:'#3b82f6' },
    contractor_payment:           { icon:'fa-hard-hat',           title:'Contractor <em>Payment</em>', sub:'Pay a contractor bill',             partyType:'Contractor', submitLabel:'Pay Contractor',   color:'#f59e0b' },
    contractor_account_payment:   { icon:'fa-hard-hat',           title:'Contractor <em>Payment</em>', sub:'Allocate across contractor bills',  partyType:'Contractor', submitLabel:'Pay Contractor',   color:'#f59e0b' },
    vendor_payment:               { icon:'fa-truck',              title:'Vendor <em>Payment</em>',     sub:'Allocate across vendor bills',      partyType:'Vendor',     submitLabel:'Pay Vendor',       color:'#3b82f6' },
    gst_payment:                  { icon:'fa-landmark',           title:'GST <em>Payment</em>',        sub:'Direct tax remittance to govt.',    partyType:'Authority',  submitLabel:'Record GST',       color:'#8b5cf6' },
    tds_payment:                  { icon:'fa-percent',            title:'TDS <em>Payment</em>',        sub:'TDS remittance to government',      partyType:'Authority',  submitLabel:'Record TDS',       color:'#8b5cf6' },
    tax_refund:                   { icon:'fa-rotate-left',        title:'Tax <em>Refund</em>',         sub:'Inbound refund from government',    partyType:'Authority',  submitLabel:'Record Refund',    color:'#10b981' },
};

function showPaymentModal(type, refId, partyId, pendingAmount, partyName) {
    const cfg = pmTypeConfig[type] || pmTypeConfig['customer_receipt'];

    document.getElementById('payment_type').value         = type;
    document.getElementById('reference_id').value         = refId;
    document.getElementById('party_id').value             = partyId;
    document.getElementById('payment_amount').value       = '';
    document.getElementById('max_pending_amount').value   = round2(pendingAmount);

    // Header
    document.getElementById('pmModalTitle').innerHTML     = cfg.title;
    document.getElementById('pmModalSubtitle').textContent= cfg.sub;
    document.getElementById('pmModalIcon').innerHTML      = `<i class="fas ${cfg.icon}"></i>`;
    document.getElementById('pmSubmitLabel').textContent  = cfg.submitLabel;

    // Party strip
    document.getElementById('party_name_display').textContent = partyName;
    document.getElementById('pmAvatarInitial').textContent    = partyName.charAt(0).toUpperCase();
    document.getElementById('pmPartyType').textContent        = cfg.partyType;

    // Pending
    const formattedPending = round2(pendingAmount).toFixed(2);
    document.getElementById('pending_amount_display').textContent = '₹ ' + parseFloat(formattedPending).toLocaleString('en-IN');
    document.getElementById('remaining_calc').textContent         = '₹ ' + parseFloat(formattedPending).toLocaleString('en-IN');
    document.getElementById('remaining_calc').className           = 'pm-bal-val';
    document.getElementById('amount_warning').classList.remove('show');
    document.getElementById('pmPendingChip').style.display        = 'flex';
    document.getElementById('pmBalLabel').textContent             = 'Remaining after payment';

    // Demand dropdown
    const demandGroup    = document.getElementById('demand_selection_group');
    const demandDropdown = document.getElementById('demand_dropdown');
    demandDropdown.innerHTML = '<option value="">General / Oldest Unpaid (Default)</option>';
    demandGroup.style.display = 'none';

    if (type === 'customer_receipt') {
        fetch(`<?= BASE_URL ?>modules/api/get_booking_demands.php?booking_id=${refId}`)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.demands.length > 0) {
                    data.demands.forEach(d => {
                        const opt = document.createElement('option');
                        opt.value = d.id; opt.textContent = d.label;
                        demandDropdown.appendChild(opt);
                    });
                    demandGroup.style.display = 'block';
                }
            })
            .catch(e => console.error('Demand fetch error:', e));
    }

    openModal();
}

function showTaxModal(type) {
    const cfg = pmTypeConfig[type] || { icon:'fa-landmark', title:'Tax <em>Transaction</em>', sub:'Government tax payment or refund', partyType:'Authority', submitLabel:'Record Payment' };

    document.getElementById('payment_type').value       = type;
    document.getElementById('reference_id').value       = '0';
    document.getElementById('party_id').value           = '0';
    document.getElementById('payment_amount').value     = '';
    document.getElementById('max_pending_amount').value = '9999999999';

    document.getElementById('pmModalTitle').innerHTML     = cfg.title;
    document.getElementById('pmModalSubtitle').textContent= cfg.sub;
    document.getElementById('pmModalIcon').innerHTML      = `<i class="fas ${cfg.icon}"></i>`;
    document.getElementById('pmSubmitLabel').textContent  = cfg.submitLabel;

    document.getElementById('party_name_display').textContent = 'Government / Authority';
    document.getElementById('pmAvatarInitial').textContent    = 'G';
    document.getElementById('pmPartyType').textContent        = 'Tax Authority';
    document.getElementById('pmPendingChip').style.display    = 'none';

    document.getElementById('remaining_calc').textContent  = 'N/A';
    document.getElementById('remaining_calc').className    = 'pm-bal-val';
    document.getElementById('pmBalLabel').textContent      = 'Balance';
    document.getElementById('amount_warning').classList.remove('show');

    document.getElementById('demand_selection_group').style.display = 'none';

    openModal();
}

function sanitizeAmount(input) {
    let v = input.value.replace(/[^0-9.]/g, '');
    const parts = v.split('.');
    if (parts.length > 2) v = parts[0] + '.' + parts.slice(1).join('');
    if (parts[1]?.length > 2) v = parts[0] + '.' + parts[1].slice(0, 2);
    input.value = v;
}

function calculateBalance() {
    const type  = document.getElementById('payment_type').value;
    const refId = document.getElementById('reference_id').value;
    
    const isGenericTax = ['gst_payment','tds_payment','tax_refund'].includes(type) && (refId === '0' || refId === '');
    
    const displayEl = document.getElementById('remaining_calc');
    const warnEl    = document.getElementById('amount_warning');

    if (isGenericTax) {
        displayEl.textContent = 'N/A';
        displayEl.className   = 'pm-bal-val';
        warnEl.classList.remove('show');
        return;
    }

    const pending = round2(parseFloat(document.getElementById('max_pending_amount').value) || 0);
    const paid    = round2(parseFloat(document.getElementById('payment_amount').value)     || 0);
    const rem     = round2(pending - paid);

    const fmt = v => '₹\u00A0' + Math.abs(v).toLocaleString('en-IN', { minimumFractionDigits:2, maximumFractionDigits:2 });

    if (rem > 0) {
        displayEl.textContent = fmt(rem);
        displayEl.className   = 'pm-bal-val';
        warnEl.classList.remove('show');
    } else if (rem === 0) {
        displayEl.textContent = fmt(0);
        displayEl.className   = 'pm-bal-val paid-off';
        warnEl.classList.remove('show');
    } else {
        displayEl.textContent = fmt(rem) + ' excess';
        displayEl.className   = 'pm-bal-val overpaid';
        warnEl.classList.add('show');
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

    // Toggle Bank dropdown based on Payment Mode (radio buttons)
    const modeRadios = document.querySelectorAll('input[name="payment_mode"]');
    const bankGroup  = document.getElementById('bank_account_group');
    const bankSelect = document.getElementById('company_account_id');
    const bankStar   = document.querySelector('.bank-required-star');

    function getSelectedMode() {
        const checked = document.querySelector('input[name="payment_mode"]:checked');
        return checked ? checked.value : 'bank';
    }

    function toggleBankGroup() {
        const isCash = getSelectedMode() === 'cash';
        bankGroup.style.display = isCash ? 'none' : '';
        if (isCash) {
            bankSelect.value = '';  // clear selection when hidden
        }
    }

    modeRadios.forEach(r => r.addEventListener('change', toggleBankGroup));
    toggleBankGroup(); // initialize

    // Form submit validation — require bank account for non-cash modes
    const payForm = document.querySelector('#paymentModal form');
    const modalBody = document.querySelector('#paymentModal .pm-body');

    function showModalToast(msg) {
        // Remove any existing toast
        const old = modalBody.querySelector('.pm-toast');
        if (old) old.remove();

        const toast = document.createElement('div');
        toast.className = 'pm-toast error';
        toast.innerHTML = `<i class="fas fa-circle-exclamation"></i><span>${msg}</span><button type="button" class="pm-toast-close" onclick="this.parentElement.remove()">×</button>`;
        modalBody.prepend(toast);
        modalBody.scrollTop = 0;

        // Auto-dismiss after 4s
        setTimeout(() => { if (toast.parentElement) toast.remove(); }, 4000);
    }

    payForm.addEventListener('submit', function(e) {
        const mode = getSelectedMode();
        if (mode !== 'cash' && !bankSelect.value) {
            e.preventDefault();
            bankSelect.style.borderColor = '#ef4444';
            bankSelect.style.boxShadow  = '0 0 0 3px rgba(239,68,68,0.15)';
            bankSelect.focus();
            showModalToast('Please select a bank account for <strong>' + mode.toUpperCase() + '</strong> payments.');
            return false;
        }
    });

    // Reset border on change
    bankSelect.addEventListener('change', function() {
        this.style.borderColor = '';
        this.style.boxShadow  = '';
        const toast = modalBody.querySelector('.pm-toast');
        if (toast) toast.remove();
    });
});

function showTaxBillModal(type, refId, partyId, pendingAmount, partyName) {
    document.getElementById('payment_type').value = type;
    document.getElementById('reference_id').value = refId;
    document.getElementById('party_id').value = partyId;
    document.getElementById('payment_amount').value = '';
    document.getElementById('max_pending_amount').value = round2(pendingAmount);
    
    let title = type === 'tds_payment' ? 'Pay Pending TDS' : 'Pay Pending GST';
    let icon = type === 'tds_payment' ? 'fa-percent' : 'fa-file-invoice';
    
    const formattedPending = round2(pendingAmount).toFixed(2);
    document.getElementById('pending_amount_display').textContent = '₹ ' + formattedPending;
    document.getElementById('remaining_calc').textContent = '₹ ' + formattedPending;
    document.getElementById('remaining_calc').style.color = 'var(--ink)';
    document.getElementById('amount_warning').classList.remove('show');

    const demandGroup = document.getElementById('demand_selection_group');
    demandGroup.style.display = 'none';

    document.getElementById('paymentModal').querySelector('h3').innerHTML = '<i class="fas ' + icon + '"></i> ' + title;

    openModal();
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>