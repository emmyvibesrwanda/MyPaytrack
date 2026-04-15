<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - PayTrack</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .logout-card {
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
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8 logout-card text-center">
        <div class="mb-6">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-red-100 rounded-full mb-4">
                <i class="fas fa-sign-out-alt text-red-600 text-4xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-800">Confirm Logout</h1>
            <p class="text-gray-600 mt-2">Are you sure you want to logout from PayTrack?</p>
        </div>
        
        <div class="flex space-x-4">
            <button id="confirmLogoutBtn" class="flex-1 bg-red-600 text-white py-2 rounded-lg hover:bg-red-700 transition">
                <i class="fas fa-check mr-2"></i> Yes, Logout
            </button>
            <button id="cancelLogoutBtn" class="flex-1 bg-gray-300 text-gray-700 py-2 rounded-lg hover:bg-gray-400 transition">
                <i class="fas fa-times mr-2"></i> Cancel
            </button>
        </div>
    </div>
    
    <script>
        document.getElementById('confirmLogoutBtn').addEventListener('click', function() {
            window.location.href = 'logout_process.php';
        });
        
        document.getElementById('cancelLogoutBtn').addEventListener('click', function() {
            // Go back to previous page
            window.history.back();
        });
    </script>
</body>
</html>