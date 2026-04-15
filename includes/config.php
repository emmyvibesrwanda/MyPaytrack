<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'paytrack');

// App configuration
define('APP_NAME', 'PayTrack');
define('APP_URL', 'http://localhost/paytrack');
define('APP_TIMEZONE', 'Africa/Kigali');
define('CURRENCY', 'RWF');
define('CURRENCY_SYMBOL', 'FRw');

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Set timezone
date_default_timezone_set(APP_TIMEZONE);

// Check if user has theme preference in session
if (!isset($_SESSION['theme']) && isset($_SESSION['user_id'])) {
    $conn = getDB();
    $stmt = $conn->prepare("SELECT theme_preference FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $_SESSION['theme'] = $row['theme_preference'];
    } else {
        $_SESSION['theme'] = 'light';
    }
    $stmt->close();
} elseif (!isset($_SESSION['theme'])) {
    $_SESSION['theme'] = 'light';
}
?>