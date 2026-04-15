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
        $debt_id = $_GET['debt_id'] ?? '';
        if ($debt_id) {
            $stmt = $conn->prepare("SELECT * FROM payments WHERE debt_id = ? ORDER BY payment_date DESC");
            $stmt->bind_param("i", $debt_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $payments = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'data' => $payments]);
        } else {
            $limit = $_GET['limit'] ?? 50;
            $stmt = $conn->prepare("
                SELECT p.*, d.invoice_number, c.name as customer_name 
                FROM payments p 
                JOIN debts d ON p.debt_id = d.id 
                JOIN customers c ON d.customer_id = c.id 
                WHERE p.user_id = ? 
                ORDER BY p.payment_date DESC 
                LIMIT ?
            ");
            $stmt->bind_param("ii", $user_id, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            $payments = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'data' => $payments]);
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? $_POST['action'] ?? '';
        
        if ($action === 'add') {
            $debt_id = $data['debt_id'] ?? $_POST['debt_id'] ?? '';
            $amount = $data['amount'] ?? $_POST['amount'] ?? '';
            $payment_method = $data['payment_method'] ?? $_POST['payment_method'] ?? 'cash';
            $reference_number = $data['reference_number'] ?? $_POST['reference_number'] ?? '';
            $notes = $data['notes'] ?? $_POST['notes'] ?? '';
            $payment_date = $data['payment_date'] ?? $_POST['payment_date'] ?? date('Y-m-d');
            
            $conn->begin_transaction();
            
            try {
                // Get debt info
                $stmt = $conn->prepare("SELECT customer_id, amount, amount_paid FROM debts WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $debt_id, $user_id);
                $stmt->execute();
                $debt = $stmt->get_result()->fetch_assoc();
                
                if (!$debt) {
                    throw new Exception('Debt not found');
                }
                
                $new_paid = $debt['amount_paid'] + $amount;
                $status = $new_paid >= $debt['amount'] ? 'paid' : 'partial';
                
                // Update debt
                $stmt = $conn->prepare("UPDATE debts SET amount_paid = ?, status = ? WHERE id = ?");
                $stmt->bind_param("dsi", $new_paid, $status, $debt_id);
                $stmt->execute();
                
                // Record payment
                $stmt = $conn->prepare("INSERT INTO payments (debt_id, user_id, amount, payment_method, reference_number, notes, payment_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iidssss", $debt_id, $user_id, $amount, $payment_method, $reference_number, $notes, $payment_date);
                $stmt->execute();
                $payment_id = $conn->insert_id;
                
                // Update customer total debt
                $stmt = $conn->prepare("UPDATE customers SET total_debt = total_debt - ? WHERE id = ?");
                $stmt->bind_param("di", $amount, $debt['customer_id']);
                $stmt->execute();
                
                $conn->commit();
                logActivity($user_id, 'add_payment', "Added payment of $amount for debt ID: $debt_id");
                echo json_encode(['success' => true, 'message' => 'Payment recorded successfully', 'payment_id' => $payment_id]);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Failed to record payment: ' . $e->getMessage()]);
            }
        } elseif ($action === 'delete') {
            $id = $data['id'] ?? $_POST['id'] ?? '';
            
            $conn->begin_transaction();
            
            try {
                // Get payment info
                $stmt = $conn->prepare("SELECT debt_id, amount FROM payments WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $id, $user_id);
                $stmt->execute();
                $payment = $stmt->get_result()->fetch_assoc();
                
                if (!$payment) {
                    throw new Exception('Payment not found');
                }
                
                // Update debt
                $stmt = $conn->prepare("UPDATE debts SET amount_paid = amount_paid - ?, status = 'unpaid' WHERE id = ?");
                $stmt->bind_param("di", $payment['amount'], $payment['debt_id']);
                $stmt->execute();
                
                // Delete payment
                $stmt = $conn->prepare("DELETE FROM payments WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $id, $user_id);
                $stmt->execute();
                
                $conn->commit();
                logActivity($user_id, 'delete_payment', "Deleted payment ID: $id");
                echo json_encode(['success' => true, 'message' => 'Payment deleted successfully']);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Failed to delete payment']);
            }
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        break;
}
?>