<?php
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect based on role
if ($auth->isLoggedIn()) {
    if ($auth->isAdmin()) {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'login') {
            $result = $auth->login($_POST['email'], $_POST['password']);
            if ($result['success']) {
                // Redirect based on role after login
                if ($auth->isAdmin()) {
                    header('Location: admin_dashboard.php');
                } else {
                    header('Location: dashboard.php');
                }
                exit();
            } else {
                $error = $result['message'];
            }
        } elseif ($_POST['action'] === 'register') {
            if ($_POST['password'] !== $_POST['confirm_password']) {
                $error = 'Passwords do not match';
            } else {
                $result = $auth->register(
                    $_POST['full_name'],
                    $_POST['email'],
                    $_POST['password'],
                    $_POST['phone'] ?? ''
                );
                if ($result['success']) {
                    $success = 'Registration successful! Please login.';
                } else {
                    $error = $result['message'];
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PayTrack</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .auth-card {
            animation: fadeInUp 0.5s ease-out;
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .input-group {
            transition: all 0.3s ease;
        }
        .input-group:focus-within {
            transform: translateX(5px);
        }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8 auth-card">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl mb-4">
                <i class="fas fa-chart-line text-white text-4xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800">PayTrack</h1>
            <p class="text-gray-600 mt-2">Invoice & Debt Management System</p>
        </div>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4 flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <!-- Login Form -->
        <form method="POST" id="loginForm">
            <input type="hidden" name="action" value="login">
            <div class="mb-4 input-group">
                <label class="block text-gray-700 text-sm font-bold mb-2">Email Address</label>
                <div class="relative">
                    <i class="fas fa-envelope absolute left-3 top-3 text-gray-400"></i>
                    <input type="email" name="email" class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200" required>
                </div>
            </div>
            <div class="mb-6 input-group">
                <label class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                <div class="relative">
                    <i class="fas fa-lock absolute left-3 top-3 text-gray-400"></i>
                    <input type="password" name="password" class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200" required>
                </div>
            </div>
            <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-blue-700 text-white py-2 rounded-lg hover:from-blue-700 hover:to-blue-800 transition transform hover:scale-105">
                <i class="fas fa-sign-in-alt mr-2"></i> Login
            </button>
        </form>
        
        <!-- Register Form -->
        <form method="POST" id="registerForm" style="display: none;">
            <input type="hidden" name="action" value="register">
            <div class="mb-4 input-group">
                <label class="block text-gray-700 text-sm font-bold mb-2">Full Name</label>
                <div class="relative">
                    <i class="fas fa-user absolute left-3 top-3 text-gray-400"></i>
                    <input type="text" name="full_name" class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" required>
                </div>
            </div>
            <div class="mb-4 input-group">
                <label class="block text-gray-700 text-sm font-bold mb-2">Email Address</label>
                <div class="relative">
                    <i class="fas fa-envelope absolute left-3 top-3 text-gray-400"></i>
                    <input type="email" name="email" class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" required>
                </div>
            </div>
            <div class="mb-4 input-group">
                <label class="block text-gray-700 text-sm font-bold mb-2">Phone Number</label>
                <div class="relative">
                    <i class="fas fa-phone absolute left-3 top-3 text-gray-400"></i>
                    <input type="tel" name="phone" class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg">
                </div>
            </div>
            <div class="mb-4 input-group">
                <label class="block text-gray-700 text-sm font-bold mb-2">Password</label>
                <div class="relative">
                    <i class="fas fa-lock absolute left-3 top-3 text-gray-400"></i>
                    <input type="password" name="password" class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" required>
                </div>
            </div>
            <div class="mb-6 input-group">
                <label class="block text-gray-700 text-sm font-bold mb-2">Confirm Password</label>
                <div class="relative">
                    <i class="fas fa-lock absolute left-3 top-3 text-gray-400"></i>
                    <input type="password" name="confirm_password" class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" required>
                </div>
            </div>
            <button type="submit" class="w-full bg-gradient-to-r from-green-600 to-green-700 text-white py-2 rounded-lg hover:from-green-700 hover:to-green-800 transition transform hover:scale-105">
                <i class="fas fa-user-plus mr-2"></i> Create Account
            </button>
        </form>
        
        <div class="mt-6 text-center">
            <a href="#" id="showRegisterBtn" class="text-blue-600 hover:underline text-sm">Don't have an account? Register</a>
            <a href="#" id="showLoginBtn" class="text-blue-600 hover:underline text-sm" style="display: none;">Back to Login</a>
        </div>
        
        <div class="mt-4 text-center text-xs text-gray-500">
            <p>Secure invoice and debt management system</p>
        </div>
    </div>
    
    <script>
        const loginForm = document.getElementById('loginForm');
        const registerForm = document.getElementById('registerForm');
        const showRegisterBtn = document.getElementById('showRegisterBtn');
        const showLoginBtn = document.getElementById('showLoginBtn');
        
        showRegisterBtn.addEventListener('click', function(e) {
            e.preventDefault();
            loginForm.style.display = 'none';
            registerForm.style.display = 'block';
            showRegisterBtn.style.display = 'none';
            showLoginBtn.style.display = 'inline';
        });
        
        showLoginBtn.addEventListener('click', function(e) {
            e.preventDefault();
            loginForm.style.display = 'block';
            registerForm.style.display = 'none';
            showRegisterBtn.style.display = 'inline';
            showLoginBtn.style.display = 'none';
        });
        
        // Check URL parameter for register tab
        if (window.location.href.includes('register')) {
            showRegisterBtn.click();
        }
    </script>
</body>
</html>