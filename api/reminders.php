<?php
require_once '../includes/auth.php';
requireAuth();
require_once '../includes/functions.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$conn = getDB();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $status = $_GET['status'] ?? '';
        if ($status) {
            $stmt = $conn->prepare("
                SELECT r.*, c.name as customer_name, d.invoice_number 
                FROM reminders r 
                JOIN customers c ON r.customer_id = c.id 
                LEFT JOIN debts d ON r.debt_id = d.id 
                WHERE r.user_id = ? AND r.status = ?
                ORDER BY r.reminder_date ASC
            ");
            $stmt->bind_param("is", $user_id, $status);
        } else {
            $stmt = $conn->prepare("
                SELECT r.*, c.name as customer_name, d.invoice_number 
                FROM reminders r 
                JOIN customers c ON r.customer_id = c.id 
                LEFT JOIN debts d ON r.debt_id = d.id 
                WHERE r.user_id = ? 
                ORDER BY r.reminder_date ASC
            ");
            $stmt->bind_param("i", $user_id);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $reminders = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $reminders]);
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? $_POST['action'] ?? '';
        
        if ($action === 'create') {
            $debt_id = $data['debt_id'] ?? $_POST['debt_id'] ?? null;
            $customer_id = $data['customer_id'] ?? $_POST['customer_id'] ?? '';
            $reminder_date = $data['reminder_date'] ?? $_POST['reminder_date'] ?? '';
            $message = $data['message'] ?? $_POST['message'] ?? '';
            $type = $data['type'] ?? $_POST['type'] ?? 'email';
            
            $stmt = $conn->prepare("INSERT INTO reminders (user_id, debt_id, customer_id, reminder_date, message, type) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiisss", $user_id, $debt_id, $customer_id, $reminder_date, $message, $type);
            
            if ($stmt->execute()) {
                logActivity($user_id, 'create_reminder', "Created reminder for customer ID: $customer_id");
                echo json_encode(['success' => true, 'message' => 'Reminder created successfully', 'reminder_id' => $conn->insert_id]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create reminder']);
            }
        } elseif ($action === 'delete') {
            $id = $data['id'] ?? $_POST['id'] ?? '';
            
            $stmt = $conn->prepare("DELETE FROM reminders WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $id, $user_id);
            
            if ($stmt->execute()) {
                logActivity($user_id, 'delete_reminder', "Deleted reminder ID: $id");
                echo json_encode(['success' => true, 'message' => 'Reminder deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete reminder']);
            }
        } elseif ($action === 'process_pending') {
            // Process pending reminders (should be called by cron job)
            $today = date('Y-m-d');
            $stmt = $conn->prepare("
                SELECT r.*, c.name, c.email, c.phone 
                FROM reminders r 
                JOIN customers c ON r.customer_id = c.id 
                WHERE r.user_id = ? AND r.status = 'pending' AND r.reminder_date <= ?
            ");
            $stmt->bind_param("is", $user_id, $today);
            $stmt->execute();
            $pending = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            $processed = 0;
            foreach ($pending as $reminder) {
                $sent = false;
                if ($reminder['type'] === 'email' && $reminder['email']) {
                    $sent = sendEmail($reminder['email'], 'Payment Reminder', $reminder['message']);
                } elseif ($reminder['type'] === 'whatsapp' && $reminder['phone']) {
                    $sent = sendWhatsApp($reminder['phone'], $reminder['message']);
                }
                
                if ($sent) {
                    $stmt2 = $conn->prepare("UPDATE reminders SET status = 'sent', sent_at = NOW() WHERE id = ?");
                    $stmt2->bind_param("i", $reminder['id']);
                    $stmt2->execute();
                    $processed++;
                }
            }
            
            echo json_encode(['success' => true, 'message' => "Processed $processed reminders"]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        break;
}
?>