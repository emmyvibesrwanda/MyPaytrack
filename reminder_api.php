<?php
require_once __DIR__ . '/includes/auth.php';
requireAuth();

header('Content-Type: application/json');
$conn = getDB();
$user_id = $_SESSION['user_id'];

$action = $_POST['action'] ?? '';

if ($action === 'add_reminder') {
    $customer_id = (int)$_POST['customer_id'];
    $reminder_date = $conn->real_escape_string($_POST['reminder_date']);
    $type = $conn->real_escape_string($_POST['type']);
    $message = $conn->real_escape_string($_POST['message']);
    
    $conn->query("INSERT INTO reminders (user_id, customer_id, reminder_date, type, message) 
                  VALUES ($user_id, $customer_id, '$reminder_date', '$type', '$message')");
    
    echo json_encode(['success' => true, 'message' => 'Reminder added successfully']);
} elseif ($action === 'delete') {
    $id = (int)$_POST['id'];
    $conn->query("DELETE FROM reminders WHERE id = $id AND user_id = $user_id");
    echo json_encode(['success' => true, 'message' => 'Reminder deleted']);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>