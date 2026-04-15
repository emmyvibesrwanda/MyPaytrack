<?php
require_once __DIR__ . '/includes/auth.php';
requireAdmin(); // This ensures only admin can access

$conn = getDB();
$page_title = 'Admin Dashboard';

// Get statistics with error handling
$total_users = 0;
$total_revenue = 0;
$pending_requests = 0;
$pro_users = 0;
$free_users = 0;

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
if ($result) $total_users = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments_history WHERE status = 'completed'");
if ($result) $total_revenue = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as count FROM payment_requests WHERE status = 'pending'");
if ($result) $pending_requests = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user' AND plan_id = 2");
if ($result) $pro_users = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user' AND plan_id = 1");
if ($result) $free_users = $result->fetch_assoc()['count'];

// Get pending payment requests
$pending_result = $conn->query("
    SELECT pr.*, u.full_name, u.email, u.phone 
    FROM payment_requests pr
    JOIN users u ON pr.user_id = u.id
    WHERE pr.status = 'pending'
    ORDER BY pr.requested_at DESC
");

// Get all users
$users_result = $conn->query("
    SELECT u.*, p.name as plan_name 
    FROM users u
    LEFT JOIN subscription_plans p ON u.plan_id = p.id
    WHERE u.role = 'user'
    ORDER BY u.created_at DESC
");

// Get payment history
$history_result = $conn->query("
    SELECT ph.*, u.full_name 
    FROM payments_history ph
    JOIN users u ON ph.user_id = u.id
    ORDER BY ph.payment_date DESC
    LIMIT 20
");

// Get monthly revenue for chart
$monthly_result = $conn->query("
    SELECT DATE_FORMAT(payment_date, '%M') as month, SUM(amount) as total 
    FROM payments_history 
    WHERE status = 'completed' 
    GROUP BY MONTH(payment_date) 
    ORDER BY payment_date DESC LIMIT 6
");

$months = [];
$revenues = [];
if ($monthly_result) {
    while ($row = $monthly_result->fetch_assoc()) {
        array_unshift($months, $row['month']);
        array_unshift($revenues, $row['total']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - PayTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .sidebar { background: #1e293b; min-height: 100vh; width: 260px; position: fixed; left: 0; top: 0; }
        .main-content { margin-left: 260px; padding: 20px; }
        .nav-link { color: #94a3b8; transition: all 0.3s; display: flex; align-items: center; padding: 12px 16px; border-radius: 8px; margin-bottom: 8px; }
        .nav-link i { width: 24px; margin-right: 12px; }
        .nav-link:hover, .nav-link.active { background: #334155; color: white; }
        .card { background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: all 0.3s; padding: 20px; }
        .card:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; display: inline-block; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fed7aa; color: #92400e; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .btn { padding: 8px 16px; border-radius: 8px; cursor: pointer; transition: all 0.2s; border: none; font-weight: 500; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; }
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000; }
        .modal.show { display: flex; }
        .modal-content { background: white; border-radius: 16px; max-width: 500px; width: 90%; padding: 24px; }
        .toast { position: fixed; bottom: 20px; right: 20px; padding: 12px 24px; border-radius: 8px; color: white; z-index: 1100; animation: slideIn 0.3s ease; }
        .toast-success { background: #10b981; }
        .toast-error { background: #ef4444; }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        table { width: 100%; }
        th { text-align: left; padding: 12px 16px; background: #f8fafc; font-size: 12px; font-weight: 600; color: #64748b; }
        td { padding: 12px 16px; border-bottom: 1px solid #e2e8f0; }
        tr:hover { background: #f8fafc; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="p-6 border-b border-gray-700">
            <div class="flex items-center space-x-2">
                <i class="fas fa-chart-line text-blue-400 text-2xl"></i>
                <span class="text-white text-xl font-bold">PayTrack Admin</span>
            </div>
        </div>
        <nav class="p-4">
            <a href="?tab=dashboard" class="nav-link <?php echo (!isset($_GET['tab']) || $_GET['tab'] == 'dashboard') ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="?tab=approvals" class="nav-link <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'approvals') ? 'active' : ''; ?>">
                <i class="fas fa-credit-card"></i> Payment Approvals
                <?php if ($pending_requests > 0): ?>
                    <span class="bg-red-500 text-white text-xs rounded-full px-2 ml-auto"><?php echo $pending_requests; ?></span>
                <?php endif; ?>
            </a>
            <a href="?tab=users" class="nav-link <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'users') ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> User Management
            </a>
            <a href="?tab=reports" class="nav-link <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'reports') ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> Revenue Reports
            </a>
            <a href="?tab=history" class="nav-link <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'history') ? 'active' : ''; ?>">
                <i class="fas fa-history"></i> Payment History
            </a>
            <hr class="border-gray-700 my-4">
            <a href="dashboard.php" class="nav-link">
                <i class="fas fa-arrow-left"></i> User Panel
            </a>
            <a href="logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="bg-gradient-to-r from-purple-600 to-pink-600 rounded-xl p-6 mb-8 text-white">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h1>
                    <p class="mt-1 opacity-90">Manage users, approve payments, and monitor revenue</p>
                </div>
                <i class="fas fa-user-shield text-5xl opacity-50"></i>
            </div>
        </div>
        
        <!-- Dashboard Tab -->
        <div id="dashboardTab" class="tab-content <?php echo (!isset($_GET['tab']) || $_GET['tab'] == 'dashboard') ? 'active' : ''; ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="card">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-gray-500 text-sm">Total Users</p>
                            <p class="text-2xl font-bold"><?php echo number_format($total_users); ?></p>
                            <p class="text-xs text-green-600 mt-1">Pro: <?php echo $pro_users; ?> | Free: <?php echo $free_users; ?></p>
                        </div>
                        <i class="fas fa-users text-3xl text-blue-400"></i>
                    </div>
                </div>
                <div class="card">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-gray-500 text-sm">Total Revenue</p>
                            <p class="text-2xl font-bold text-green-600"><?php echo number_format($total_revenue, 0, ',', '.'); ?> RWF</p>
                        </div>
                        <i class="fas fa-chart-line text-3xl text-green-400"></i>
                    </div>
                </div>
                <div class="card">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-gray-500 text-sm">Pending Approvals</p>
                            <p class="text-2xl font-bold text-yellow-600"><?php echo $pending_requests; ?></p>
                        </div>
                        <i class="fas fa-clock text-3xl text-yellow-400"></i>
                    </div>
                </div>
                <div class="card">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-gray-500 text-sm">Active Pro Users</p>
                            <p class="text-2xl font-bold text-purple-600"><?php echo $pro_users; ?></p>
                        </div>
                        <i class="fas fa-crown text-3xl text-purple-400"></i>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
                <div class="card">
                    <h3 class="font-semibold mb-4"><i class="fas fa-chart-line text-blue-500 mr-2"></i>Monthly Revenue</h3>
                    <canvas id="revenueChart" height="250"></canvas>
                </div>
                <div class="card">
                    <h3 class="font-semibold mb-4"><i class="fas fa-chart-pie text-purple-500 mr-2"></i>User Distribution</h3>
                    <canvas id="userChart" height="250"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Payment Approvals Tab -->
        <div id="approvalsTab" class="tab-content <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'approvals') ? 'active' : ''; ?>">
            <div class="card">
                <h2 class="text-lg font-semibold mb-4"><i class="fas fa-credit-card text-yellow-500 mr-2"></i>Pending Payment Approvals</h2>
                <div class="overflow-x-auto">
                    <table>
                        <thead>
                            <tr><th>User</th><th>Amount</th><th>Transaction Ref</th><th>Date</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php if ($pending_result && $pending_result->num_rows == 0): ?>
                                <tr><td colspan="5" class="text-center text-gray-500 py-8">No pending payment requests</td></tr>
                            <?php elseif ($pending_result): ?>
                                <?php while ($row = $pending_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><p class="font-medium"><?php echo htmlspecialchars($row['full_name']); ?></p><p class="text-xs text-gray-500"><?php echo htmlspecialchars($row['email']); ?></p></td>
                                        <td><span class="font-bold text-purple-600"><?php echo number_format($row['amount'], 0, ',', '.'); ?> RWF</span></td>
                                        <td><code class="text-sm bg-gray-100 px-2 py-1 rounded"><?php echo htmlspecialchars($row['transaction_reference']); ?></code></td>
                                        <td class="text-sm"><?php echo date('M d, Y H:i', strtotime($row['requested_at'])); ?></td>
                                        <td><button onclick="approvePayment(<?php echo $row['id']; ?>, <?php echo $row['user_id']; ?>)" class="text-green-600 hover:text-green-800 mr-3"><i class="fas fa-check-circle text-xl"></i></button><button onclick="rejectPayment(<?php echo $row['id']; ?>, <?php echo $row['user_id']; ?>)" class="text-red-600 hover:text-red-800"><i class="fas fa-times-circle text-xl"></i></button></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Users Tab -->
        <div id="usersTab" class="tab-content <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'users') ? 'active' : ''; ?>">
            <div class="card">
                <h2 class="text-lg font-semibold mb-4"><i class="fas fa-users text-blue-500 mr-2"></i>Registered Users</h2>
                <div class="overflow-x-auto">
                    <table>
                        <thead><tr><th>User</th><th>Contact</th><th>Plan</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php while ($user = $users_result->fetch_assoc()): ?>
                                <tr>
                                    <td><p class="font-medium"><?php echo htmlspecialchars($user['full_name']); ?></p><p class="text-xs text-gray-500">ID: #<?php echo $user['id']; ?></p></td>
                                    <td><p class="text-sm"><?php echo htmlspecialchars($user['email']); ?></p><p class="text-xs text-gray-500"><?php echo htmlspecialchars($user['phone'] ?? 'No phone'); ?></p></td>
                                    <td><span class="badge <?php echo $user['plan_id'] == 2 ? 'badge-info' : 'badge-success'; ?>"><?php echo htmlspecialchars($user['plan_name']); ?></span></td>
                                    <td><span class="badge <?php echo $user['subscription_status'] == 'active' ? 'badge-success' : 'badge-danger'; ?>"><?php echo ucfirst($user['subscription_status']); ?></span></td>
                                    <td class="text-sm"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td><?php if ($user['plan_id'] != 2): ?><button onclick="upgradeUser(<?php echo $user['id']; ?>)" class="text-purple-600 hover:text-purple-800"><i class="fas fa-arrow-up text-lg"></i></button><?php endif; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Reports Tab -->
        <div id="reportsTab" class="tab-content <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'reports') ? 'active' : ''; ?>">
            <div class="card mb-6">
                <h3 class="font-semibold mb-4"><i class="fas fa-chart-line text-blue-500 mr-2"></i>Monthly Revenue Trend</h3>
                <canvas id="revenueChart2" height="300"></canvas>
            </div>
            <div class="card">
                <h3 class="font-semibold mb-4"><i class="fas fa-trophy text-yellow-500 mr-2"></i>Subscription Summary</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-green-50 rounded-lg p-4 text-center"><p class="text-2xl font-bold text-green-600"><?php echo $pro_users; ?></p><p class="text-sm text-gray-600">Pro Users</p></div>
                    <div class="bg-blue-50 rounded-lg p-4 text-center"><p class="text-2xl font-bold text-blue-600"><?php echo $free_users; ?></p><p class="text-sm text-gray-600">Free Users</p></div>
                    <div class="bg-purple-50 rounded-lg p-4 text-center"><p class="text-2xl font-bold text-purple-600"><?php echo number_format($total_revenue, 0, ',', '.'); ?> RWF</p><p class="text-sm text-gray-600">Total Revenue</p></div>
                </div>
            </div>
        </div>
        
        <!-- History Tab -->
        <div id="historyTab" class="tab-content <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'history') ? 'active' : ''; ?>">
            <div class="card">
                <h2 class="text-lg font-semibold mb-4"><i class="fas fa-history text-blue-500 mr-2"></i>Payment History</h2>
                <div class="overflow-x-auto">
                    <table>
                        <thead><tr><th>Date</th><th>User</th><th>Amount</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php while ($payment = $history_result->fetch_assoc()): ?>
                                <tr><td class="text-sm"><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td><td class="font-medium"><?php echo htmlspecialchars($payment['full_name']); ?></td><td class="text-green-600 font-semibold"><?php echo number_format($payment['amount'], 0, ',', '.'); ?> RWF</td><td><span class="badge badge-success">Completed</span></td></tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modals -->
    <div id="approveModal" class="modal"><div class="modal-content"><div class="flex justify-between items-center mb-4"><h3 class="text-xl font-semibold">Confirm Approval</h3><button onclick="closeModal('approveModal')" class="text-gray-400 hover:text-gray-600">&times;</button></div><p>Approve this payment? User will be upgraded to Pro Plan.</p><div class="flex space-x-3 mt-6"><button id="confirmApprove" class="flex-1 btn btn-success">Approve</button><button onclick="closeModal('approveModal')" class="flex-1 btn btn-danger">Cancel</button></div></div></div>
    <div id="rejectModal" class="modal"><div class="modal-content"><div class="flex justify-between items-center mb-4"><h3 class="text-xl font-semibold">Confirm Rejection</h3><button onclick="closeModal('rejectModal')" class="text-gray-400 hover:text-gray-600">&times;</button></div><p>Reject this payment? User will be notified.</p><div class="flex space-x-3 mt-6"><button id="confirmReject" class="flex-1 btn btn-danger">Reject</button><button onclick="closeModal('rejectModal')" class="flex-1 btn btn-secondary">Cancel</button></div></div></div>
    
    <script>
    let currentRequestId = null, currentUserId = null;
    function approvePayment(id, uid) { currentRequestId = id; currentUserId = uid; document.getElementById('approveModal').classList.add('show'); }
    function rejectPayment(id, uid) { currentRequestId = id; currentUserId = uid; document.getElementById('rejectModal').classList.add('show'); }
    function closeModal(id) { document.getElementById(id).classList.remove('show'); }
    function showToast(msg, type) { var t=document.createElement('div'); t.className='toast toast-'+type; t.innerHTML=msg; document.body.appendChild(t); setTimeout(function(){t.remove();},3000); }
    
    document.getElementById('confirmApprove').onclick = function() {
        fetch('admin_api.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=approve_payment&request_id='+currentRequestId+'&user_id='+currentUserId })
        .then(r=>r.json()).then(r=>{ showToast(r.message, r.success?'success':'error'); if(r.success) setTimeout(()=>location.reload(),1500); closeModal('approveModal'); });
    };
    document.getElementById('confirmReject').onclick = function() {
        fetch('admin_api.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=reject_payment&request_id='+currentRequestId+'&user_id='+currentUserId })
        .then(r=>r.json()).then(r=>{ showToast(r.message, r.success?'success':'error'); if(r.success) setTimeout(()=>location.reload(),1500); closeModal('rejectModal'); });
    };
    function upgradeUser(id) { if(confirm('Upgrade this user to Pro Plan?')) fetch('admin_api.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=manual_upgrade&user_id='+id}).then(r=>r.json()).then(r=>{showToast(r.message,r.success?'success':'error');if(r.success)setTimeout(()=>location.reload(),1500);}); }
    
    new Chart(document.getElementById('revenueChart'), { type:'line', data:{ labels:<?php echo json_encode($months); ?>, datasets:[{ label:'Revenue (RWF)', data:<?php echo json_encode($revenues); ?>, borderColor:'#3b82f6', backgroundColor:'rgba(59,130,246,0.1)', fill:true, tension:0.4 }] }, options:{ responsive:true, maintainAspectRatio:true } });
    new Chart(document.getElementById('revenueChart2'), { type:'line', data:{ labels:<?php echo json_encode($months); ?>, datasets:[{ label:'Revenue (RWF)', data:<?php echo json_encode($revenues); ?>, borderColor:'#3b82f6', backgroundColor:'rgba(59,130,246,0.1)', fill:true, tension:0.4 }] }, options:{ responsive:true, maintainAspectRatio:true } });
    new Chart(document.getElementById('userChart'), { type:'doughnut', data:{ labels:['Free Users','Pro Users'], datasets:[{ data:[<?php echo $free_users; ?>,<?php echo $pro_users; ?>], backgroundColor:['#10b981','#8b5cf6'] }] }, options:{ responsive:true, maintainAspectRatio:true } });
    </script>
</body>
</html>