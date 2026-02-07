<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

function sanitize($data) {
    return strip_tags(trim($data));
}

function redirect($url) {
    header("Location: " . BASE_URL . $url);
    exit();
}

function setFlashMessage($type, $message) {
    $_SESSION['flash_type'] = $type;
    $_SESSION['flash_message'] = $message;
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $type = $_SESSION['flash_type'];
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_type']);
        unset($_SESSION['flash_message']);
        return ['type' => $type, 'message' => $message];
    }
    return null;
}

function formatDate($date, $format = DATE_FORMAT) {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

function formatCurrency($amount) {
    return formatCurrencyIndian($amount);
}

function formatCurrencyShort($amount) {
    if ($amount >= 10000000) { // 1 Crore
        return '₹ ' . number_format($amount / 10000000, 2) . ' Cr';
    } elseif ($amount >= 100000) { // 1 Lakh
        return '₹ ' . number_format($amount / 100000, 2) . ' L';
    } elseif ($amount >= 1000) { // 1 Thousand (optional, mainly for small numbers)
        return '₹ ' . number_format($amount / 1000, 1) . ' K';
    }
    return '₹ ' . number_format($amount, 0);
}

function formatCurrencyIndian($amount) {
    $amount = (float)$amount;
    $is_negative = $amount < 0;
    $amount = abs($amount);
    
    $decimal = round($amount - ($no = floor($amount)), 2) * 100;
    $hundred = null;
    $digits_length = strlen($no);
    $i = 0;
    $str = array();
    
    // Simplified logic for Indian Number format (1,50,000.00)
    $decimalPart = number_format($amount - floor($amount), 2);
    $decimalPart = substr($decimalPart, -3); // Get .00
    
    $num = floor($amount);
    $explrestunits = "";
    if (strlen($num) > 3) {
        $lastthree = substr($num, strlen($num) - 3, strlen($num));
        $restunits = substr($num, 0, strlen($num) - 3); // extracts the last three digits
        $restunits = (strlen($restunits) % 2 == 1) ? "0" . $restunits : $restunits; // explodes the remaining digits in 2's formats, adds a zero in the beginning to maintain the 2's grouping.
        $expunit = str_split($restunits, 2);
        for ($i = 0; $i < sizeof($expunit); $i++) {
            // creates each of the 2's group and adds a comma to the end
            if ($i == 0) {
                $explrestunits .= (int)$expunit[$i] . ","; // if is first value , convert into integer
            } else {
                $explrestunits .= $expunit[$i] . ",";
            }
        }
        $thecash = $explrestunits . $lastthree;
    } else {
        $thecash = $num;
    }
    
    $result = '₹ ' . $thecash . $decimalPart;
    if ($is_negative) {
        return '-' . $result;
    }
    return $result;
}

function generateChallanNo($type, $db) {
    $prefix = '';
    switch ($type) {
        case 'material':
            $prefix = 'MAT';
            break;
        case 'labour':
            $prefix = 'LAB';
            break;
        case 'customer':
            $prefix = 'CUST';
            break;
    }
    
    $year = date('Y');
    $sql = "SELECT challan_no FROM challans WHERE challan_type = ? AND YEAR(created_at) = ? ORDER BY id DESC LIMIT 1";
    $stmt = $db->query($sql, [$type, $year]);
    $last = $stmt->fetch();
    
    if ($last) {
        $last_no = intval(substr($last['challan_no'], -4));
        $new_no = $last_no + 1;
    } else {
        $new_no = 1;
    }
    
    return $prefix . '/' . $year . '/' . str_pad($new_no, 4, '0', STR_PAD_LEFT);
}

function logAudit($action, $table, $record_id, $old_values = null, $new_values = null) {
    if (!isset($_SESSION['user_id'])) return;
    
    $db = Database::getInstance();
    $data = [
        'user_id' => $_SESSION['user_id'],
        'action' => $action,
        'table_name' => $table,
        'record_id' => $record_id,
        'old_values' => $old_values ? json_encode($old_values) : null,
        'new_values' => $new_values ? json_encode($new_values) : null,
        'ip_address' => $_SERVER['REMOTE_ADDR']
    ];
    
    $db->insert('audit_trail', $data);
}

function checkPermission($allowed_roles) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_role'])) {
        // Not logged in or session expired
        redirect('modules/auth/login.php');
    }
    
    // Ensure input is an array
    if (!is_array($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }
    
    if (!in_array($_SESSION['user_role'], $allowed_roles)) {
        // Log unauthorized access attempt
        error_log("Unauthorized access attempt by user ID {$_SESSION['user_id']} ({$_SESSION['user_role']}) to " . $_SERVER['PHP_SELF']);
        
        setFlashMessage('error', 'You do not have permission to access this page.');
        redirect('modules/dashboard/index.php');
    }
}

function getProjectName($project_id) {
    $db = Database::getInstance();
    $stmt = $db->select('projects', 'id = ?', [$project_id], 'project_name');
    $result = $stmt->fetch();
    return $result ? $result['project_name'] : 'Unknown';
}

function getPartyName($party_id) {
    $db = Database::getInstance();
    $stmt = $db->select('parties', 'id = ?', [$party_id], 'name');
    $result = $stmt->fetch();
    return $result ? $result['name'] : 'Unknown';
}

function getFlatNo($flat_id) {
    $db = Database::getInstance();
    $stmt = $db->select('flats', 'id = ?', [$flat_id], 'flat_no');
    $result = $stmt->fetch();
    return $result ? $result['flat_no'] : 'Unknown';
}

function updateBookingTotals($booking_id) {
    $db = Database::getInstance();
    
    // 1. Calculate total received
    $sql = "SELECT COALESCE(SUM(amount), 0) as total_received 
            FROM payments 
            WHERE reference_type = 'booking' AND reference_id = ?";
    $stmt = $db->query($sql, [$booking_id]);
    $result = $stmt->fetch();
    $total_received = round($result['total_received'], 2);
    
    // 2. Update booking total and pending amount
    // Fetch agreement value first
    $booking_info = $db->select('bookings', 'id=?', [$booking_id])->fetch();
    $agreement_value = $booking_info['agreement_value'] ?? 0;
    $total_pending = max(0, $agreement_value - $total_received);

    $db->update('bookings', 
        [
            'total_received' => $total_received,
            'total_pending' => $total_pending
        ], 
        'id = ?', 
        ['id' => $booking_id]
    );

    // 3. Check for Full Payment and Update Flat Status
    $booking = $db->select('bookings', 'id = ?', [$booking_id])->fetch();
    if ($booking) {
        $flat_id = $booking['flat_id'];
        $agreement_value = $booking['agreement_value'];
        
        // Fetch current flat status
        $flat = $db->select('flats', 'id = ?', [$flat_id])->fetch();
        
        if ($flat) {
            if ($total_received >= $agreement_value) {
                // Mark as SOLD if paid in full
                if ($flat['status'] !== 'sold') {
                    $db->update('flats', ['status' => 'sold'], 'id = ?', ['id' => $flat_id]);
                    logAudit('auto_update', 'flats', $flat_id, ['status' => $flat['status']], ['status' => 'sold']);
                }
            } else {
                // Revert to BOOKED if balance remains (e.g. after payment deletion)
                if ($flat['status'] === 'sold') {
                    $db->update('flats', ['status' => 'booked'], 'id = ?', ['id' => $flat_id]);
                    logAudit('auto_update', 'flats', $flat_id, ['status' => 'sold'], ['status' => 'booked']);
                }
            }
        }
    }
    
    // 4. Update Demand Allocation (FIFO Logic)
    updateBookingDemands($booking_id, $total_received);
}

function updateBookingDemands($booking_id, $total_received_buffer_unused) {
    $db = Database::getInstance();
    
    // 1. Reset all demands to 0 first (clean slate calculation)
    $db->update('booking_demands', ['paid_amount' => 0, 'status' => 'pending'], 'booking_id = ?', ['booking_id' => $booking_id]);

    // 2. Fetch all demands ordered by date
    $demands = $db->query("SELECT * FROM booking_demands WHERE booking_id = ? ORDER BY generated_date ASC", [$booking_id])->fetchAll();
    if (empty($demands)) return;

    $demandMap = [];
    foreach ($demands as $d) {
        $demandMap[$d['id']] = [
            'amount' => $d['demand_amount'],
            'paid' => 0,
            'status' => 'pending'
        ];
    }

    // 3. Fetch all payments for this booking
    $payments = $db->query("SELECT amount, demand_id FROM payments WHERE reference_type = 'booking' AND reference_id = ?", [$booking_id])->fetchAll();

    $general_pool = 0;

    // 4. Allocation Pass 1: Targeted Payments
    foreach ($payments as $p) {
        if (!empty($p['demand_id']) && isset($demandMap[$p['demand_id']])) {
            $demandMap[$p['demand_id']]['paid'] += $p['amount'];
        } else {
            $general_pool += $p['amount'];
        }
    }

    // 5. Allocation Pass 2: General Pool (FIFO)
    foreach ($demands as $d) {
        $id = $d['id'];
        $needed = $demandMap[$id]['amount'] - $demandMap[$id]['paid'];

        if ($needed > 0 && $general_pool > 0) {
            $allocate = min($needed, $general_pool);
            $demandMap[$id]['paid'] += $allocate;
            $general_pool -= $allocate;
        }

        // Update Status
        if ($demandMap[$id]['paid'] >= $demandMap[$id]['amount'] - 1) { // -1 for tiny float diffs
            $demandMap[$id]['status'] = 'paid';
        } elseif ($demandMap[$id]['paid'] > 0) {
            $demandMap[$id]['status'] = 'partial';
        } else {
            $demandMap[$id]['status'] = 'pending';
        }

        // Persist to DB
        $db->update('booking_demands', 
            [
                'paid_amount' => $demandMap[$id]['paid'], 
                'status' => $demandMap[$id]['status']
            ], 
            'id = ?', 
            ['id' => $id]
        );
    }
}

function updateChallanPaidAmount($challan_id) {
    $db = Database::getInstance();
    
    $sql = "SELECT COALESCE(SUM(amount), 0) as paid_amount 
            FROM payments 
            WHERE reference_type = 'challan' AND reference_id = ?";
    $stmt = $db->query($sql, [$challan_id]);
    $result = $stmt->fetch();
    
    $paid_amount = round($result['paid_amount'], 2);
    
    $stmt = $db->select('challans', 'id = ?', [$challan_id], 'total_amount');
    $challan = $stmt->fetch();
    
    $pending_amount = max(0, round($challan['total_amount'] - $paid_amount, 2));

    $status = 'pending';
    if ($paid_amount >= $challan['total_amount']) {
        $status = 'paid';
    } elseif ($paid_amount > 0) {
        $status = 'partial';
    }
    
    $db->update('challans', 
        [
            'paid_amount' => $paid_amount, 
            'pending_amount' => $pending_amount,
            'status' => $status
        ], 
        'id = ?', 
        ['id' => $challan_id]
    );
}

function updateMaterialStock($material_id, $quantity, $add = true) {
    $db = Database::getInstance();
    
    $operator = $add ? '+' : '-';
    $sql = "UPDATE materials SET current_stock = current_stock {$operator} ? WHERE id = ?";
    $db->query($sql, [$quantity, $material_id]);
}

function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

function jsonResponse($success, $message, $data = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

function validateRequired($fields, $data) {
    $errors = [];
    foreach ($fields as $field => $label) {
        if (empty($data[$field])) {
            $errors[] = $label . ' is required';
        }
    }
    return $errors;
}

function loadEnv($path) {
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

function generate_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}


function updateBillPaidAmount($bill_id) {
    $db = Database::getInstance();
    
    $sql = "SELECT COALESCE(SUM(amount), 0) as paid_amount 
            FROM payments 
            WHERE reference_type = 'bill' AND reference_id = ?";
    $stmt = $db->query($sql, [$bill_id]);
    $result = $stmt->fetch();
    
    $paid_amount = round($result['paid_amount'], 2);
    
    $stmt = $db->select('bills', 'id = ?', [$bill_id], 'amount');
    $bill = $stmt->fetch();
    
    $status = 'pending';
    if ($paid_amount >= $bill['amount']) {
        $status = 'paid';
    } elseif ($paid_amount > 0) {
        $status = 'partial';
    }
    
    $db->update('bills', 
        ['paid_amount' => $paid_amount, 'status' => $status], 
        'id = ?', 
        ['id' => $bill_id]
    );
}

