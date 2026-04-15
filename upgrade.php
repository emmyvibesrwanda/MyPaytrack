<?php
require_once 'includes/auth.php';
requireAuth();
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$conn = getDB();
$page_title = 'Upgrade to Pro';

// Get current user data
$result = $conn->query("SELECT * FROM users WHERE id = $user_id");
$current_user = $result->fetch_assoc();

// Get admin settings
$result = $conn->query("SELECT * FROM admin_settings WHERE id = 1");
$admin_settings = $result->fetch_assoc();

// Handle payment request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $transaction_reference = $conn->real_escape_string($_POST['transaction_reference']);
    $payment_method = 'mobile_money';
    
    // Check if user already has a pending request
    $result = $conn->query("SELECT id FROM payment_requests WHERE user_id = $user_id AND status = 'pending'");
    
    if ($result->num_rows > 0) {
        $error = "You already have a pending request. Please wait for admin approval.";
    } else {
        // Create payment request
        $conn->query("INSERT INTO payment_requests (user_id, plan_id, amount, transaction_reference, payment_method, status) 
                      VALUES ($user_id, 2, 3000, '$transaction_reference', '$payment_method', 'pending')");
        
        if ($conn->affected_rows > 0) {
            // Add notification for user
            $title = "Upgrade Request Submitted";
            $message = "Your upgrade request has been submitted. Admin will review and process it within 24 hours.";
            $conn->query("INSERT INTO notifications (user_id, title, message, type) VALUES ($user_id, '$title', '$message', 'payment')");
            
            $success = "✅ Upgrade request submitted successfully! Admin will verify your payment and upgrade your account within 24 hours.";
        } else {
            $error = "Failed to submit upgrade request. Please try again.";
        }
    }
}

// Check if user already has a pending request
$result = $conn->query("SELECT * FROM payment_requests WHERE user_id = $user_id AND status = 'pending' ORDER BY requested_at DESC LIMIT 1");
$pending_request = $result->fetch_assoc();

include 'includes/header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Upgrade to Pro Plan</h1>
        <p class="text-gray-600 mt-2">Get unlimited features for only 3,000 RWF/month</p>
    </div>

    <?php if ($current_user['plan_id'] == 2 && $current_user['subscription_status'] == 'active'): ?>
        <div class="card p-8 mb-8 bg-gradient-to-r from-green-50 to-green-100 border-green-200 text-center">
            <i class="fas fa-crown text-6xl text-green-600 mb-4"></i>
            <h2 class="text-2xl font-bold text-green-800">You're Already a Pro User!</h2>
            <p class="text-green-700 mt-2">Thank you for being a premium member.</p>
            <a href="dashboard.php" class="btn btn-primary mt-6 inline-block">Go to Dashboard</a>
        </div>
    <?php elseif ($pending_request): ?>
        <div class="card p-8 mb-8 bg-gradient-to-r from-yellow-50 to-yellow-100 border-yellow-200 text-center">
            <i class="fas fa-clock text-6xl text-yellow-600 mb-4"></i>
            <h2 class="text-2xl font-bold text-yellow-800">Request Pending Review</h2>
            <p class="text-yellow-700 mt-2">Your upgrade request is being reviewed by Admin.</p>
            <div class="bg-white rounded-lg p-4 mt-4 max-w-md mx-auto">
                <p class="text-sm text-gray-600">📅 Submitted: <?php echo date('M d, Y H:i', strtotime($pending_request['requested_at'])); ?></p>
                <p class="text-sm text-gray-600">🔑 Transaction Ref: <strong><?php echo htmlspecialchars($pending_request['transaction_reference']); ?></strong></p>
            </div>
            <a href="dashboard.php" class="btn btn-primary mt-6 inline-block">Return to Dashboard</a>
        </div>
    <?php else: ?>
        <!-- Pro Plan Benefits -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="card p-6 bg-gradient-to-br from-blue-50 to-purple-50">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Pro Plan Features</h3>
                <ul class="space-y-3">
                    <li class="flex items-center"><i class="fas fa-check-circle text-green-500 mr-3"></i> Unlimited customers</li>
                    <li class="flex items-center"><i class="fas fa-check-circle text-green-500 mr-3"></i> Unlimited invoices</li>
                    <li class="flex items-center"><i class="fas fa-check-circle text-green-500 mr-3"></i> Advanced reports & charts</li>
                    <li class="flex items-center"><i class="fas fa-check-circle text-green-500 mr-3"></i> Payment reminders</li>
                    <li class="flex items-center"><i class="fas fa-check-circle text-green-500 mr-3"></i> Priority support</li>
                    <li class="flex items-center"><i class="fas fa-check-circle text-green-500 mr-3"></i> Export data to CSV/Excel</li>
                </ul>
            </div>
            
            <div class="card p-6 bg-gradient-to-br from-yellow-50 to-orange-50 text-center">
                <p class="text-sm text-gray-500">Special Offer</p>
                <p class="text-4xl font-bold text-purple-600">3,000 RWF</p>
                <p class="text-gray-500">per month</p>
                <div class="mt-4 inline-block bg-green-100 rounded-full px-4 py-2">
                    <i class="fas fa-tag text-green-600 mr-2"></i>
                    <span class="font-semibold text-green-600">Best Value</span>
                </div>
            </div>
        </div>

        <!-- Payment Instructions -->
        <div class="card p-6 mb-8">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">How to Upgrade</h3>
            
            <div class="bg-gradient-to-r from-blue-50 to-purple-50 rounded-lg p-6 mb-6">
                <div class="space-y-4">
                    <div class="flex items-start">
                        <div class="w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold mr-3 flex-shrink-0">1</div>
                        <div>
                            <p class="font-medium text-gray-800">Send <span class="text-xl font-bold text-purple-600">3,000 RWF</span> via Mobile Money to:</p>
                            <div class="mt-2 bg-white rounded-lg p-3">
                                <p class="text-lg"><i class="fas fa-mobile-alt text-green-600 mr-2"></i> <strong>Mobile Money Number:</strong> <span class="font-mono text-blue-600 text-xl"><?php echo htmlspecialchars($admin_settings['mobile_money_number']); ?></span></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold mr-3 flex-shrink-0">2</div>
                        <div>
                            <p class="font-medium text-gray-800">After payment, note your <strong>transaction reference number</strong></p>
                            <p class="text-sm text-gray-500 mt-1">This is the confirmation number you receive after sending money</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold mr-3 flex-shrink-0">3</div>
                        <div>
                            <p class="font-medium text-gray-800">Enter your transaction reference below and submit</p>
                            <p class="text-sm text-gray-500 mt-1">Admin will verify and upgrade your account within 24 hours</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-info-circle text-yellow-600 text-xl mr-3"></i>
                    <div>
                        <p class="font-medium text-yellow-800">Need help?</p>
                        <p class="text-sm text-yellow-700">Contact support: <?php echo htmlspecialchars($admin_settings['payment_email']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upgrade Request Form -->
        <div class="card p-6">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Confirm Your Payment</h3>
            
            <?php if (isset($success)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
                    <i class="fas fa-check-circle mr-2"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
                    <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Transaction Reference Number *</label>
                    <input type="text" name="transaction_reference" required class="w-full" placeholder="Enter your Mobile Money transaction reference">
                    <p class="text-xs text-gray-500 mt-1">Example: REF123456789 or the confirmation number from your mobile money</p>
                </div>
                
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                    <p class="text-sm text-blue-800">
                        <i class="fas fa-info-circle mr-1"></i>
                        After submitting, admin will verify your payment. You will receive a notification once approved.
                    </p>
                </div>
                
                <button type="submit" name="submit_request" class="btn btn-primary w-full text-lg py-3">
                    <i class="fas fa-paper-plane mr-2"></i> Submit Payment Request
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>