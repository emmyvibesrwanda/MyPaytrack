<?php
// Email queue system - stores emails in database and sends them via cron job

function queueEmail($to, $subject, $message, $from = 'noreply@paytrack.com') {
    $conn = getDB();
    $to = $conn->real_escape_string($to);
    $subject = $conn->real_escape_string($subject);
    $message = $conn->real_escape_string($message);
    $from = $conn->real_escape_string($from);
    
    $conn->query("INSERT INTO email_queue (to_email, subject, message, from_email, status, created_at) 
                  VALUES ('$to', '$subject', '$message', '$from', 'pending', NOW())");
    
    return $conn->insert_id;
}

function processEmailQueue() {
    $conn = getDB();
    $result = $conn->query("SELECT * FROM email_queue WHERE status = 'pending' ORDER BY created_at ASC LIMIT 10");
    
    while ($email = $result->fetch_assoc()) {
        $sent = mail($email['to_email'], $email['subject'], $email['message'], "From: {$email['from_email']}\r\n");
        
        if ($sent) {
            $conn->query("UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = {$email['id']}");
        } else {
            $conn->query("UPDATE email_queue SET status = 'failed', attempts = attempts + 1 WHERE id = {$email['id']}");
        }
    }
}

// Modified send functions using queue
function sendUpgradeRequestEmailQueued($user_data, $request_data) {
    $to = ADMIN_EMAIL;
    $subject = "NEW UPGRADE REQUEST - PayTrack";
    
    $message = "
    NEW UPGRADE REQUEST FROM {$user_data['full_name']}
    
    User: {$user_data['full_name']}
    Email: {$user_data['email']}
    Phone: {$user_data['phone']}
    Transaction Ref: {$request_data['transaction_reference']}
    Amount: 3,000 RWF
    
    Login to admin panel to approve: " . SITE_URL . "/admin_dashboard.php
    ";
    
    return queueEmail($to, $subject, $message);
}
?>