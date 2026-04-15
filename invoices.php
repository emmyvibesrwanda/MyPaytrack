<?php
require_once __DIR__ . '/includes/auth.php';
requireAuth();
require_once __DIR__ . '/includes/functions.php';

$conn = getDB();
$user_id = $_SESSION['user_id'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    
    // Delete Invoice
    if ($action === 'delete_invoice') {
        $invoice_id = (int)$_POST['invoice_id'];
        
        // Get invoice details before deleting
        $invoice = $conn->query("SELECT customer_id, amount, amount_paid FROM debts WHERE id = $invoice_id AND user_id = $user_id")->fetch_assoc();
        
        if ($invoice) {
            // Update customer total debt
            $remaining = $invoice['amount'] - $invoice['amount_paid'];
            $conn->query("UPDATE customers SET total_debt = total_debt - $remaining WHERE id = {$invoice['customer_id']}");
            
            // Delete invoice
            $conn->query("DELETE FROM debts WHERE id = $invoice_id AND user_id = $user_id");
            
            if ($conn->affected_rows > 0) {
                logActivity($user_id, 'delete_invoice', "Deleted invoice ID: $invoice_id");
                echo json_encode(['success' => true, 'message' => 'Invoice deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete invoice']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invoice not found']);
        }
        exit();
    }
    
    // Mark as Paid
    if ($action === 'mark_paid') {
        $debt_id = (int)$_POST['debt_id'];
        $amount = (float)$_POST['amount'];
        
        $debt = $conn->query("SELECT customer_id, amount, amount_paid FROM debts WHERE id = $debt_id AND user_id = $user_id")->fetch_assoc();
        
        if ($debt) {
            $new_paid = $debt['amount_paid'] + $amount;
            $status = $new_paid >= $debt['amount'] ? 'paid' : 'partial';
            
            $conn->query("UPDATE debts SET amount_paid = $new_paid, status = '$status' WHERE id = $debt_id");
            $conn->query("INSERT INTO payments (debt_id, user_id, amount, payment_date) VALUES ($debt_id, $user_id, $amount, CURDATE())");
            $conn->query("UPDATE customers SET total_debt = total_debt - $amount WHERE id = {$debt['customer_id']}");
            
            logActivity($user_id, 'mark_paid', "Recorded payment of $amount for invoice ID: $debt_id");
            echo json_encode(['success' => true, 'message' => 'Payment recorded successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invoice not found']);
        }
        exit();
    }
}

// Get filter status
$status_filter = $_GET['status'] ?? '';

if ($status_filter) {
    $invoices = $conn->query("
        SELECT d.*, c.name as customer_name, c.email, c.phone 
        FROM debts d 
        JOIN customers c ON d.customer_id = c.id 
        WHERE d.user_id = $user_id AND d.status = '$status_filter'
        ORDER BY d.created_at DESC
    ");
} else {
    $invoices = $conn->query("
        SELECT d.*, c.name as customer_name, c.email, c.phone 
        FROM debts d 
        JOIN customers c ON d.customer_id = c.id 
        WHERE d.user_id = $user_id 
        ORDER BY d.created_at DESC
    ");
}

$page_title = 'Invoices';
include __DIR__ . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Invoices</h1>
            <p class="text-gray-600 mt-1">View and manage all your invoices</p>
        </div>
        <a href="customers.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition flex items-center gap-2">
            <i class="fas fa-plus"></i> Create New Invoice
        </a>
    </div>
    
    <!-- Filter Tabs -->
    <div class="flex flex-wrap gap-2 mb-6">
        <a href="?status=" class="px-4 py-2 rounded-lg text-sm font-medium transition <?php echo !$status_filter ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
            All Invoices
        </a>
        <a href="?status=unpaid" class="px-4 py-2 rounded-lg text-sm font-medium transition <?php echo $status_filter === 'unpaid' ? 'bg-yellow-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
            <i class="fas fa-clock mr-1"></i> Unpaid
        </a>
        <a href="?status=paid" class="px-4 py-2 rounded-lg text-sm font-medium transition <?php echo $status_filter === 'paid' ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
            <i class="fas fa-check-circle mr-1"></i> Paid
        </a>
        <a href="?status=partial" class="px-4 py-2 rounded-lg text-sm font-medium transition <?php echo $status_filter === 'partial' ? 'bg-purple-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
            <i class="fas fa-chart-line mr-1"></i> Partial
        </a>
        <a href="?status=overdue" class="px-4 py-2 rounded-lg text-sm font-medium transition <?php echo $status_filter === 'overdue' ? 'bg-red-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
            <i class="fas fa-exclamation-triangle mr-1"></i> Overdue
        </a>
    </div>
    
    <!-- Invoices Table -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Paid</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Balance</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Due Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if ($invoices->num_rows == 0): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-file-invoice text-4xl mb-2"></i>
                                <p>No invoices found</p>
                                <a href="customers.php" class="mt-2 text-blue-600 hover:text-blue-800 inline-block">Create your first invoice</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php while ($invoice = $invoices->fetch_assoc()): 
                            $balance = $invoice['amount'] - $invoice['amount_paid'];
                            $is_overdue = $invoice['due_date'] && strtotime($invoice['due_date']) < time() && $invoice['status'] != 'paid';
                        ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <span class="font-mono text-sm font-semibold"><?php echo htmlspecialchars($invoice['invoice_number']); ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($invoice['customer_name']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($invoice['email'] ?: $invoice['phone']); ?></p>
                                </td>
                                <td class="px-6 py-4 font-semibold"><?php echo formatCurrency($invoice['amount']); ?></td>
                                <td class="px-6 py-4 text-green-600"><?php echo formatCurrency($invoice['amount_paid']); ?></td>
                                <td class="px-6 py-4">
                                    <span class="font-semibold <?php echo $balance > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                                        <?php echo formatCurrency($balance); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-sm <?php echo $is_overdue ? 'text-red-600 font-semibold' : 'text-gray-600'; ?>">
                                        <?php echo $invoice['due_date'] ? date('M d, Y', strtotime($invoice['due_date'])) : 'N/A'; ?>
                                        <?php if ($is_overdue): ?>
                                            <i class="fas fa-exclamation-triangle ml-1 text-red-500"></i>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4"><?php echo getStatusBadge($invoice['status']); ?></td>
                                <td class="px-6 py-4">
                                    <div class="flex space-x-2">
                                        <a href="invoice_detail.php?id=<?php echo $invoice['id']; ?>" class="text-blue-600 hover:text-blue-800" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($balance > 0): ?>
                                            <button onclick="markInvoiceAsPaid(<?php echo $invoice['id']; ?>, <?php echo $balance; ?>)" 
                                                    class="text-purple-600 hover:text-purple-800" title="Mark as Paid">
                                                <i class="fas fa-check-double"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button onclick="deleteInvoice(<?php echo $invoice['id']; ?>, '<?php echo htmlspecialchars($invoice['invoice_number']); ?>')" 
                                                class="text-red-600 hover:text-red-800" title="Delete Invoice">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Mark as Paid Modal -->
<div id="markInvoicePaidModal" class="modal">
    <div class="modal-content max-w-md">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold">Mark Invoice as Paid</h3>
                <button onclick="closeModal('markInvoicePaidModal')" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <form id="markInvoicePaidForm">
                <input type="hidden" name="debt_id" id="invoiceDebtId">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Outstanding Balance</label>
                    <p id="outstandingBalance" class="text-2xl font-bold text-red-600"></p>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Payment Amount (RWF)</label>
                    <input type="number" name="amount" id="paymentAmountValue" step="1" class="w-full px-3 py-2 border rounded-lg bg-gray-100" readonly>
                </div>
                <div class="flex space-x-3">
                    <button type="submit" class="flex-1 bg-purple-600 text-white py-2 rounded-lg hover:bg-purple-700">Confirm Payment</button>
                    <button type="button" onclick="closeModal('markInvoicePaidModal')" class="flex-1 bg-gray-300 text-gray-700 py-2 rounded-lg hover:bg-gray-400">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let currentInvoiceId = null;

function markInvoiceAsPaid(invoiceId, balance) {
    currentInvoiceId = invoiceId;
    document.getElementById('invoiceDebtId').value = invoiceId;
    document.getElementById('outstandingBalance').textContent = formatCurrency(balance);
    document.getElementById('paymentAmountValue').value = balance;
    openModal('markInvoicePaidModal');
}

function deleteInvoice(invoiceId, invoiceNumber) {
    confirmAction(`Are you sure you want to delete invoice <strong>${invoiceNumber}</strong>? This action cannot be undone.`, () => {
        fetch('', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=delete_invoice&invoice_id=' + invoiceId
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showToast(result.message, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(result.message, 'error');
            }
        });
    });
}

document.getElementById('markInvoicePaidForm').addEventListener('submit', function(e) {
    e.preventDefault();
    let debtId = document.getElementById('invoiceDebtId').value;
    let amount = document.getElementById('paymentAmountValue').value;
    
    confirmAction(`Confirm payment of ${formatCurrency(amount)}?`, () => {
        fetch('', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=mark_paid&debt_id=' + debtId + '&amount=' + amount
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showToast(result.message, 'success');
                closeModal('markInvoicePaidModal');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(result.message, 'error');
            }
        });
    });
});

function openModal(modalId) {
    document.getElementById(modalId).classList.add('show');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>