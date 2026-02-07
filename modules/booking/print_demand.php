<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireAuth();
checkPermission(['admin', 'project_manager', 'accountant']);

$db = Database::getInstance();
$demand_id = $_GET['id'] ?? null;

if (!$demand_id) {
    die("Invalid Demand ID");
}

// Fetch Demand Details
$sql = "SELECT bd.*, 
               b.booking_date, b.agreement_value, b.total_received,
               b.id as booking_ref_id,
               p.name as customer_name, p.mobile, p.address as customer_address,
               pr.project_name, pr.location as project_location,
               f.flat_no, f.floor
        FROM booking_demands bd
        JOIN bookings b ON bd.booking_id = b.id
        JOIN parties p ON b.customer_id = p.id
        JOIN projects pr ON b.project_id = pr.id
        JOIN flats f ON b.flat_id = f.id
        WHERE bd.id = ?";

$stmt = $db->query($sql, [$demand_id]);
$data = $stmt->fetch();

if (!$data) {
    die("Demand not found");
}

// Calculate Arrears (Previous Unpaid Dues)
$arrears = 0;
// Fetch all previous demands for this booking that are older than current demand
$prev_demands = $db->query(
    "SELECT stage_name, demand_amount, paid_amount FROM booking_demands 
     WHERE booking_id = ? AND generated_date < ? AND id != ?", 
    [$data['booking_id'], $data['generated_date'], $demand_id]
)->fetchAll();

foreach ($prev_demands as $pd) {
    // Round to 2 decimals to avoid float errors
    $pending = round($pd['demand_amount'] - $pd['paid_amount'], 2);
    if ($pending > 0) {
        $arrears += $pending;
    }
}

$current_demand_amount = $data['demand_amount'];
// If current demand is partially paid, we show the remaining amount?
// Usually a demand letter is for the FULL amount of the stage.
// But if they paid half, we should probably demand the remainder?
// Let's assume the demand letter is for the *Balance* of the current stage + Arrears.
// However, standard practice: "Demand for X Stage: 100,000". If they paid 50k already, maybe show "Less: Paid".
// For now, let's stick to: Current Stage Full Amount (or Remaining?)
// The user's system tracks `paid_amount` on demands.
// If I reprint a demand letter for a partially paid stage, it should probably show the *remaining* due.
$current_due = round($data['demand_amount'] - $data['paid_amount'], 2);

$total_payable_now = $current_due + $arrears;

// Total Project Balance (Future + Current)
$total_project_balance = $data['agreement_value'] - $data['total_received'];

// Fetch Company Settings
$settings = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$company_name = $settings['company_name'] ?? APP_NAME;
$company_address = $settings['company_address'] ?? ' Registered Office: 123, Real Estate Hub, Business District, City - 390001';
$company_phone = $settings['company_phone'] ?? '+91 98765 43210';
$company_email = $settings['company_email'] ?? 'accounts@builderz.com';
$company_logo = $settings['company_logo'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demand Letter - <?= htmlspecialchars($data['customer_name']) ?></title>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            line-height: 1.6;
            color: #000;
            max-width: 800px;
            margin: 0 auto;
            padding: 40px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 2px solid #000;
            padding-bottom: 20px;
        }

        .logo-img {
            max-height: 80px;
            margin-bottom: 15px;
        }

        .company-name {
            font-size: 28px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0;
        }

        .company-address {
            font-size: 14px;
            margin-top: 5px;
        }

        .meta-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .recipient-box {
            width: 50%;
        }

        .date-box {
            text-align: right;
            width: 40%;
        }

        .subject {
            font-weight: bold;
            text-decoration: underline;
            margin: 20px 0;
            font-size: 16px;
        }

        .content {
            text-align: justify;
            margin-bottom: 30px;
        }

        .financial-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .financial-table th, .financial-table td {
            border: 1px solid #000;
            padding: 10px;
            text-align: left;
        }

        .financial-table th {
            background-color: #f0f0f0;
        }

        .amount-col {
            text-align: right;
        }

        .footer {
            margin-top: 60px;
            display: flex;
            justify-content: space-between;
        }

        .signatory {
            text-align: center;
        }
        
        .print-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 12px 24px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: sans-serif;
            font-weight: bold;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        @media print {
            .print-btn {
                display: none;
            }
            body {
                padding: 0;
                margin: 2cm;
            }
        }
    </style>
</head>
<body>

    <button class="print-btn" onclick="window.print()">Print Letter</button>

    <div class="header">
        <?php if($company_logo && file_exists(__DIR__ . '/../../' . $company_logo)): ?>
            <img src="<?= BASE_URL . $company_logo ?>" alt="Logo" class="logo-img">
        <?php endif; ?>
        
        <h1 class="company-name"><?= htmlspecialchars($company_name) ?></h1>
        <div class="company-address">
            <?= nl2br(htmlspecialchars($company_address)) ?><br>
            Phone: <?= htmlspecialchars($company_phone) ?> | Email: <?= htmlspecialchars($company_email) ?>
        </div>
    </div>

    <div class="meta-row">
        <div class="recipient-box">
            <strong>To,</strong><br>
            <?= htmlspecialchars($data['customer_name']) ?><br>
            Phone: <?= htmlspecialchars($data['mobile']) ?><br>
            <?= nl2br(htmlspecialchars($data['customer_address'] ?? '')) ?>
        </div>
        <div class="date-box">
            <strong>Date:</strong> <?= date('d-m-Y') ?><br>
            <strong>Demand Ref:</strong> DEM/<?= date('Y') ?>/<?= str_pad($data['id'], 4, '0', STR_PAD_LEFT) ?>
        </div>
    </div>

    <div class="subject">
        Subject: Demand for Payment upon completion of <?= htmlspecialchars($data['stage_name']) ?> stage.
    </div>

    <div class="content">
        <p>Dear Sir/Madam,</p>

        <p>
            We are pleased to inform you that the construction of your booked unit <strong><?= htmlspecialchars($data['flat_no']) ?></strong> 
            in project <strong><?= htmlspecialchars($data['project_name']) ?></strong> has reached the 
            <strong>"<?= htmlspecialchars($data['stage_name']) ?>"</strong> stage.
        </p>

        <p>
            As per the terms of our agreement and the construction-linked payment plan, the following amount is now due for payment. 
            We request you to kindly clear the outstanding dues at the earliest to help us maintain the construction pace.
        </p>

        <table class="financial-table">
            <tr>
                <th>Description</th>
                <th class="amount-col">Amount (₹)</th>
            </tr>
            
            <?php foreach ($prev_demands as $pd): 
                $pending = round($pd['demand_amount'] - $pd['paid_amount'], 2);
                if ($pending > 0):
            ?>
            <tr>
                <td style="color: #ea580c;">Arrears: <strong><?= htmlspecialchars($pd['stage_name']) ?></strong> Stage</td>
                <td class="amount-col" style="color: #ea580c;"><?= number_format($pending, 2) ?></td>
            </tr>
            <?php endif; endforeach; ?>

            <tr>
                <td>Demand for <strong><?= htmlspecialchars($data['stage_name']) ?></strong> Stage</td>
                <td class="amount-col"><?= number_format($data['demand_amount'], 2) ?></td>
            </tr>

            <?php if ($data['paid_amount'] > 0): ?>
            <tr>
                <td>Less: Amount Already Paid for this Stage</td>
                <td class="amount-col" style="color:#ef4444;">- <?= number_format($data['paid_amount'], 2) ?></td>
            </tr>
            <?php endif; ?>

            <tr>
                <td style="color: #666; font-size: 13px;"><em>(Total Agreement Value: ₹ <?= number_format($data['agreement_value'], 2) ?>)</em></td>
                <td></td>
            </tr>

            <tr style="background-color: #f8fafc;">
                <td><strong>Total amount payable now</strong></td>
                <td class="amount-col" style="font-size: 18px; color: #0f172a;"><strong>₹ <?= number_format($total_payable_now, 2) ?></strong></td>
            </tr>
        </table>

        <p>
            Kindly make the payment via Cheque/DD/NEFT in favor of <strong>"<?= APP_NAME ?>"</strong> on or before 
            <strong><?= date('d-m-Y', strtotime($data['due_date'])) ?></strong> in order to avoid interest charges.
        </p>
        
        <p>
            Total Outstanding Balance (Including Future Dues): <strong>₹ <?= number_format($total_project_balance, 2) ?></strong>
        </p>

        <p>Thank you for your continued patronage.</p>
    </div>

    <div class="footer">
        <div class="signatory">
            <br><br><br>
            __________________________<br>
            <strong>Authorised Signatory</strong><br>
            For, <?= htmlspecialchars($company_name) ?>
        </div>
    </div>

</body>
</html>
