<?php
require_once '../includes/auth.php';
requireAuth();
require_once '../includes/functions.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$conn = getDB();

// Get dashboard statistics
$stats = [];

// Total customers
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM customers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stats['total_customers'] = $result->fetch_assoc()['count'] ?? 0;
$stmt->close();

// Unpaid debts count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM debts WHERE user_id = ? AND status != 'paid'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stats['unpaid_debts'] = $result->fetch_assoc()['count'] ?? 0;
$stmt->close();

// Total amount owed
$stmt = $conn->prepare("SELECT SUM(amount - amount_paid) as total FROM debts WHERE user_id = ? AND status != 'paid'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stats['total_owed'] = $row['total'] ?? 0;
$stmt->close();

// Total amount paid (last 30 days)
$stmt = $conn->prepare("SELECT SUM(amount) as total FROM payments WHERE user_id = ? AND payment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stats['total_paid_30days'] = $row['total'] ?? 0;
$stmt->close();

// Payment status data for chart
$stmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) as paid,
        COALESCE(SUM(CASE WHEN status != 'paid' THEN (amount - amount_paid) ELSE 0 END), 0) as unpaid
    FROM debts WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$chart_data = $result->fetch_assoc();
$stmt->close();

// Recent activity
$activities = getRecentActivity($user_id, 10);

// Recent debts
$stmt = $conn->prepare("
    SELECT d.*, c.name as customer_name 
    FROM debts d 
    JOIN customers c ON d.customer_id = c.id 
    WHERE d.user_id = ? 
    ORDER BY d.created_at DESC 
    LIMIT 10
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_debts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Monthly revenue data for chart
$stmt = $conn->prepare("
    SELECT 
        DATE_FORMAT(payment_date, '%Y-%m') as month,
        SUM(amount) as total
    FROM payments 
    WHERE user_id = ? AND payment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
    ORDER BY month ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$monthly_revenue = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Overdue debts
$stmt = $conn->prepare("
    SELECT COUNT(*) as count, SUM(amount - amount_paid) as total 
    FROM debts 
    WHERE user_id = ? AND status != 'paid' AND due_date < CURDATE()
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$overdue = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stats['overdue_count'] = $overdue['count'] ?? 0;
$stats['overdue_total'] = $overdue['total'] ?? 0;

// Response
echo json_encode([
    'success' => true,
    'data' => [
        'statistics' => $stats,
        'chart_data' => $chart_data,
        'recent_activity' => $activities,
        'recent_debts' => $recent_debts,
        'monthly_revenue' => $monthly_revenue
    ]
]);
?>