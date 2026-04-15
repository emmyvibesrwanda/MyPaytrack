<?php
require_once __DIR__ . '/includes/auth.php';
requireAuth();
require_once __DIR__ . '/includes/functions.php';

$conn = getDB();
$user_id = $_SESSION['user_id'];

// Get summary statistics
$result = $conn->query("
    SELECT 
        COUNT(DISTINCT c.id) as total_customers,
        COUNT(d.id) as total_invoices,
        COALESCE(SUM(d.amount), 0) as total_amount,
        COALESCE(SUM(d.amount_paid), 0) as total_paid,
        COALESCE(SUM(d.amount - d.amount_paid), 0) as total_outstanding
    FROM customers c
    LEFT JOIN debts d ON c.id = d.customer_id AND d.user_id = $user_id
    WHERE c.user_id = $user_id
");
$summary = $result->fetch_assoc();

// Get monthly revenue
$monthly = $conn->query("
    SELECT 
        DATE_FORMAT(payment_date, '%M %Y') as month,
        COALESCE(SUM(amount), 0) as total
    FROM payments 
    WHERE user_id = $user_id 
    GROUP BY MONTH(payment_date), YEAR(payment_date)
    ORDER BY payment_date DESC
    LIMIT 6
");

$page_title = 'Reports';
include __DIR__ . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Reports & Analytics</h1>
    
    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
        <div class="bg-white rounded-xl shadow-md p-4">
            <p class="text-gray-500 text-sm">Total Customers</p>
            <p class="text-2xl font-bold"><?php echo number_format($summary['total_customers']); ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-md p-4">
            <p class="text-gray-500 text-sm">Total Invoices</p>
            <p class="text-2xl font-bold"><?php echo number_format($summary['total_invoices']); ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-md p-4">
            <p class="text-gray-500 text-sm">Total Amount</p>
            <p class="text-2xl font-bold text-blue-600"><?php echo formatCurrency($summary['total_amount']); ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-md p-4">
            <p class="text-gray-500 text-sm">Total Paid</p>
            <p class="text-2xl font-bold text-green-600"><?php echo formatCurrency($summary['total_paid']); ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-md p-4">
            <p class="text-gray-500 text-sm">Outstanding</p>
            <p class="text-2xl font-bold text-red-600"><?php echo formatCurrency($summary['total_outstanding']); ?></p>
        </div>
    </div>
    
    <!-- Monthly Revenue Chart -->
    <div class="bg-white rounded-xl shadow-md p-6 mb-8">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Monthly Revenue</h3>
        <canvas id="revenueChart" height="300"></canvas>
    </div>
    
    <!-- Top Customers -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b bg-gray-50">
            <h3 class="text-lg font-semibold text-gray-800">Top Customers by Revenue</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Customer</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Total Invoices</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Total Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Paid</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Outstanding</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $top_customers = $conn->query("
                        SELECT 
                            c.name,
                            COUNT(d.id) as invoice_count,
                            COALESCE(SUM(d.amount), 0) as total_amount,
                            COALESCE(SUM(d.amount_paid), 0) as total_paid
                        FROM customers c
                        LEFT JOIN debts d ON c.id = d.customer_id AND d.user_id = $user_id
                        WHERE c.user_id = $user_id
                        GROUP BY c.id
                        ORDER BY total_amount DESC
                        LIMIT 10
                    ");
                    while ($customer = $top_customers->fetch_assoc()):
                        $outstanding = $customer['total_amount'] - $customer['total_paid'];
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($customer['name']); ?></td>
                        <td class="px-6 py-4"><?php echo $customer['invoice_count']; ?></td>
                        <td class="px-6 py-4"><?php echo formatCurrency($customer['total_amount']); ?></td>
                        <td class="px-6 py-4 text-green-600"><?php echo formatCurrency($customer['total_paid']); ?></td>
                        <td class="px-6 py-4 text-red-600"><?php echo formatCurrency($outstanding); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const months = [];
const revenues = [];

<?php
$months_arr = [];
$revenues_arr = [];
while ($row = $monthly->fetch_assoc()) {
    $months_arr[] = $row['month'];
    $revenues_arr[] = $row['total'];
}
?>

new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_reverse($months_arr)); ?>,
        datasets: [{
            label: 'Revenue (RWF)',
            data: <?php echo json_encode(array_reverse($revenues_arr)); ?>,
            backgroundColor: '#3b82f6',
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString() + ' RWF';
                    }
                }
            }
        }
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>