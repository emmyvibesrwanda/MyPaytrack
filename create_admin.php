<?php
require_once __DIR__ . '/includes/db.php';

$conn = getDB();

// Check if admin exists
$result = $conn->query("SELECT * FROM users WHERE email = 'becypher01@gmail.com'");

if ($result->num_rows > 0) {
    // Update existing user to admin
    $password_hash = password_hash('Boneri@123', PASSWORD_DEFAULT);
    $conn->query("UPDATE users SET role = 'admin', plan_id = 2, password_hash = '$password_hash' WHERE email = 'becypher01@gmail.com'");
    echo "✅ Admin user updated!<br>";
} else {
    // Create new admin
    $password_hash = password_hash('Boneri@123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO users (full_name, email, password_hash, phone, plan_id, role) VALUES 
                  ('Admin Emmy', 'becypher01@gmail.com', '$password_hash', '+250788888888', 2, 'admin')");
    echo "✅ Admin user created!<br>";
}

echo "<br><strong>Admin Credentials:</strong><br>";
echo "Email: becypher01@gmail.com<br>";
echo "Password: Boneri@123<br>";
echo "<br><a href='auth.php'>Go to Login Page</a>";
?>