<?php
require_once '../includes/auth.php';
requireAuth();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$conn = getDB();

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

if ($action === 'theme') {
    $theme = $data['theme'] ?? 'light';
    
    if ($theme === 'dark' || $theme === 'light') {
        $stmt = $conn->prepare("UPDATE users SET theme_preference = ? WHERE id = ?");
        $stmt->bind_param("si", $theme, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['theme'] = $theme;
            echo json_encode(['success' => true, 'message' => 'Theme updated']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update theme']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid theme']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>