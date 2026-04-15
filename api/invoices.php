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
                SELECT d.*, c.name as customer_name, c.email, c.phone 
                FROM debts d 
                JOIN customers c ON d.customer_id = c.id 
                WHERE d.user_id = ? AND d.status = ?
                ORDER BY d.created_at DESC
            ");
            $stmt->bind_param("is", $user_id, $status);
        } else {
            $stmt = $conn->prepare("
                SELECT d.*, c.name as customer_name, c.email, c.phone 
                FROM debts d 
                JOIN customers c ON d.customer_id = c.id 
                WHERE d.user_id = ? 
                ORDER BY d.created_at DESC
            ");
            $stmt->bind_param("i", $user_id);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $invoices = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $invoices]);
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? $_POST['action'] ?? '';
        
        if ($action === 'create') {
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
                logActivity($user_id, 'create_invoice', "Created invoice $invoice_number for amount $amount");
                echo json_encode(['success' => true, 'message' => 'Invoice created successfully', 'invoice_number' => $invoice_number, 'debt_id' => $debt_id]);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Failed to create invoice: ' . $e->getMessage()]);
            }
        } elseif ($action === 'send') {
            $debt_id = $data['debt_id'] ?? $_POST['debt_id'] ?? '';
            $method = $data['method'] ?? $_POST['method'] ?? 'email';
            
            $stmt = $conn->prepare("
                SELECT d.*, c.name, c.email, c.phone 
                FROM debts d 
                JOIN customers c ON d.customer_id = c.id 
                WHERE d.id = ? AND d.user_id = ?
            ");
            $stmt->bind_param("ii", $debt_id, $user_id);
            $stmt->execute();
            $invoice = $stmt->get_result()->fetch_assoc();
            
            if ($invoice) {
                $message = "Dear {$invoice['name']},\n\n";
                $message .= "Invoice #{$invoice['invoice_number']}\n";
                $message .= "Amount: " . formatCurrency($invoice['amount'] - $invoice['amount_paid']) . "\n";
                $message .= "Due Date: {$invoice['due_date']}\n";
                $message .= "Description: {$invoice['description']}\n\n";
                $message .= "Please make payment at your earliest convenience.\n\n";
                $message .= "Thank you,\n" . APP_NAME;
                
                $sent = false;
                if ($method === 'email' && $invoice['email']) {
                    $sent = sendEmail($invoice['email'], "Invoice #{$invoice['invoice_number']}", $message);
                } elseif ($method === 'whatsapp' && $invoice['phone']) {
                    $sent = sendWhatsApp($invoice['phone'], $message);
                }
                
                if ($sent) {
                    logActivity($user_id, 'send_invoice', "Sent invoice {$invoice['invoice_number']} via $method");
                    echo json_encode(['success' => true, 'message' => "Invoice sent via $method"]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to send invoice']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invoice not found']);
            }
        } elseif ($action === 'generate_pdf') {
            $debt_id = $data['debt_id'] ?? $_POST['debt_id'] ?? '';
            
            $stmt = $conn->prepare("
                SELECT d.*, c.name as customer_name, c.email, c.phone, c.address 
                FROM debts d 
                JOIN customers c ON d.customer_id = c.id 
                WHERE d.id = ? AND d.user_id = ?
            ");
            $stmt->bind_param("ii", $debt_id, $user_id);
            $stmt->execute();
            $invoice = $stmt->get_result()->fetch_assoc();
            
            if ($invoice) {
                // Get user business info
                $stmt = $conn->prepare("SELECT business_name, business_logo, email, phone FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $business = $stmt->get_result()->fetch_assoc();
                
                // Return invoice data for PDF generation on frontend
                echo json_encode([
                    'success' => true, 
                    'data' => [
                        'invoice' => $invoice,
                        'business' => $business
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invoice not found']);
            }
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        break;
}
?>