<?php
require_once __DIR__ . '/includes/auth.php';
requireAuth();
require_once __DIR__ . '/includes/functions.php';

$conn = getDB();
$user_id = $_SESSION['user_id'];

// Get statistics
$result = $conn->query("SELECT COUNT(*) as count FROM customers WHERE user_id = $user_id");
$total_customers = $result ? $result->fetch_assoc()['count'] : 0;

$result = $conn->query("SELECT COUNT(*) as count FROM debts WHERE user_id = $user_id AND status != 'paid'");
$unpaid_debts = $result ? $result->fetch_assoc()['count'] : 0;

$result = $conn->query("SELECT COALESCE(SUM(amount - amount_paid), 0) as total FROM debts WHERE user_id = $user_id AND status != 'paid'");
$total_owed = $result ? $result->fetch_assoc()['total'] : 0;

$result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE user_id = $user_id AND MONTH(payment_date) = MONTH(CURDATE())");
$paid_this_month = $result ? $result->fetch_assoc()['total'] : 0;

// Get chart data
$result = $conn->query("
    SELECT 
        COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) as paid,
        COALESCE(SUM(CASE WHEN status != 'paid' THEN (amount - amount_paid) ELSE 0 END), 0) as unpaid
    FROM debts WHERE user_id = $user_id
");
$chart_data = $result ? $result->fetch_assoc() : ['paid' => 0, 'unpaid' => 0];

// Get recent activity
$activities = getRecentActivity($user_id, 5);

// Get recent debts
$recent_debts = $conn->query("
    SELECT d.*, c.name as customer_name 
    FROM debts d 
    JOIN customers c ON d.customer_id = c.id 
    WHERE d.user_id = $user_id 
    ORDER BY d.created_at DESC 
    LIMIT 5
");

$page_title = 'Dashboard';
include __DIR__ . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Welcome Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Dashboard</h1>
        <p class="text-gray-600 mt-1">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</p>
    </div>
    
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500 hover:shadow-lg transition cursor-pointer" onclick="window.location.href='customers.php'">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-gray-500 text-sm">Total Customers</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo number_format($total_customers); ?></p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-users text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-yellow-500 hover:shadow-lg transition cursor-pointer" onclick="window.location.href='invoices.php'">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-gray-500 text-sm">Unpaid Invoices</p>
                    <p class="text-2xl font-bold text-yellow-600"><?php echo number_format($unpaid_debts); ?></p>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-file-invoice text-yellow-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-red-500 hover:shadow-lg transition">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-gray-500 text-sm">Total Amount Owed</p>
                    <p class="text-2xl font-bold text-red-600"><?php echo formatCurrency($total_owed); ?></p>
                </div>
                <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-dollar-sign text-red-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500 hover:shadow-lg transition">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-gray-500 text-sm">Paid This Month</p>
                    <p class="text-2xl font-bold text-green-600"><?php echo formatCurrency($paid_this_month); ?></p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts and Activity -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Payment Status Chart -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Payment Status Distribution</h3>
            <canvas id="paymentChart" class="w-full" style="max-height: 280px;"></canvas>
        </div>
        
        <!-- Recent Activity -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Activity</h3>
            <div class="space-y-4 max-h-80 overflow-y-auto">
                <?php if (empty($activities)): ?>
                    <p class="text-gray-500 text-center py-8">No recent activity</p>
                <?php else: ?>
                    <?php foreach ($activities as $activity): ?>
                        <div class="flex items-start space-x-3 pb-3 border-b border-gray-100">
                            <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-info-circle text-gray-500 text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm text-gray-800"><?php echo htmlspecialchars($activity['description']); ?></p>
                                <p class="text-xs text-gray-500 mt-1"><?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recent Transactions -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b bg-gray-50 flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-800">Recent Transactions</h3>
            <a href="invoices.php" class="text-blue-600 hover:text-blue-800 text-sm">View All →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Due Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if ($recent_debts && $recent_debts->num_rows > 0): ?>
                        <?php while ($debt = $recent_debts->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm text-gray-800"><?php echo htmlspecialchars($debt['customer_name']); ?></td>
                                <td class="px-6 py-4 text-sm font-mono"><?php echo htmlspecialchars($debt['invoice_number']); ?></td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-800"><?php echo formatCurrency($debt['amount'] - $debt['amount_paid']); ?></td>
                                <td class="px-6 py-4"><?php echo getStatusBadge($debt['status']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?php echo $debt['due_date'] ? date('M d, Y', strtotime($debt['due_date'])) : 'N/A'; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-inbox text-4xl mb-2"></i>
                                <p>No transactions found</p>
                                <a href="customers.php" class="text-blue-600 hover:text-blue-800 mt-2 inline-block">Add your first customer</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Payment Status Chart
const ctx = document.getElementById('paymentChart').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Paid', 'Unpaid'],
        datasets: [{
            data: [<?php echo $chart_data['paid']; ?>, <?php echo $chart_data['unpaid']; ?>],
            backgroundColor: ['#10b981', '#f59e0b'],
            borderWidth: 0,
            hoverOffset: 10
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { font: { size: 12 }, usePointStyle: true }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.raw || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                        return `${label}: ${formatCurrency(value)} (${percentage}%)`;
                    }
                }
            }
        },
        cutout: '60%'
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>