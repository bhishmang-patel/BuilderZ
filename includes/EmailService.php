<?php
// Require PHPMailer files
require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/functions.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    
    private static function getCompanyName() {
        require_once __DIR__ . '/../config/database.php';
        $db = Database::getInstance();
        try {
            $stmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'company_name'");
            if ($stmt) {
                $row = $stmt->fetch();
                if ($row && !empty(trim($row['setting_value']))) {
                    return trim($row['setting_value']);
                }
            }
        } catch (\Exception $e) {
            // ignore if table/etc doesn't exist
        }
        return 'EstateAxis';
    }

    // Core function to send HTML email using PHPMailer
    public static function sendEmail($to, $subject, $body, $attachmentBinary = null, $attachmentName = null) {
        if (empty($to)) return false;
        
        $companyName = self::getCompanyName();
        
        $mail = new PHPMailer(true);

        try {
            //Server settings
            $mail->isSMTP();                                            // Send using SMTP
            $mail->Host       = 'smtp.gmail.com';                       // Set the SMTP server to send through
            $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
            $mail->Username   = 'info.deployx@gmail.com';               // SMTP username
            $mail->Password   = 'imqm bwgz yuqa qsxu';                  // SMTP password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable implicit TLS encryption
            $mail->Port       = 587;                                    // TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

            //Recipients
            $mail->setFrom('info.deployx@gmail.com', $companyName . ' Notifications');
            $mail->addAddress($to);                                     // Add a recipient
            $mail->addReplyTo('info.deployx@gmail.com', $companyName . ' Support');

            // Content
            $mail->CharSet = 'UTF-8';
            $mail->isHTML(true);                                        // Set email format to HTML
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], "\n", $body));

            if ($attachmentBinary && $attachmentName) {
                // Attach the generated PDF binary string
                $mail->addStringAttachment($attachmentBinary, $attachmentName, 'base64', 'application/pdf');
            }

            return $mail->send();
        } catch (Exception $e) {
            // Log the error if mail fails
            error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
            return false;
        }
    }

    public static function sendBookingConfirmation($customerEmail, $customerName, $bookingDetails, $pdfContent = null, $pdfFilename = null) {
        if (empty($customerEmail)) return false;

        $companyName = self::getCompanyName();

        $subject = "Booking Confirmed: Congratulations!";
        $body = "<div style='font-family: Arial, sans-serif; color: #333; line-height: 1.6;'>";
        $body .= "<h2>Dear {$customerName},</h2>";
        $body .= "<p>Greetings from {$companyName}!</p>";
        $body .= "<p>Your booking for <strong>Flat {$bookingDetails['flat_no']}</strong> in <strong>{$bookingDetails['project_name']}</strong> has been successfully confirmed.</p>";
        $body .= "<p><strong>Agreement Value:</strong> ₹" . formatNumberIndian($bookingDetails['agreement_value']) . "</p>";
        $body .= "<p>We will notify you for your upcoming installments automatically based on the progress of the construction. Thank you for choosing us.</p>";
        $body .= "<br><p>Best Regards,<br><strong>{$companyName} Team</strong></p>";
        $body .= "</div>";
        
        return self::sendEmail($customerEmail, $subject, $body, $pdfContent, $pdfFilename);
    }
    
    public static function sendInstallmentReceipt($customerEmail, $customerName, $paymentDetails, $pdfContent = null, $pdfFilename = null) {
        if (empty($customerEmail)) return false;

        $companyName = self::getCompanyName();

        $subject = "Payment Receipt Received";
        $body = "<div style='font-family: Arial, sans-serif; color: #333; line-height: 1.6;'>";
        $body .= "<h2>Dear {$customerName},</h2>";
        $body .= "<p>Greetings from {$companyName}!</p>";
        $body .= "<p>We have successfully received your payment of <strong>₹" . formatNumberIndian($paymentDetails['amount']) . "</strong>.</p>";
        
        if (isset($paymentDetails['remaining_balance']) && $paymentDetails['remaining_balance'] > 0) {
            $body .= "<p>Your pending balance is <strong>₹" . formatNumberIndian($paymentDetails['remaining_balance']) . "</strong>.</p>";
            $body .= "<p>We will notify you for the next installments as per the construction progress.</p>";
        } else {
            $body .= "<p>This was your last installment! Your total balance is now fully paid. Congratulations!</p>";
        }
        $body .= "<br><p>Best Regards,<br><strong>{$companyName} Team</strong></p>";
        $body .= "</div>";
        
        return self::sendEmail($customerEmail, $subject, $body, $pdfContent, $pdfFilename);
    }
    
    public static function sendDemandGeneration($customerEmail, $customerName, $demandDetails, $pdfContent = null, $pdfFilename = null) {
        if (empty($customerEmail)) return false;

        $companyName = self::getCompanyName();

        $subject = "Payment Demand Generated - Unit " . ($demandDetails['flat_no'] ?? '');
        
        $body = "<div style='font-family: Arial, sans-serif; color: #333; line-height: 1.6; max-width: 600px;'>";
        $body .= "<h2>Dear {$customerName},</h2>";
        $body .= "<p>A new payment demand has been generated for your booking of <strong>Unit " . ($demandDetails['flat_no'] ?? 'N/A') . "</strong> in <strong>" . ($demandDetails['project_name'] ?? 'N/A') . "</strong>.</p>";
        
        $body .= "<table style='width:100%; border-collapse: collapse; margin: 20px 0;'>";
        
        // Arrears
        if (isset($demandDetails['arrears']) && $demandDetails['arrears'] > 0) {
            $body .= "<tr><td style='padding: 8px; border: 1px solid #ddd; color: #ea580c;'>Arrears (Previous Dues)</td>";
            $body .= "<td style='padding: 8px; border: 1px solid #ddd; text-align: right; color: #ea580c;'>₹" . formatNumberIndian($demandDetails['arrears']) . "</td></tr>";
        }
        
        // Current Demand
        $body .= "<tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Current Demand:</strong> {$demandDetails['stage_name']}</td>";
        $body .= "<td style='padding: 8px; border: 1px solid #ddd; text-align: right;'>₹" . formatNumberIndian($demandDetails['amount']) . "</td></tr>";
        
        // Less Paid (If partial payment already made)
        if (isset($demandDetails['paid_amount']) && $demandDetails['paid_amount'] > 0) {
            $body .= "<tr><td style='padding: 8px; border: 1px solid #ddd;'>Less: Already Paid for this stage</td>";
            $body .= "<td style='padding: 8px; border: 1px solid #ddd; text-align: right; color:#ef4444;'>- ₹" . formatNumberIndian($demandDetails['paid_amount']) . "</td></tr>";
        }

        // Total Payable
        if (isset($demandDetails['total_payable'])) {
            $body .= "<tr style='background-color: #f8fafc;'><td style='padding: 8px; border: 1px solid #ddd;'><strong>Total Amount Payable Now</strong></td>";
            $body .= "<td style='padding: 8px; border: 1px solid #ddd; text-align: right; font-size: 16px; color:#0f172a;'><strong>₹" . formatNumberIndian($demandDetails['total_payable']) . "</strong></td></tr>";
        }
        
        $body .= "</table>";
        
        $body .= "<p>Please find the detailed Demand Letter attached to this email.</p>";
        $body .= "<p>Kindly process the payment at your earliest convenience to avoid any delays.</p>";
        $body .= "<p><em>(If you have already paid this amount, please ignore this email.)</em></p>";
        $body .= "<br><p>Best Regards,<br><strong>{$companyName} Team</strong></p>";
        $body .= "</div>";
        
        return self::sendEmail($customerEmail, $subject, $body, $pdfContent, $pdfFilename);
    }
}
