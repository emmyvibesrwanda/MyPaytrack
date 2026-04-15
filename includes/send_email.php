<?php
require_once __DIR__ . '/../phpmailer/src/Exception.php';
require_once __DIR__ . '/../phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendEmail($to, $subject, $message, $to_name = '') {
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'becypher01@gmail.com';
        $mail->Password   = 'kqwyhtxvtmwgvavu'; // Replace with your app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Disable SSL verification for localhost
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Recipients
        $mail->setFrom('becypher01@gmail.com', 'PayTrack System');
        $mail->addAddress($to, $to_name ?: 'Customer');
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->AltBody = strip_tags($message);
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email Error: {$mail->ErrorInfo}");
        return false;
    }
}

// Test function
function testEmail() {
    $test_message = "
    <html>
    <head><title>Test Email</title></head>
    <body>
        <h2>PayTrack Email Test</h2>
        <p>If you receive this, your email configuration is working!</p>
        <p>Time: " . date('Y-m-d H:i:s') . "</p>
    </body>
    </html>";
    
    return sendEmail('becypher01@gmail.com', 'PayTrack Email Test', $test_message, 'Admin');
}
?>