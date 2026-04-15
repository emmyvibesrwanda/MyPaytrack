<?php
require_once __DIR__ . '/includes/auth.php';
requireAuth();

$conn = getDB();
$user_id = $_SESSION['user_id'];

// Get user data
$user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();

// Update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $business_name = $conn->real_escape_string($_POST['business_name']);
    
    $conn->query("UPDATE users SET full_name = '$full_name', phone = '$phone', business_name = '$business_name' WHERE id = $user_id");
    $_SESSION['user_name'] = $full_name;
    $success = "Profile updated successfully";
}

// Change password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    
    if (password_verify($current, $user['password_hash'])) {
        if ($new === $confirm) {
            $new_hash = password_hash($new, PASSWORD_DEFAULT);
            $conn->query("UPDATE users SET password_hash = '$new_hash' WHERE id = $user_id");
            $success = "Password changed successfully";
        } else {
            $error = "New passwords do not match";
        }
    } else {
        $error = "Current password is incorrect";
    }
}

$page_title = 'Settings';
include __DIR__ . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Settings</h1>
    
    <?php if (isset($success)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4"><?php echo $success; ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Profile Settings -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Profile Settings</h3>
            <form method="POST">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Full Name</label>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" class="w-full px-3 py-2 border rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                    <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="w-full px-3 py-2 border rounded-lg bg-gray-100" readonly disabled>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Phone</label>
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Business Name</label>
                    <input type="text" name="business_name" value="<?php echo htmlspecialchars($user['business_name']); ?>" class="w-full px-3 py-2 border rounded-lg">
                </div>
                <button type="submit" name="update_profile" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700">Update Profile</button>
            </form>
        </div>
        
        <!-- Change Password -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Change Password</h3>
            <form method="POST">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Current Password</label>
                    <input type="password" name="current_password" class="w-full px-3 py-2 border rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">New Password</label>
                    <input type="password" name="new_password" class="w-full px-3 py-2 border rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="w-full px-3 py-2 border rounded-lg" required>
                </div>
                <button type="submit" name="change_password" class="w-full bg-green-600 text-white py-2 rounded-lg hover:bg-green-700">Change Password</button>
            </form>
        </div>
        
        <!-- Plan Information -->
        <div class="bg-white rounded-xl shadow-md p-6 lg:col-span-2">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Current Plan</h3>
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-2xl font-bold text-blue-600"><?php echo $user['plan_id'] == 2 ? 'Pro Plan' : 'Free Plan'; ?></p>
                    <p class="text-gray-600 mt-1"><?php echo $user['plan_id'] == 2 ? 'Unlimited customers and invoices' : 'Limited to 10 customers and 5 invoices per month'; ?></p>
                </div>
                <?php if ($user['plan_id'] != 2): ?>
                    <a href="upgrade.php" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700">Upgrade to Pro →</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>