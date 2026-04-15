<?php
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/includes/mail_config.php';

header('Content-Type: application/json');
$conn = getDB();
$action = $_POST['action'] ?? '';

if ($action === 'approve') {
    $request_id = (int)$_POST['request_id'];
    $user_id = (int)$_POST['user_id'];
    
    // Start transaction for faster processing
    $conn->begin_transaction();
    
    try {
        // Get user email
        $user_result = $conn->query("SELECT email, full_name FROM users WHERE id = $user_id");
        $user = $user_result->fetch_assoc();
        
        // Update payment request
        $conn->query("UPDATE payment_requests SET status = 'approved', processed_at = NOW() WHERE id = $request_id");
        
        // Upgrade user
        $conn->query("UPDATE users SET plan_id = 2, subscription_status = 'active' WHERE id = $user_id");
        
        // Record payment
        $conn->query("INSERT INTO payments_history (user_id, amount) VALUES ($user_id, 3000)");
        
        // Add notification
        $title = "Account Upgraded to Pro! 🎉";
        $message = "Your payment has been approved. You are now a Pro user!";
        $conn->query("INSERT INTO notifications (user_id, title, message, type) VALUES ($user_id, '$title', '$message', 'upgrade')");
        
        $conn->commit();
        
        // Send email in background (don't wait for response)
        $message_text = "Congratulations! Your payment has been verified and your account has been upgraded to Pro Plan.";
        sendPaymentConfirmationEmail($user['email'], $user['full_name'], 'approved', $message_text);
        
        echo json_encode(['success' => true, 'message' => 'Payment approved and user upgraded']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to approve payment']);
    }
    exit();
}

if ($action === 'reject') {
    $request_id = (int)$_POST['request_id'];
    $user_id = (int)$_POST['user_id'];
    
    $user_result = $conn->query("SELECT email, full_name FROM users WHERE id = $user_id");
    $user = $user_result->fetch_assoc();
    
    $conn->query("UPDATE payment_requests SET status = 'rejected', processed_at = NOW() WHERE id = $request_id");
    $conn->query("INSERT INTO notifications (user_id, title, message, type) VALUES ($user_id, 'Payment Update', 'Your payment request has been rejected.', 'payment')");
    
    $message_text = "We couldn't verify your payment. Please check your transaction details and try again.";
    sendPaymentConfirmationEmail($user['email'], $user['full_name'], 'rejected', $message_text);
    
    echo json_encode(['success' => true, 'message' => 'Payment rejected']);
    exit();
}

if ($action === 'upgrade') {
    $user_id = (int)$_POST['user_id'];
    
    $user_result = $conn->query("SELECT email, full_name FROM users WHERE id = $user_id");
    $user = $user_result->fetch_assoc();
    
    $conn->query("UPDATE users SET plan_id = 2, subscription_status = 'active' WHERE id = $user_id");
    $conn->query("INSERT INTO notifications (user_id, title, message, type) VALUES ($user_id, 'Account Upgraded', 'Admin has upgraded your account to Pro Plan.', 'upgrade')");
    
    $message_text = "Your account has been upgraded to Pro Plan by the administrator.";
    sendPaymentConfirmationEmail($user['email'], $user['full_name'], 'approved', $message_text);
    
    echo json_encode(['success' => true, 'message' => 'User upgraded to Pro']);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>