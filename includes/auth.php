<?php
require_once __DIR__ . '/db.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function register($full_name, $email, $password, $phone = '') {
        $conn = $this->db->getConnection();
        
        // Check if email exists
        $result = $conn->query("SELECT id FROM users WHERE email = '" . $conn->real_escape_string($email) . "'");
        if ($result && $result->num_rows > 0) {
            return ['success' => false, 'message' => 'Email already registered'];
        }
        
        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $full_name_esc = $conn->real_escape_string($full_name);
        $email_esc = $conn->real_escape_string($email);
        $phone_esc = $conn->real_escape_string($phone);
        
        // Insert user with free plan (regular user, not admin)
        $conn->query("INSERT INTO users (full_name, email, password_hash, phone, plan_id, role) 
                      VALUES ('$full_name_esc', '$email_esc', '$password_hash', '$phone_esc', 1, 'user')");
        
        if ($conn->affected_rows > 0) {
            $user_id = $conn->insert_id;
            return ['success' => true, 'user_id' => $user_id];
        }
        return ['success' => false, 'message' => 'Registration failed'];
    }
    
    public function login($email, $password) {
        $conn = $this->db->getConnection();
        $email_esc = $conn->real_escape_string($email);
        
        $result = $conn->query("SELECT * FROM users WHERE email = '$email_esc'");
        
        if (!$result || $result->num_rows === 0) {
            return ['success' => false, 'message' => 'Invalid email or password'];
        }
        
        $user = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['plan_id'] = $user['plan_id'];
            
            return ['success' => true, 'user' => $user];
        }
        
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
    
    public function logout() {
        $_SESSION = array();
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time()-3600, '/');
        }
        session_destroy();
        return true;
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
    
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        $conn = $this->db->getConnection();
        $user_id = (int)$_SESSION['user_id'];
        
        $result = $conn->query("SELECT * FROM users WHERE id = $user_id");
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        return null;
    }
}

// Initialize auth
$auth = new Auth();

// Helper functions
function requireAuth() {
    global $auth;
    if (!$auth->isLoggedIn()) {
        header('Location: auth.php');
        exit();
    }
}

function requireAdmin() {
    global $auth;
    if (!$auth->isLoggedIn()) {
        header('Location: auth.php');
        exit();
    }
    if (!$auth->isAdmin()) {
        header('Location: dashboard.php');
        exit();
    }
}
?>