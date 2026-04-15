<?php
require_once 'includes/auth.php';
requireAuth();
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$conn = getDB();
$debt_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get invoice details
$stmt = $conn->prepare("
    SELECT d.*, c.name as customer_name, c.email, c.phone, c.address 
    FROM debts d 
    JOIN customers c ON d.customer_id = c.id 
    WHERE d.id = ? AND d.user_id = ?
");
$stmt->bind_param("ii", $debt_id, $user_id);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();

if (!$invoice) {
    die('Invoice not found');
}

// Get business info
$stmt = $conn->prepare("SELECT business_name, email, phone FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$business = $stmt->get_result()->fetch_assoc();

$balance = $invoice['amount'] - $invoice['amount_paid'];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice #<?php echo $invoice['invoice_number']; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #3b82f6;
        }
        .company-info {
            text-align: right;
            margin-bottom: 30px;
        }
        .customer-info {
            margin-bottom: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f3f4f6;
        }
        .totals {
            text-align: right;
            margin-top: 20px;
        }
        .totals table {
            width: 300px;
            margin-left: auto;
        }
        .footer {
            text-align: center;
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #666;
        }
        .status-paid {
            color: #10b981;
            font-weight: bold;
        }
        .status-unpaid {
            color: #ef4444;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="header">
            <h1>INVOICE</h1>
            <h2><?php echo htmlspecialchars($business['business_name'] ?? $_SESSION['user_name']); ?></h2>
        </div>
        
        <div class="company-info">
            <p><strong>Invoice #:</strong> <?php echo htmlspecialchars($invoice['invoice_number']); ?></p>
            <p><strong>Date:</strong> <?php echo date('F d, Y', strtotime($invoice['created_at'])); ?></p>
            <p><strong>Due Date:</strong> <?php echo $invoice['due_date'] ? date('F d, Y', strtotime($invoice['due_date'])) : 'N/A'; ?></p>
        </div>
        
        <div class="customer-info">
            <h3>Bill To:</h3>
            <p><strong><?php echo htmlspecialchars($invoice['customer_name']); ?></strong></p>
            <p><?php echo nl2br(htmlspecialchars($invoice['address'])); ?></p>
            <p>Email: <?php echo htmlspecialchars($invoice['email']); ?></p>
            <p>Phone: <?php echo htmlspecialchars($invoice['phone']); ?></p>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Amount (RWF)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo nl2br(htmlspecialchars($invoice['description'] ?: 'No description provided')); ?></td>
                    <td><?php echo number_format($invoice['amount'], 0, ',', '.'); ?></td>
                </tr>
            </tbody>
        </table>
        
        <div class="totals">
            <table>
                <tr>
                    <td><strong>Subtotal:</strong></td>
                    <td><?php echo number_format($invoice['amount'], 0, ',', '.'); ?> RWF</td>
                </tr>
                <tr>
                    <td><strong>Paid:</strong></td>
                    <td><?php echo number_format($invoice['amount_paid'], 0, ',', '.'); ?> RWF</td>
                </tr>
                <tr style="border-top: 2px solid #333;">
                    <td><strong>Balance Due:</strong></td>
                    <td><strong><?php echo number_format($balance, 0, ',', '.'); ?> RWF</strong></td>
                </tr>
            </table>
        </div>
        
        <div class="footer">
            <p>Thank you for your business!</p>
            <p>Payment is due within 30 days.</p>
            <p><?php echo htmlspecialchars($business['email']); ?> | <?php echo htmlspecialchars($business['phone']); ?></p>
        </div>
    </div>
    
    <script>
        window.print();
    </script>
</body>
</html>