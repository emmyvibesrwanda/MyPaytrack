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
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $stmt = $conn->prepare("
                SELECT d.*, c.name as customer_name, c.email, c.phone 
                FROM debts d 
                JOIN customers c ON d.customer_id = c.id 
                WHERE d.id = ? AND d.user_id = ?
            ");
            $stmt->bind_param("ii", $id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $debt = $result->fetch_assoc();
            
            // Get payments for this debt
            $stmt = $conn->prepare("SELECT * FROM payments WHERE debt_id = ? ORDER BY payment_date DESC");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $debt['payments'] = $payments;
            
            echo json_encode(['success' => true, 'data' => $debt]);
        } else {
            $customer_id = $_GET['customer_id'] ?? '';
            if ($customer_id) {
                $stmt = $conn->prepare("SELECT * FROM debts WHERE customer_id = ? AND user_id = ? ORDER BY created_at DESC");
                $stmt->bind_param("ii", $customer_id, $user_id);
            } else {
                $stmt = $conn->prepare("
                    SELECT d.*, c.name as customer_name 
                    FROM debts d 
                    JOIN customers c ON d.customer_id = c.id 
                    WHERE d.user_id = ? 
                    ORDER BY d.created_at DESC
                ");
                $stmt->bind_param("i", $user_id);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $debts = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'data' => $debts]);
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? $_POST['action'] ?? '';
        
        if ($action === 'add') {
            $customer_id = $data['customer_id'] ?? $_POST['customer_id'] ?? '';
            $amount = $data['amount'] ?? $_POST['amount'] ?? '';
            $description = $data['description'] ?? $_POST['description'] ?? '';
            $due_date = $data['due_date'] ?? $_POST['due_date'] ?? null;
            $invoice_number = generateInvoiceNumber();
            
            $conn->begin_transaction();
            
            try {
                $stmt = $conn->prepare("INSERT INTO debts (user_id, customer_id, amount, description, due_date, invoice_number) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iidsss", $user_id, $customer_id, $amount, $description, $due_date, $invoice_number);
                $stmt->execute();
                $debt_id = $conn->insert_id;
                
                $stmt2 = $conn->prepare("UPDATE customers SET total_debt = total_debt + ? WHERE id = ?");
                $stmt2->bind_param("di", $amount, $customer_id);
                $stmt2->execute();
                
                $conn->commit();
                logActivity($user_id, 'add_debt', "Added debt of $amount for customer ID: $customer_id");
                echo json_encode(['success' => true, 'message' => 'Debt added successfully', 'debt_id' => $debt_id]);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Failed to add debt: ' . $e->getMessage()]);
            }
        } elseif ($action === 'update') {
            $id = $data['id'] ?? $_POST['id'] ?? '';
            $amount = $data['amount'] ?? $_POST['amount'] ?? '';
            $description = $data['description'] ?? $_POST['description'] ?? '';
            $due_date = $data['due_date'] ?? $_POST['due_date'] ?? null;
            $status = $data['status'] ?? $_POST['status'] ?? '';
            
            $stmt = $conn->prepare("UPDATE debts SET amount = ?, description = ?, due_date = ?, status = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("dsssii", $amount, $description, $due_date, $status, $id, $user_id);
            
            if ($stmt->execute()) {
                logActivity($user_id, 'update_debt', "Updated debt ID: $id");
                echo json_encode(['success' => true, 'message' => 'Debt updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update debt']);
            }
        } elseif ($action === 'delete') {
            $id = $data['id'] ?? $_POST['id'] ?? '';
            
            // Get customer_id first
            $stmt = $conn->prepare("SELECT customer_id, amount FROM debts WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $id, $user_id);
            $stmt->execute();
            $debt = $stmt->get_result()->fetch_assoc();
            
            if ($debt) {
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare("DELETE FROM debts WHERE id = ? AND user_id = ?");
                    $stmt->bind_param("ii", $id, $user_id);
                    $stmt->execute();
                    
                    $stmt2 = $conn->prepare("UPDATE customers SET total_debt = total_debt - ? WHERE id = ?");
                    $stmt2->bind_param("di", $debt['amount'], $debt['customer_id']);
                    $stmt2->execute();
                    
                    $conn->commit();
                    logActivity($user_id, 'delete_debt', "Deleted debt ID: $id");
                    echo json_encode(['success' => true, 'message' => 'Debt deleted successfully']);
                } catch (Exception $e) {
                    $conn->rollback();
                    echo json_encode(['success' => false, 'message' => 'Failed to delete debt']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Debt not found']);
            }
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        break;
}
?>