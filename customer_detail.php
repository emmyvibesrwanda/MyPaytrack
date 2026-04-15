<?php
require_once 'includes/auth.php';
requireAuth();
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$conn = getDB();
$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get customer details
$stmt = $conn->prepare("SELECT * FROM customers WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $customer_id, $user_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();

if (!$customer) {
    header('Location: customers.php');
    exit();
}

// Get customer debts
$stmt = $conn->prepare("
    SELECT d.*, 
           (SELECT SUM(amount) FROM payments WHERE debt_id = d.id) as total_paid
    FROM debts d 
    WHERE d.customer_id = ? AND d.user_id = ? 
    ORDER BY d.created_at DESC
");
$stmt->bind_param("ii", $customer_id, $user_id);
$stmt->execute();
$debts = $stmt->get_result();

$page_title = $customer['name'];
include 'includes/header.php';
?>

<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <a href="customers.php" class="text-blue-600 hover:text-blue-800 mb-2 inline-block">
                <i class="fas fa-arrow-left"></i> Back to Customers
            </a>
            <h1 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($customer['name']); ?></h1>
            <p class="text-gray-600">Customer Details & Transaction History</p>
        </div>
        <button onclick="openAddDebtModal(<?php echo $customer_id; ?>, '<?php echo htmlspecialchars($customer['name']); ?>')" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
            <i class="fas fa-plus"></i> Add Debt
        </button>
    </div>
</div>

<!-- Customer Info Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                <i class="fas fa-envelope text-blue-600"></i>
            </div>
            <div>
                <p class="text-sm text-gray-500">Email</p>
                <p class="font-medium"><?php echo htmlspecialchars($customer['email'] ?: 'N/A'); ?></p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                <i class="fas fa-phone text-green-600"></i>
            </div>
            <div>
                <p class="text-sm text-gray-500">Phone</p>
                <p class="font-medium"><?php echo htmlspecialchars($customer['phone'] ?: 'N/A'); ?></p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
                <i class="fas fa-dollar-sign text-yellow-600"></i>
            </div>
            <div>
                <p class="text-sm text-gray-500">Total Debt</p>
                <p class="font-bold text-xl <?php echo $customer['total_debt'] > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                    <?php echo formatCurrency($customer['total_debt']); ?>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Debts Table -->
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b">
        <h3 class="text-lg font-semibold text-gray-800">Transaction History</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice #</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paid</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remaining</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($debts->num_rows === 0): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-receipt text-4xl mb-2"></i>
                            <p>No transactions found</p>
                            <button onclick="openAddDebtModal(<?php echo $customer_id; ?>, '<?php echo htmlspecialchars($customer['name']); ?>')" class="mt-2 text-blue-600 hover:text-blue-800">
                                Add first debt
                            </button>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php while ($debt = $debts->fetch_assoc()): 
                        $remaining = $debt['amount'] - $debt['amount_paid'];
                    ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 font-mono text-sm"><?php echo htmlspecialchars($debt['invoice_number']); ?></td>
                            <td class="px-6 py-4 font-medium"><?php echo formatCurrency($debt['amount']); ?></td>
                            <td class="px-6 py-4 text-green-600"><?php echo formatCurrency($debt['amount_paid']); ?></td>
                            <td class="px-6 py-4 font-semibold <?php echo $remaining > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                                <?php echo formatCurrency($remaining); ?>
                            </td>
                            <td class="px-6 py-4 text-sm <?php echo strtotime($debt['due_date']) < time() && $debt['status'] != 'paid' ? 'text-red-600 font-semibold' : 'text-gray-600'; ?>">
                                <?php echo $debt['due_date'] ? date('M d, Y', strtotime($debt['due_date'])) : 'N/A'; ?>
                            </td>
                            <td class="px-6 py-4"><?php echo getStatusBadge($debt['status']); ?></td>
                            <td class="px-6 py-4">
                                <div class="flex space-x-2">
                                    <?php if ($remaining > 0): ?>
                                        <button onclick="recordPayment(<?php echo $debt['id']; ?>, <?php echo $remaining; ?>)" class="text-green-600 hover:text-green-800">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button onclick="viewInvoice(<?php echo $debt['id']; ?>)" class="text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-eye"></i>
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

<!-- Add Debt Modal -->
<div id="addDebtModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl w-full max-w-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-semibold">Add Debt for <span id="customerName"></span></h3>
            <button onclick="closeModal('addDebtModal')" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="addDebtForm">
            <input type="hidden" name="customer_id" id="debtCustomerId">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Amount *</label>
                <input type="number" name="amount" step="0.01" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Description</label>
                <textarea name="description" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"></textarea>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Due Date</label>
                <input type="date" name="due_date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
            </div>
            <div class="flex space-x-2">
                <button type="submit" class="flex-1 bg-green-600 text-white py-2 rounded-lg hover:bg-green-700">Add Debt</button>
                <button type="button" onclick="closeModal('addDebtModal')" class="flex-1 bg-gray-300 text-gray-700 py-2 rounded-lg hover:bg-gray-400">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddDebtModal(customerId, customerName) {
    document.getElementById('debtCustomerId').value = customerId;
    document.getElementById('customerName').textContent = customerName;
    openModal('addDebtModal');
}

document.getElementById('addDebtForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = {
        action: 'add',
        customer_id: parseInt(formData.get('customer_id')),
        amount: parseFloat(formData.get('amount')),
        description: formData.get('description'),
        due_date: formData.get('due_date')
    };
    
    const response = await fetch('/paytrack/api/debts.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    
    const result = await response.json();
    if (result.success) {
        showToast(result.message, 'success');
        location.reload();
    } else {
        showToast(result.message, 'error');
    }
});

function recordPayment(debtId, maxAmount) {
    const amount = prompt(`Enter payment amount (Max: ${formatCurrency(maxAmount)}):`, maxAmount);
    if (amount && !isNaN(amount) && parseFloat(amount) > 0) {
        fetch('/paytrack/api/payments.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'add',
                debt_id: debtId,
                amount: parseFloat(amount),
                payment_method: 'cash'
            })
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showToast(result.message, 'success');
                location.reload();
            } else {
                showToast(result.message, 'error');
            }
        });
    }
}

function viewInvoice(debtId) {
    window.location.href = `invoice_detail.php?id=${debtId}`;
}
</script>

<?php include 'includes/footer.php'; ?>