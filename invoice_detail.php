<?php
require_once __DIR__ . '/includes/auth.php';
requireAuth();
require_once __DIR__ . '/includes/functions.php';

$conn = getDB();
$user_id = $_SESSION['user_id'];
$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$invoice = $conn->query("
    SELECT d.*, c.name as customer_name, c.email, c.phone, c.address 
    FROM debts d 
    JOIN customers c ON d.customer_id = c.id 
    WHERE d.id = $invoice_id AND d.user_id = $user_id
")->fetch_assoc();

if (!$invoice) {
    header('Location: invoices.php');
    exit();
}

$payments = $conn->query("SELECT * FROM payments WHERE debt_id = $invoice_id ORDER BY payment_date DESC");
$balance = $invoice['amount'] - $invoice['amount_paid'];

$page_title = 'Invoice #' . $invoice['invoice_number'];
include __DIR__ . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-8 max-w-4xl">
    <!-- Actions -->
    <div class="flex justify-between items-center mb-6">
        <a href="invoices.php" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left"></i> Back to Invoices
        </a>
        <div class="flex space-x-2">
            <button onclick="window.print()" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                <i class="fas fa-print"></i> Print
            </button>
            <?php if ($balance > 0): ?>
                <button onclick="markAsPaid(<?php echo $invoice['id']; ?>, <?php echo $balance; ?>)" 
                        class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700">
                    <i class="fas fa-check-double"></i> Mark as Paid
                </button>
            <?php endif; ?>
            <button onclick="deleteInvoice(<?php echo $invoice['id']; ?>, '<?php echo htmlspecialchars($invoice['invoice_number']); ?>')" 
                    class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">
                <i class="fas fa-trash"></i> Delete Invoice
            </button>
        </div>
    </div>
    
    <!-- Invoice Card -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <!-- Header -->
        <div class="bg-gray-50 px-8 py-6 border-b">
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">INVOICE</h1>
                    <p class="text-gray-600">#<?php echo htmlspecialchars($invoice['invoice_number']); ?></p>
                </div>
                <div class="text-right">
                    <h2 class="text-xl font-semibold">PayTrack</h2>
                    <p class="text-gray-600"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Info -->
        <div class="px-8 py-6 border-b">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500">BILL TO</p>
                    <p class="font-semibold"><?php echo htmlspecialchars($invoice['customer_name']); ?></p>
                    <p class="text-sm"><?php echo nl2br(htmlspecialchars($invoice['address'])); ?></p>
                    <p class="text-sm"><?php echo htmlspecialchars($invoice['email']); ?></p>
                    <p class="text-sm"><?php echo htmlspecialchars($invoice['phone']); ?></p>
                </div>
                <div class="text-right">
                    <p><span class="text-gray-500">Invoice Date:</span> <?php echo date('F d, Y', strtotime($invoice['created_at'])); ?></p>
                    <p><span class="text-gray-500">Due Date:</span> <?php echo $invoice['due_date'] ? date('F d, Y', strtotime($invoice['due_date'])) : 'N/A'; ?></p>
                    <p><span class="text-gray-500">Status:</span> <?php echo getStatusBadge($invoice['status']); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Items -->
        <div class="px-8 py-6">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-semibold">Description</th>
                        <th class="px-4 py-3 text-right text-sm font-semibold">Amount (RWF)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b">
                        <td class="px-4 py-3"><?php echo nl2br(htmlspecialchars($invoice['description'] ?: 'No description')); ?></td>
                        <td class="px-4 py-3 text-right font-semibold"><?php echo number_format($invoice['amount'], 0, ',', '.'); ?></td>
                    </tr>
                </tbody>
                <tfoot class="border-t">
                    <tr>
                        <td class="px-4 py-3 text-right font-bold">Subtotal:</td>
                        <td class="px-4 py-3 text-right"><?php echo number_format($invoice['amount'], 0, ',', '.'); ?> RWF</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-right font-bold">Paid:</td>
                        <td class="px-4 py-3 text-right text-green-600"><?php echo number_format($invoice['amount_paid'], 0, ',', '.'); ?> RWF</td>
                    </tr>
                    <tr class="bg-gray-50">
                        <td class="px-4 py-3 text-right font-bold text-lg">Balance Due:</td>
                        <td class="px-4 py-3 text-right font-bold text-xl <?php echo $balance > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                            <?php echo number_format($balance, 0, ',', '.'); ?> RWF
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <!-- Payment History -->
        <?php if ($payments->num_rows > 0): ?>
            <div class="px-8 py-6 border-t bg-gray-50">
                <h3 class="font-semibold mb-3">Payment History</h3>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-gray-500">
                            <th class="text-left pb-2">Date</th>
                            <th class="text-left pb-2">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($payment = $payments->fetch_assoc()): ?>
                            <tr>
                                <td class="py-1"><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                <td class="py-1 text-green-600"><?php echo number_format($payment['amount'], 0, ',', '.'); ?> RWF</td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <div class="px-8 py-6 border-t text-sm text-gray-500 text-center">
            <p>Thank you for your business!</p>
        </div>
    </div>
</div>

<!-- Mark as Paid Modal -->
<div id="markPaidModal" class="modal">
    <div class="modal-content max-w-md">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold">Mark Invoice as Paid</h3>
                <button onclick="closeModal('markPaidModal')" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <form id="markPaidForm">
                <input type="hidden" name="debt_id" id="debtId">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Outstanding Balance</label>
                    <p id="outstandingAmount" class="text-2xl font-bold text-red-600"></p>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Payment Amount</label>
                    <input type="number" name="amount" id="amountValue" class="w-full px-3 py-2 border rounded-lg bg-gray-100" readonly>
                </div>
                <button type="submit" class="w-full bg-purple-600 text-white py-2 rounded-lg hover:bg-purple-700">Confirm Payment</button>
            </form>
        </div>
    </div>
</div>

<script>
function markAsPaid(invoiceId, balance) {
    document.getElementById('debtId').value = invoiceId;
    document.getElementById('outstandingAmount').textContent = formatCurrency(balance);
    document.getElementById('amountValue').value = balance;
    openModal('markPaidModal');
}

document.getElementById('markPaidForm').addEventListener('submit', function(e) {
    e.preventDefault();
    let debtId = document.getElementById('debtId').value;
    let amount = document.getElementById('amountValue').value;
    
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
                closeModal('markPaidModal');
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
                setTimeout(() => window.location.href = 'invoices.php', 1000);
            } else {
                showToast(result.message, 'error');
            }
        });
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>