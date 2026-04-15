<?php
require_once __DIR__ . '/includes/auth.php';
requireAuth();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mail_config.php'; // Make sure this line exists


$conn = getDB();
$user_id = $_SESSION['user_id'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    
    // Add Reminder
    if ($action === 'add_reminder') {
        $customer_id = (int)$_POST['customer_id'];
        $reminder_date = $conn->real_escape_string($_POST['reminder_date']);
        $type = $conn->real_escape_string($_POST['type']);
        $message = $conn->real_escape_string($_POST['message']);
        
        $conn->query("INSERT INTO reminders (user_id, customer_id, reminder_date, type, message, status) 
                      VALUES ($user_id, $customer_id, '$reminder_date', '$type', '$message', 'pending')");
        
        if ($conn->affected_rows > 0) {
            logActivity($user_id, 'add_reminder', "Added reminder for customer ID: $customer_id");
            echo json_encode(['success' => true, 'message' => 'Reminder added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add reminder']);
        }
        exit();
    }
    
    // Edit Reminder
    if ($action === 'edit_reminder') {
        $reminder_id = (int)$_POST['reminder_id'];
        $customer_id = (int)$_POST['customer_id'];
        $reminder_date = $conn->real_escape_string($_POST['reminder_date']);
        $type = $conn->real_escape_string($_POST['type']);
        $message = $conn->real_escape_string($_POST['message']);
        
        $conn->query("UPDATE reminders SET 
                      customer_id = $customer_id, 
                      reminder_date = '$reminder_date', 
                      type = '$type', 
                      message = '$message' 
                      WHERE id = $reminder_id AND user_id = $user_id");
        
        if ($conn->affected_rows >= 0) {
            logActivity($user_id, 'edit_reminder', "Edited reminder ID: $reminder_id");
            echo json_encode(['success' => true, 'message' => 'Reminder updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update reminder']);
        }
        exit();
    }
    
    // Get Reminder Details
    if ($action === 'get_reminder') {
        $reminder_id = (int)$_POST['reminder_id'];
        $result = $conn->query("SELECT * FROM reminders WHERE id = $reminder_id AND user_id = $user_id");
        $reminder = $result->fetch_assoc();
        echo json_encode(['success' => true, 'data' => $reminder]);
        exit();
    }
    
    // Delete Reminder
    if ($action === 'delete_reminder') {
        $reminder_id = (int)$_POST['reminder_id'];
        
        $conn->query("DELETE FROM reminders WHERE id = $reminder_id AND user_id = $user_id");
        
        if ($conn->affected_rows > 0) {
            logActivity($user_id, 'delete_reminder', "Deleted reminder ID: $reminder_id");
            echo json_encode(['success' => true, 'message' => 'Reminder deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete reminder']);
        }
        exit();
    }
        // Send Reminder Now
    if ($action === 'send_reminder_now') {
        $reminder_id = (int)$_POST['reminder_id'];
        
        // Get reminder details with customer info
        $result = $conn->query("
            SELECT r.*, c.name as customer_name, c.email, c.phone 
            FROM reminders r 
            JOIN customers c ON r.customer_id = c.id 
            WHERE r.id = $reminder_id AND r.user_id = $user_id
        ");
        $reminder = $result->fetch_assoc();
        
        if (!$reminder) {
            echo json_encode(['success' => false, 'message' => 'Reminder not found']);
            exit();
        }
        
        // Replace placeholders in message
        $message = str_replace(
            ['[customer_name]', '[reminder_date]'],
            [$reminder['customer_name'], date('M d, Y', strtotime($reminder['reminder_date']))],
            $reminder['message']
        );
        
        $sent = false;
        $error_message = '';
        
        // Send based on type
        if ($reminder['type'] == 'email') {
            if (!empty($reminder['email'])) {
                // Make sure mail_config is loaded
                if (!function_exists('sendReminderEmail')) {
                    require_once __DIR__ . '/includes/mail_config.php';
                }
                $sent = sendReminderEmail($reminder['email'], $reminder['customer_name'], $message);
                if (!$sent) $error_message = 'Failed to send email. Check email configuration.';
            } else {
                $error_message = 'Customer has no email address';
            }
        } elseif ($reminder['type'] == 'whatsapp') {
            if (!empty($reminder['phone'])) {
                $sent = sendWhatsAppReminder($reminder['phone'], $message);
                if (!$sent) $error_message = 'Failed to send WhatsApp message';
            } else {
                $error_message = 'Customer has no phone number';
            }
        } elseif ($reminder['type'] == 'sms') {
            if (!empty($reminder['phone'])) {
                $sent = sendSMSReminder($reminder['phone'], $message);
                if (!$sent) $error_message = 'Failed to send SMS';
            } else {
                $error_message = 'Customer has no phone number';
            }
        }
        
        if ($sent) {
            // Update reminder status
            $conn->query("UPDATE reminders SET status = 'sent', sent_at = NOW() WHERE id = $reminder_id");
            logActivity($user_id, 'send_reminder', "Sent reminder to customer: {$reminder['customer_name']}");
            echo json_encode(['success' => true, 'message' => 'Reminder sent successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => $error_message ?: 'Failed to send reminder. Please try again.']);
        }
        exit();
    }
}

// Get all reminders with customer names
$reminders = $conn->query("
    SELECT r.*, c.name as customer_name, c.email, c.phone 
    FROM reminders r 
    JOIN customers c ON r.customer_id = c.id 
    WHERE r.user_id = $user_id 
    ORDER BY r.reminder_date ASC
");

// Get customers for dropdown
$customers = $conn->query("SELECT id, name, email, phone FROM customers WHERE user_id = $user_id ORDER BY name");

$page_title = 'Reminders';
include __DIR__ . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Payment Reminders</h1>
            <p class="text-gray-600 mt-1">Schedule and manage payment reminders for your customers</p>
        </div>
        <button onclick="openReminderModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition flex items-center gap-2">
            <i class="fas fa-plus"></i> Add Reminder
        </button>
    </div>
    
    <!-- Reminders List -->
    <div class="grid grid-cols-1 gap-4">
        <?php if ($reminders->num_rows == 0): ?>
            <div class="bg-white rounded-xl shadow-md p-12 text-center">
                <i class="fas fa-bell-slash text-5xl text-gray-300 mb-4"></i>
                <h3 class="text-lg font-semibold text-gray-600">No Reminders</h3>
                <p class="text-gray-500 mt-2">Create reminders to notify customers about payments</p>
                <button onclick="openReminderModal()" class="mt-4 text-blue-600 hover:text-blue-800">Create your first reminder</button>
            </div>
        <?php else: ?>
            <?php while ($reminder = $reminders->fetch_assoc()): ?>
                <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition <?php echo $reminder['status'] == 'pending' ? 'border-l-4 border-yellow-500' : 'border-l-4 border-green-500'; ?>">
                    <div class="p-6">
                        <div class="flex justify-between items-start">
                            <div class="flex items-start space-x-4 flex-1">
                                <div class="w-12 h-12 rounded-full flex items-center justify-center <?php echo $reminder['type'] == 'email' ? 'bg-blue-100' : ($reminder['type'] == 'whatsapp' ? 'bg-green-100' : 'bg-purple-100'); ?>">
                                    <i class="fas <?php echo $reminder['type'] == 'email' ? 'fa-envelope text-blue-600' : ($reminder['type'] == 'whatsapp' ? 'fa-whatsapp text-green-600' : 'fa-sms text-purple-600'); ?> text-xl"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="flex flex-wrap justify-between items-start gap-2">
                                        <div>
                                            <h3 class="font-semibold text-gray-800 text-lg"><?php echo htmlspecialchars($reminder['customer_name']); ?></h3>
                                            <p class="text-gray-600 mt-1"><?php echo nl2br(htmlspecialchars($reminder['message'])); ?></p>
                                            <div class="flex flex-wrap items-center gap-4 mt-3">
                                                <span class="text-sm text-gray-500">
                                                    <i class="fas fa-calendar mr-1"></i> <?php echo date('M d, Y', strtotime($reminder['reminder_date'])); ?>
                                                </span>
                                                <span class="text-sm text-gray-500">
                                                    <i class="fas fa-bell mr-1"></i> <?php echo ucfirst($reminder['type']); ?> Reminder
                                                </span>
                                                <?php if ($reminder['status'] == 'pending'): ?>
                                                    <span class="text-xs text-yellow-600">
                                                        <i class="fas fa-clock"></i> Not sent yet
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-xs text-green-600">
                                                        <i class="fas fa-check-circle"></i> Sent on <?php echo date('M d, Y', strtotime($reminder['sent_at'] ?? $reminder['created_at'])); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <?php if ($reminder['type'] == 'email' && empty($reminder['email'])): ?>
                                                <span class="text-xs text-red-500">No email address</span>
                                            <?php elseif (($reminder['type'] == 'whatsapp' || $reminder['type'] == 'sms') && empty($reminder['phone'])): ?>
                                                <span class="text-xs text-red-500">No phone number</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex space-x-2 ml-4">
                                <?php if ($reminder['status'] == 'pending'): ?>
                                    <button onclick="sendReminderNow(<?php echo $reminder['id']; ?>)" 
                                            class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600 transition text-sm flex items-center gap-1"
                                            title="Send Now">
                                        <i class="fas fa-paper-plane"></i> Send Now
                                    </button>
                                <?php endif; ?>
                                <button onclick="editReminder(<?php echo $reminder['id']; ?>)" 
                                        class="text-blue-600 hover:text-blue-800 p-1" title="Edit Reminder">
                                    <i class="fas fa-edit text-lg"></i>
                                </button>
                                <button onclick="deleteReminder(<?php echo $reminder['id']; ?>)" 
                                        class="text-red-600 hover:text-red-800 p-1" title="Delete Reminder">
                                    <i class="fas fa-trash text-lg"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Reminder Modal -->
<div id="reminderModal" class="modal">
    <div class="modal-content max-w-md">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 id="reminderModalTitle" class="text-xl font-semibold">Add Payment Reminder</h3>
                <button onclick="closeReminderModal()" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <form id="reminderForm">
                <input type="hidden" name="reminder_id" id="reminderId">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Customer *</label>
                    <select name="customer_id" id="reminderCustomer" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required onchange="updateCustomerContact()">
                        <option value="">Select Customer</option>
                        <?php 
                        $customers->data_seek(0);
                        while ($c = $customers->fetch_assoc()): ?>
                            <option value="<?php echo $c['id']; ?>" data-email="<?php echo $c['email']; ?>" data-phone="<?php echo $c['phone']; ?>">
                                <?php echo htmlspecialchars($c['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div id="contactWarning" class="hidden mb-4 p-3 bg-yellow-100 text-yellow-800 rounded-lg text-sm">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <span id="warningMessage"></span>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Reminder Date *</label>
                    <input type="date" name="reminder_date" id="reminderDate" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Reminder Type *</label>
                    <select name="type" id="reminderType" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required onchange="checkContactAvailability()">
                        <option value="email">📧 Email Reminder</option>
                        <option value="whatsapp">💬 WhatsApp Reminder</option>
                        <option value="sms">📱 SMS Reminder</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Message *</label>
                    <textarea name="message" id="reminderMessage" rows="4" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500" 
                              placeholder="Example: Dear [customer_name], your payment reminder is scheduled for [reminder_date]. Please ensure timely payment." required></textarea>
                    <p class="text-xs text-gray-500 mt-1">💡 Available placeholders: <code>[customer_name]</code>, <code>[reminder_date]</code></p>
                </div>
                <div class="flex space-x-3">
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700">Save Reminder</button>
                    <button type="button" onclick="closeReminderModal()" class="flex-1 bg-gray-300 text-gray-700 py-2 rounded-lg hover:bg-gray-400">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Store customer contact info
let customersData = {};

<?php
$customers->data_seek(0);
while ($c = $customers->fetch_assoc()): ?>
customersData[<?php echo $c['id']; ?>] = {
    email: '<?php echo addslashes($c['email']); ?>',
    phone: '<?php echo addslashes($c['phone']); ?>'
};
<?php endwhile; ?>

function updateCustomerContact() {
    let customerId = document.getElementById('reminderCustomer').value;
    let type = document.getElementById('reminderType').value;
    
    if (customerId && customersData[customerId]) {
        let hasContact = false;
        let warningMsg = '';
        
        if (type === 'email') {
            hasContact = customersData[customerId].email;
            warningMsg = 'This customer has no email address. Please add an email to send reminders.';
        } else if (type === 'whatsapp' || type === 'sms') {
            hasContact = customersData[customerId].phone;
            warningMsg = 'This customer has no phone number. Please add a phone number to send reminders.';
        }
        
        let warningDiv = document.getElementById('contactWarning');
        if (!hasContact) {
            warningDiv.classList.remove('hidden');
            document.getElementById('warningMessage').textContent = warningMsg;
        } else {
            warningDiv.classList.add('hidden');
        }
    }
}

function checkContactAvailability() {
    updateCustomerContact();
}

function openReminderModal() {
    document.getElementById('reminderForm').reset();
    document.getElementById('reminderId').value = '';
    document.getElementById('reminderModalTitle').textContent = 'Add Payment Reminder';
    document.getElementById('contactWarning').classList.add('hidden');
    openModal('reminderModal');
}

function closeReminderModal() {
    closeModal('reminderModal');
}

function editReminder(reminderId) {
    fetch('', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_reminder&reminder_id=' + reminderId
    })
    .then(response => response.json())
    .then(result => {
        if (result.success && result.data) {
            let reminder = result.data;
            document.getElementById('reminderId').value = reminder.id;
            document.getElementById('reminderCustomer').value = reminder.customer_id;
            document.getElementById('reminderDate').value = reminder.reminder_date;
            document.getElementById('reminderType').value = reminder.type;
            document.getElementById('reminderMessage').value = reminder.message;
            document.getElementById('reminderModalTitle').textContent = 'Edit Payment Reminder';
            updateCustomerContact();
            openModal('reminderModal');
        }
    });
}

function deleteReminder(reminderId) {
    confirmAction('Are you sure you want to delete this reminder? This action cannot be undone.', () => {
        fetch('', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=delete_reminder&reminder_id=' + reminderId
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showToast(result.message, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(result.message, 'error');
            }
        });
    });
}

function sendReminderNow(reminderId) {
    confirmAction('Send this reminder now? The customer will receive the notification immediately.', () => {
        // Show loading on button
        let btn = event.target.closest('button');
        let originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        
        fetch('', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=send_reminder_now&reminder_id=' + reminderId
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showToast(result.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(result.message, 'error');
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        })
        .catch(error => {
            showToast('Error sending reminder', 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    });
}

document.getElementById('reminderForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Validate contact info before saving
    let customerId = document.getElementById('reminderCustomer').value;
    let type = document.getElementById('reminderType').value;
    
    if (customerId && customersData[customerId]) {
        if (type === 'email' && !customersData[customerId].email) {
            showToast('This customer has no email address. Please add an email first.', 'error');
            return;
        }
        if ((type === 'whatsapp' || type === 'sms') && !customersData[customerId].phone) {
            showToast('This customer has no phone number. Please add a phone number first.', 'error');
            return;
        }
    }
    
    let formData = new FormData(this);
    let reminderId = document.getElementById('reminderId').value;
    let action = reminderId ? 'edit_reminder' : 'add_reminder';
    formData.append('action', action);
    
    let submitBtn = this.querySelector('button[type="submit"]');
    let originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    
    fetch('', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast(result.message, 'success');
            closeReminderModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(result.message, 'error');
        }
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    })
    .catch(error => {
        showToast('Error saving reminder', 'error');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});

function openModal(modalId) {
    document.getElementById(modalId).classList.add('show');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>