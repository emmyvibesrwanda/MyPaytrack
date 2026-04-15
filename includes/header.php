<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? APP_NAME; ?> - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            width: 280px;
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            transition: all 0.3s ease;
            z-index: 1000;
            overflow-y: auto;
        }
        .main-content {
            margin-left: 280px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            margin: 4px 12px;
            border-radius: 12px;
            color: #94a3b8;
            transition: all 0.3s ease;
        }
        .nav-item i {
            width: 24px;
            margin-right: 12px;
            font-size: 18px;
        }
        .nav-item:hover {
            background: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
            transform: translateX(5px);
        }
        .nav-item.active {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }
        .nav-header {
            padding: 24px 20px;
            border-bottom: 1px solid #334155;
            margin-bottom: 20px;
        }
        .user-info {
            padding: 20px;
            border-top: 1px solid #334155;
            margin-top: auto;
        }
        /* Mobile menu button */
        .menu-btn {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: #3b82f6;
            color: white;
            padding: 10px 15px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                padding-top: 70px;
            }
            .menu-btn {
                display: block;
            }
        }
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
            z-index: 1100;
        }
        .modal.show { display: flex; }
        .modal-content {
            background: white;
            border-radius: 16px;
            max-width: 450px;
            width: 90%;
            animation: slideUp 0.3s ease;
        }
        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        /* Toast Notifications */
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 14px 24px;
            border-radius: 12px;
            color: white;
            z-index: 1200;
            animation: slideInRight 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .toast-success { background: linear-gradient(135deg, #10b981, #059669); }
        .toast-error { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .toast-info { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .btn { padding: 10px 20px; border-radius: 8px; font-weight: 500; cursor: pointer; border: none; transition: all 0.2s; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; transform: translateY(-1px); }
        .btn-secondary { background: #6b7280; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fed7aa; color: #92400e; }
        .badge-info { background: #dbeafe; color: #1e40af; }
    </style>
</head>
<body>

<?php if (isset($_SESSION['user_id'])): ?>
<!-- Mobile Menu Button -->
<div class="menu-btn" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</div>

<!-- Sidebar Navigation -->
<div class="sidebar" id="sidebar">
    <div class="nav-header">
        <div class="flex items-center space-x-3">
            <i class="fas fa-chart-line text-blue-400 text-2xl"></i>
            <span class="text-white text-xl font-bold">PayTrack</span>
        </div>
        <p class="text-gray-500 text-sm mt-2">Invoice & Debt Management</p>
    </div>
    
    <nav>
        <a href="dashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        <a href="customers.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'customers.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Customers</span>
        </a>
        <a href="invoices.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'invoices.php' ? 'active' : ''; ?>">
            <i class="fas fa-file-invoice"></i>
            <span>Invoices</span>
        </a>
        <a href="reminders.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'reminders.php' ? 'active' : ''; ?>">
            <i class="fas fa-bell"></i>
            <span>Reminders</span>
        </a>
        <a href="reports.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <span>Reports</span>
        </a>
        <a href="settings.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
        </a>
        <a href="upgrade.php" class="nav-item">
            <i class="fas fa-rocket"></i>
            <span>Upgrade to Pro</span>
        </a>
    </nav>
    
    <div class="user-info">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center">
                <i class="fas fa-user text-white"></i>
            </div>
            <div class="flex-1">
                <p class="text-white font-medium"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                <p class="text-gray-400 text-xs"><?php echo $_SESSION['role'] ?? 'User'; ?></p>
            </div>
            <a href="#" onclick="confirmAction('Are you sure you want to logout?', function() { window.location.href = 'logout.php'; }); return false;" class="text-red-400 hover:text-red-300">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</div>

<div class="main-content" id="mainContent">
<?php else: ?>
<div>
<?php endif; ?>

<!-- Global Confirmation Modal -->
<div id="confirmationModal" class="modal">
    <div class="modal-content">
        <div class="p-6">
            <div class="text-center mb-4">
                <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-question-circle text-yellow-600 text-2xl"></i>
                </div>
                <h3 id="modalTitle" class="text-xl font-semibold text-gray-800 mb-2">Confirm Action</h3>
                <p id="modalMessage" class="text-gray-600"></p>
            </div>
            <div class="flex space-x-3">
                <button id="modalConfirmBtn" class="flex-1 btn btn-primary">Confirm</button>
                <button id="modalCancelBtn" class="flex-1 btn btn-secondary">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle sidebar on mobile
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
}

// Global confirmation function
function confirmAction(message, onConfirm, onCancel = null) {
    const modal = document.getElementById('confirmationModal');
    const modalMessage = document.getElementById('modalMessage');
    const confirmBtn = document.getElementById('modalConfirmBtn');
    const cancelBtn = document.getElementById('modalCancelBtn');
    
    modalMessage.textContent = message;
    modal.classList.add('show');
    
    const handleConfirm = () => {
        modal.classList.remove('show');
        confirmBtn.removeEventListener('click', handleConfirm);
        cancelBtn.removeEventListener('click', handleCancel);
        if (onConfirm) onConfirm();
    };
    
    const handleCancel = () => {
        modal.classList.remove('show');
        confirmBtn.removeEventListener('click', handleConfirm);
        cancelBtn.removeEventListener('click', handleCancel);
        if (onCancel) onCancel();
    };
    
    confirmBtn.addEventListener('click', handleConfirm);
    cancelBtn.addEventListener('click', handleCancel);
}

// Toast notification
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i> <span>${message}</span>`;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

function formatCurrency(amount) {
    return Number(amount).toLocaleString('rw-RW') + ' RWF';
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const menuBtn = document.querySelector('.menu-btn');
    if (window.innerWidth <= 768) {
        if (sidebar && !sidebar.contains(event.target) && !menuBtn?.contains(event.target)) {
            sidebar.classList.remove('open');
        }
    }
});
</script>