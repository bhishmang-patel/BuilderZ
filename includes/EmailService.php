<?php
// Require PHPMailer files
require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

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
        return 'Estate Exis';
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
        $body .= "<p><strong>Agreement Value:</strong> ₹" . number_format($bookingDetails['agreement_value'], 2) . "</p>";
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
        $body .= "<p>We have successfully received your payment of <strong>₹" . number_format($paymentDetails['amount'], 2) . "</strong>.</p>";
        
        if (isset($paymentDetails['remaining_balance']) && $paymentDetails['remaining_balance'] > 0) {
            $body .= "<p>Your pending balance is <strong>₹" . number_format($paymentDetails['remaining_balance'], 2) . "</strong>.</p>";
            $body .= "<p>We will notify you for the next installments as per the construction progress.</p>";
        } else {
            $body .= "<p>This was your last installment! Your total balance is now fully paid. Congratulations!</p>";
        }
        $body .= "<br><p>Best Regards,<br><strong>{$companyName} Team</strong></p>";
        $body .= "</div>";
        
        return self::sendEmail($customerEmail, $subject, $body, $pdfContent, $pdfFilename);
    }
    
    public static function sendDemandGeneration($customerEmail, $customerName, $demandDetails) {
        if (empty($customerEmail)) return false;

        $companyName = self::getCompanyName();

        $subject = "Payment Demand Generated";
        $body = "<div style='font-family: Arial, sans-serif; color: #333; line-height: 1.6;'>";
        $body .= "<h2>Dear {$customerName},</h2>";
        $body .= "<p>A new payment demand has been generated for your booking.</p>";
        $body .= "<p><strong>Construction Stage:</strong> {$demandDetails['stage_name']}</p>";
        $body .= "<p><strong>Amount Demanded:</strong> ₹" . number_format($demandDetails['amount'], 2) . "</p>";
        $body .= "<p>Please process the payment at your earliest convenience to avoid any delays.</p>\n        <p><em>(If you have already paid this amount, please ignore this email.)</em></p>";
        $body .= "<br><p>Best Regards,<br><strong>{$companyName} Team</strong></p>";
        $body .= "</div>";
        
        return self::sendEmail($customerEmail, $subject, $body);
    }
}
