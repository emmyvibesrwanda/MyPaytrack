<?php
// Load PHPMailer
require_once __DIR__ . '/../phpmailer/src/Exception.php';
require_once __DIR__ . '/../phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ============ UPDATE THIS WITH YOUR APP PASSWORD ============
define('SMTP_PASSWORD', 'kqwyhtxvtmwgvavu'); // ← IMPORTANT!
// ============================================================

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'becypher01@gmail.com');
define('SMTP_FROM', 'becypher01@gmail.com');
define('SMTP_FROM_NAME', 'PayTrack System');
define('ADMIN_EMAIL', 'becypher01@gmail.com');

function sendEmail($to, $subject, $message, $to_name = '') {
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        
        // Disable SSL verification for localhost
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Recipients
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to, $to_name ?: 'Customer');
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->AltBody = strip_tags($message);
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Email Error: " . $mail->ErrorInfo);
        return false;
    }
}

// Simple reminder email function
function sendReminderEmail($to, $customer_name, $message) {
    if (empty($to)) {
        return false;
    }
    
    $subject = "Payment Reminder from PayTrack";
    
    $html_message = "
    <html>
    <body>
        <h3>Payment Reminder</h3>
        <p>Dear <strong>" . htmlspecialchars($customer_name) . "</strong>,</p>
        <p>" . nl2br(htmlspecialchars($message)) . "</p>
        <p>Thank you for your cooperation.</p>
        <hr>
        <p><small>This is an automated message from PayTrack System.</small></p>
    </body>
    </html>";
    
    return sendEmail($to, $subject, $html_message, $customer_name);
}

// Test function
function testEmail() {
    $test_message = "<h2>Test Email</h2><p>If you receive this, your email configuration is working!</p>";
    return sendEmail(ADMIN_EMAIL, 'PayTrack Email Test', $test_message, 'Admin');
}
?>