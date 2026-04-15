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
        // Get all customers or single customer
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $stmt = $conn->prepare("SELECT * FROM customers WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $customer = $result->fetch_assoc();
            
            // Get customer's debts
            $stmt = $conn->prepare("SELECT * FROM debts WHERE customer_id = ? AND user_id = ? ORDER BY created_at DESC");
            $stmt->bind_param("ii", $id, $user_id);
            $stmt->execute();
            $debts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            $customer['debts'] = $debts;
            echo json_encode(['success' => true, 'data' => $customer]);
        } else {
            $search = $_GET['search'] ?? '';
            if ($search) {
                $stmt = $conn->prepare("SELECT * FROM customers WHERE user_id = ? AND (name LIKE ? OR email LIKE ? OR phone LIKE ?) ORDER BY name");
                $search_param = "%$search%";
                $stmt->bind_param("isss", $user_id, $search_param, $search_param, $search_param);
            } else {
                $stmt = $conn->prepare("SELECT * FROM customers WHERE user_id = ? ORDER BY name");
                $stmt->bind_param("i", $user_id);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $customers = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'data' => $customers]);
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? $_POST['action'] ?? '';
        
        if ($action === 'add') {
            $name = $data['name'] ?? $_POST['name'] ?? '';
            $email = $data['email'] ?? $_POST['email'] ?? '';
            $phone = $data['phone'] ?? $_POST['phone'] ?? '';
            $address = $data['address'] ?? $_POST['address'] ?? '';
            $notes = $data['notes'] ?? $_POST['notes'] ?? '';
            
            $stmt = $conn->prepare("INSERT INTO customers (user_id, name, email, phone, address, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssss", $user_id, $name, $email, $phone, $address, $notes);
            
            if ($stmt->execute()) {
                $customer_id = $conn->insert_id;
                logActivity($user_id, 'add_customer', "Added customer: $name");
                echo json_encode(['success' => true, 'message' => 'Customer added successfully', 'customer_id' => $customer_id]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add customer']);
            }
        } elseif ($action === 'update') {
            $id = $data['id'] ?? $_POST['id'] ?? '';
            $name = $data['name'] ?? $_POST['name'] ?? '';
            $email = $data['email'] ?? $_POST['email'] ?? '';
            $phone = $data['phone'] ?? $_POST['phone'] ?? '';
            $address = $data['address'] ?? $_POST['address'] ?? '';
            $notes = $data['notes'] ?? $_POST['notes'] ?? '';
            
            $stmt = $conn->prepare("UPDATE customers SET name = ?, email = ?, phone = ?, address = ?, notes = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("sssssii", $name, $email, $phone, $address, $notes, $id, $user_id);
            
            if ($stmt->execute()) {
                logActivity($user_id, 'update_customer', "Updated customer ID: $id");
                echo json_encode(['success' => true, 'message' => 'Customer updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update customer']);
            }
        } elseif ($action === 'delete') {
            $id = $data['id'] ?? $_POST['id'] ?? '';
            
            $stmt = $conn->prepare("DELETE FROM customers WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $id, $user_id);
            
            if ($stmt->execute()) {
                logActivity($user_id, 'delete_customer', "Deleted customer ID: $id");
                echo json_encode(['success' => true, 'message' => 'Customer deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete customer']);
            }
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        break;
}
?>