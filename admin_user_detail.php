<?php
require_once 'includes/auth.php';
requireAdmin();

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$conn = getDB();

// Get user details
$result = $conn->query("
    SELECT u.*, p.name as plan_name, p.price 
    FROM users u
    LEFT JOIN subscription_plans p ON u.plan_id = p.id
    WHERE u.id = $user_id AND u.role = 'user'
");
$user = $result->fetch_assoc();

if (!$user) {
    header('Location: admin_dashboard.php');
    exit();
}

// Get user's payment requests
$result = $conn->query("
    SELECT * FROM payment_requests 
    WHERE user_id = $user_id 
    ORDER BY requested_at DESC
");
$payment_requests = array();
while ($row = $result->fetch_assoc()) {
    $payment_requests[] = $row;
}

// Get user's payment history
$result = $conn->query("
    SELECT * FROM payments_history 
    WHERE user_id = $user_id 
    ORDER BY payment_date DESC
");
$payments = array();
while ($row = $result->fetch_assoc()) {
    $payments[] = $row;
}

// Get user's invoices
$result = $conn->query("
    SELECT d.*, c.name as customer_name 
    FROM debts d
    JOIN customers c ON d.customer_id = c.id
    WHERE d.user_id = $user_id
    ORDER BY d.created_at DESC
    LIMIT 10
");
$invoices = array();
while ($row = $result->fetch_assoc()) {
    $invoices[] = $row;
}

$page_title = 'User Details - ' . $user['full_name'];
include 'includes/header.php';
?>

<div class="max-w-7xl mx-auto">
    <div class="mb-6">
        <a href="admin_dashboard.php" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
    
    <!-- User Profile Card -->
    <div class="card p-6 mb-6">
        <div class="flex justify-between items-start flex-wrap gap-4">
            <div class="flex items-center space-x-4">
                <div class="w-20 h-20 bg-gradient-to-r from-blue-500 to-purple-500 rounded-full flex items-center justify-center text-white text-3xl font-bold">
                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($user['full_name']); ?></h1>
                    <p class="text-gray-600"><?php echo htmlspecialchars($user['email']); ?></p>
                    <p class="text-gray-500 text-sm">📞 <?php echo htmlspecialchars($user['phone'] ?? 'No phone'); ?></p>
                    <p class="text-gray-500 text-sm">📅 Joined: <?php echo date('F d, Y', strtotime($user['created_at'])); ?></p>
                </div>
            </div>
            <div class="text-right">
                <div class="mb-2">
                    <span class="badge <?php echo $user['plan_id'] == 2 ? 'badge-info' : 'badge-success'; ?> text-lg">
                        <?php echo htmlspecialchars($user['plan_name']); ?> Plan
                    </span>
                </div>
                <?php if ($user['plan_id'] != 2): ?>
                    <button onclick="upgradeUserManually(<?php echo $user['id']; ?>)" class="btn btn-primary">
                        <i class="fas fa-arrow-up"></i> Upgrade to Pro
                    </button>
                <?php endif; ?>
                <button onclick="sendNotification(<?php echo $user['id']; ?>)" class="btn btn-secondary">
                    <i class="fas fa-bell"></i> Send Notification
                </button>
            </div>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="card p-4">
            <p class="text-gray-500 text-sm">Total Spent</p>
            <p class="text-2xl font-bold text-green-600">
                <?php 
                    $total = array_sum(array_column($payments, 'amount'));
                    echo formatCurrency($total);
                ?>
            </p>
        </div>
        <div class="card p-4">
            <p class="text-gray-500 text-sm">Payment Requests</p>
            <p class="text-2xl font-bold text-yellow-600"><?php echo count($payment_requests); ?></p>
        </div>
        <div class="card p-4">
            <p class="text-gray-500 text-sm">Total Invoices</p>
            <p class="text-2xl font-bold text-blue-600"><?php echo count($invoices); ?></p>
        </div>
    </div>
    
    <!-- Tabs -->
    <div class="mb-4 border-b border-gray-200">
        <ul class="flex flex-wrap -mb-px">
            <li class="mr-2">
                <button onclick="showUserTab('paymentRequests')" id="userTabPaymentRequestsBtn" class="inline-block p-3 border-b-2 rounded-t-lg text-blue-600 border-blue-600">
                    Payment Requests
                </button>
            </li>
            <li class="mr-2">
                <button onclick="showUserTab('payments')" id="userTabPaymentsBtn" class="inline-block p-3 border-b-2 rounded-t-lg text-gray-500 border-transparent">
                    Payment History
                </button>
            </li>
            <li class="mr-2">
                <button onclick="showUserTab('invoices')" id="userTabInvoicesBtn" class="inline-block p-3 border-b-2 rounded-t-lg text-gray-500 border-transparent">
                    Recent Invoices
                </button>
            </li>
        </ul>
    </div>
    
    <!-- Payment Requests Tab -->
    <div id="userPaymentRequestsTab" class="user-tab-content">
        <div class="card overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Transaction Ref</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payment_requests as $request): ?>
                        <tr>
                            <td class="px-6 py-4 text-sm"><?php echo date('M d, Y', strtotime($request['requested_at'])); ?></td>
                            <td class="px-6 py-4 font-semibold"><?php echo formatCurrency($request['amount']); ?></td>
                            <td class="px-6 py-4 font-mono text-sm"><?php echo htmlspecialchars($request['transaction_reference']); ?></td>
                            <td class="px-6 py-4">
                                <span class="badge <?php echo $request['status'] == 'pending' ? 'badge-warning' : ($request['status'] == 'approved' ? 'badge-success' : 'badge-danger'); ?>">
                                    <?php echo ucfirst($request['status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($payment_requests)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-gray-500">No payment requests</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Payment History Tab -->
    <div id="userPaymentsTab" class="user-tab-content hidden">
        <div class="card overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td class="px-6 py-4 text-sm"><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                            <td class="px-6 py-4 font-semibold text-green-600"><?php echo formatCurrency($payment['amount']); ?></td>
                            <td class="px-6 py-4 text-sm"><?php echo ucfirst($payment['payment_method'] ?? 'Bank Transfer'); ?></td>
                            <td class="px-6 py-4">
                                <span class="badge badge-success"><?php echo ucfirst($payment['status']); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-gray-500">No payment history</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Invoices Tab -->
    <div id="userInvoicesTab" class="user-tab-content hidden">
        <div class="card overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td class="px-6 py-4 font-mono text-sm"><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($invoice['customer_name']); ?></td>
                            <td class="px-6 py-4"><?php echo formatCurrency($invoice['amount']); ?></td>
                            <td class="px-6 py-4"><?php echo getStatusBadge($invoice['status']); ?></td>
                            <td class="px-6 py-4 text-sm"><?php echo date('M d, Y', strtotime($invoice['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($invoices)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500">No invoices found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function showUserTab(tabName) {
    document.querySelectorAll('.user-tab-content').forEach(tab => {
        tab.classList.add('hidden');
    });
    
    document.querySelectorAll('[id^="userTab"]').forEach(btn => {
        btn.classList.remove('text-blue-600', 'border-blue-600');
        btn.classList.add('text-gray-500', 'border-transparent');
    });
    
    document.getElementById('user' + tabName.charAt(0).toUpperCase() + tabName.slice(1) + 'Tab').classList.remove('hidden');
    document.getElementById('userTab' + tabName.charAt(0).toUpperCase() + tabName.slice(1) + 'Btn').classList.add('text-blue-600', 'border-blue-600');
}

function upgradeUserManually(userId) {
    confirmAction('Are you sure you want to manually upgrade this user to Pro plan?', () => {
        const formData = new FormData();
        formData.append('action', 'manual_upgrade');
        formData.append('user_id', userId);
        
        fetch('admin_api.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showToast(result.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(result.message, 'error');
            }
        });
    });
}

function sendNotification(userId) {
    const title = prompt('Enter notification title:');
    if (!title) return;
    const message = prompt('Enter notification message:');
    if (!message) return;
    
    const formData = new FormData();
    formData.append('action', 'send_notification');
    formData.append('user_id', userId);
    formData.append('title', title);
    formData.append('message', message);
    
    fetch('admin_api.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast(result.message, 'success');
        } else {
            showToast(result.message, 'error');
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>