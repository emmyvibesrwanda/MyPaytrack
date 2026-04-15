<?php
require_once __DIR__ . '/includes/auth.php';
requireAuth();
require_once __DIR__ . '/includes/functions.php';

$conn = getDB();
$user_id = $_SESSION['user_id'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    
    // Add Customer
    if ($action === 'add_customer') {
        $name = $conn->real_escape_string($_POST['name']);
        $email = $conn->real_escape_string($_POST['email'] ?? '');
        $phone = $conn->real_escape_string($_POST['phone'] ?? '');
        $address = $conn->real_escape_string($_POST['address'] ?? '');
        
        $conn->query("INSERT INTO customers (user_id, name, email, phone, address) 
                      VALUES ($user_id, '$name', '$email', '$phone', '$address')");
        
        if ($conn->affected_rows > 0) {
            $customer_id = $conn->insert_id;
            logActivity($user_id, 'add_customer', "Added customer: $name");
            echo json_encode(['success' => true, 'message' => 'Customer added successfully', 'customer_id' => $customer_id, 'customer_name' => $name]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add customer']);
        }
        exit();
    }
    
    // Add Debt/Invoice
    if ($action === 'add_debt') {
        $customer_id = (int)$_POST['customer_id'];
        $amount = (float)$_POST['amount'];
        $description = $conn->real_escape_string($_POST['description'] ?? '');
        $due_date = $conn->real_escape_string($_POST['due_date'] ?? '');
        $invoice_number = generateInvoiceNumber();
        
        $conn->query("INSERT INTO debts (user_id, customer_id, amount, description, due_date, invoice_number) 
                      VALUES ($user_id, $customer_id, $amount, '$description', '$due_date', '$invoice_number')");
        
        if ($conn->affected_rows > 0) {
            $conn->query("UPDATE customers SET total_debt = total_debt + $amount WHERE id = $customer_id");
            logActivity($user_id, 'add_debt', "Added debt of $amount for customer ID: $customer_id");
            echo json_encode(['success' => true, 'message' => 'Debt added successfully', 'invoice_number' => $invoice_number]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add debt']);
        }
        exit();
    }
    
    // Mark as Paid
    if ($action === 'mark_paid') {
        $debt_id = (int)$_POST['debt_id'];
        $amount = (float)$_POST['amount'];
        
        $debt = $conn->query("SELECT customer_id, amount, amount_paid FROM debts WHERE id = $debt_id AND user_id = $user_id")->fetch_assoc();
        
        if ($debt) {
            $new_paid = $debt['amount_paid'] + $amount;
            $status = $new_paid >= $debt['amount'] ? 'paid' : 'partial';
            
            $conn->query("UPDATE debts SET amount_paid = $new_paid, status = '$status' WHERE id = $debt_id");
            $conn->query("INSERT INTO payments (debt_id, user_id, amount, payment_date) VALUES ($debt_id, $user_id, $amount, CURDATE())");
            $conn->query("UPDATE customers SET total_debt = total_debt - $amount WHERE id = {$debt['customer_id']}");
            
            logActivity($user_id, 'mark_paid', "Recorded payment of $amount for debt ID: $debt_id");
            echo json_encode(['success' => true, 'message' => 'Payment recorded successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Debt not found']);
        }
        exit();
    }
    
    // Delete Customer
    if ($action === 'delete_customer') {
        $customer_id = (int)$_POST['customer_id'];
        $conn->query("DELETE FROM customers WHERE id = $customer_id AND user_id = $user_id");
        
        if ($conn->affected_rows > 0) {
            logActivity($user_id, 'delete_customer', "Deleted customer ID: $customer_id");
            echo json_encode(['success' => true, 'message' => 'Customer deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete customer']);
        }
        exit();
    }
    
    // Get Unpaid Debts for Customer
    if ($action === 'get_unpaid_debts') {
        $customer_id = (int)$_POST['customer_id'];
        $debts = $conn->query("SELECT id, invoice_number, amount, amount_paid, (amount - amount_paid) as remaining 
                               FROM debts WHERE customer_id = $customer_id AND user_id = $user_id AND status != 'paid'");
        $data = [];
        while ($row = $debts->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $data]);
        exit();
    }
}

// Get all customers
$search = $_GET['search'] ?? '';
if ($search) {
    $search_term = $conn->real_escape_string($search);
    $customers = $conn->query("SELECT * FROM customers WHERE user_id = $user_id 
                               AND (name LIKE '%$search_term%' OR email LIKE '%$search_term%' OR phone LIKE '%$search_term%') 
                               ORDER BY name");
} else {
    $customers = $conn->query("SELECT * FROM customers WHERE user_id = $user_id ORDER BY name");
}

$page_title = 'Customers';
include __DIR__ . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Customers</h1>
            <p class="text-gray-600 mt-1">Manage your customers and their debts</p>
        </div>
        <button onclick="openAddCustomerModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition flex items-center gap-2">
            <i class="fas fa-plus"></i> Add Customer
        </button>
    </div>
    
    <!-- Search Bar -->
    <div class="mb-6">
        <div class="relative max-w-md">
            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
            <input type="text" id="searchInput" placeholder="Search customers by name, email or phone..." 
                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
            <button id="clearSearch" class="absolute right-3 top-2 text-gray-400 hover:text-gray-600 hidden">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    
    <!-- Customers Table -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Owed</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100" id="customersTable">
                    <?php if ($customers->num_rows == 0): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-users text-4xl mb-2"></i>
                                <p>No customers found</p>
                                <button onclick="openAddCustomerModal()" class="mt-2 text-blue-600 hover:text-blue-800">Add your first customer</button>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php while ($customer = $customers->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50 customer-row" data-customer-name="<?php echo htmlspecialchars($customer['name']); ?>">
                                <td class="px-6 py-4">
                                    <p class="font-medium text-gray-800 customer-name"><?php echo htmlspecialchars($customer['name']); ?></p>
                                    <p class="text-xs text-gray-500">ID: #<?php echo $customer['id']; ?></p>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($customer['email']): ?>
                                        <p class="text-sm"><i class="fas fa-envelope text-gray-400 mr-1"></i> <?php echo htmlspecialchars($customer['email']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($customer['phone']): ?>
                                        <p class="text-sm"><i class="fas fa-phone text-gray-400 mr-1"></i> <?php echo htmlspecialchars($customer['phone']); ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-semibold <?php echo $customer['total_debt'] > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                                        <?php echo formatCurrency($customer['total_debt']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($customer['total_debt'] > 0): ?>
                                        <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs">Has Debt</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">No Debt</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex space-x-2">
                                        <button onclick="addDebt(<?php echo $customer['id']; ?>, '<?php echo htmlspecialchars($customer['name']); ?>')" 
                                                class="text-green-600 hover:text-green-800" title="Add Debt">
                                            <i class="fas fa-plus-circle"></i>
                                        </button>
                                        <?php if ($customer['total_debt'] > 0): ?>
                                            <button onclick="markAsPaid(<?php echo $customer['id']; ?>, '<?php echo htmlspecialchars($customer['name']); ?>')" 
                                                    class="text-purple-600 hover:text-purple-800" title="Mark as Paid">
                                                <i class="fas fa-check-double"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button onclick="deleteCustomer(<?php echo $customer['id']; ?>)" 
                                                class="text-red-600 hover:text-red-800" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Customer Modal -->
<div id="addCustomerModal" class="modal">
    <div class="modal-content max-w-md">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold">Add New Customer</h3>
                <button onclick="closeModal('addCustomerModal')" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <form id="addCustomerForm">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Full Name *</label>
                    <input type="text" name="name" id="customerName" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                    <input type="email" name="email" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Phone</label>
                    <input type="tel" name="phone" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Address</label>
                    <textarea name="address" rows="2" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"></textarea>
                </div>
                <div class="flex space-x-3">
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700">Add Customer</button>
                    <button type="button" onclick="closeModal('addCustomerModal')" class="flex-1 bg-gray-300 text-gray-700 py-2 rounded-lg hover:bg-gray-400">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Debt Modal -->
<div id="addDebtModal" class="modal">
    <div class="modal-content max-w-md">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold">Add Debt for <span id="debtCustomerName" class="text-blue-600"></span></h3>
                <button onclick="closeModal('addDebtModal')" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <form id="addDebtForm">
                <input type="hidden" name="customer_id" id="debtCustomerId">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Amount (RWF) *</label>
                    <input type="number" name="amount" step="1" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500" required placeholder="Enter amount">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Description</label>
                    <textarea name="description" rows="2" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500" placeholder="Debt description"></textarea>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Due Date</label>
                    <input type="date" name="due_date" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                </div>
                <div class="flex space-x-3">
                    <button type="submit" class="flex-1 bg-green-600 text-white py-2 rounded-lg hover:bg-green-700">Add Debt</button>
                    <button type="button" onclick="closeModal('addDebtModal')" class="flex-1 bg-gray-300 text-gray-700 py-2 rounded-lg hover:bg-gray-400">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Mark as Paid Modal -->
<div id="markPaidModal" class="modal">
    <div class="modal-content max-w-md">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold">Mark Debt as Paid</h3>
                <button onclick="closeModal('markPaidModal')" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <form id="markPaidForm">
                <input type="hidden" name="debt_id" id="paidDebtId">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Select Debt</label>
                    <select id="debtSelect" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500" onchange="updatePaymentAmount()" required>
                        <option value="">Select a debt...</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Payment Amount (RWF)</label>
                    <input type="number" name="amount" id="paymentAmount" class="w-full px-3 py-2 border rounded-lg bg-gray-100" readonly>
                </div>
                <div class="flex space-x-3">
                    <button type="submit" class="flex-1 bg-purple-600 text-white py-2 rounded-lg hover:bg-purple-700">Confirm Payment</button>
                    <button type="button" onclick="closeModal('markPaidModal')" class="flex-1 bg-gray-300 text-gray-700 py-2 rounded-lg hover:bg-gray-400">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Success Modal - Ask to Add Debt -->
<div id="successModal" class="modal">
    <div class="modal-content max-w-md">
        <div class="p-6 text-center">
            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-check-circle text-green-600 text-2xl"></i>
            </div>
            <h3 class="text-xl font-semibold text-gray-800 mb-2">Customer Added Successfully!</h3>
            <p id="successMessage" class="text-gray-600 mb-6">Customer has been added to your database.</p>
            <div class="flex space-x-3">
                <button id="addDebtNowBtn" class="flex-1 bg-green-600 text-white py-2 rounded-lg hover:bg-green-700">
                    <i class="fas fa-plus-circle"></i> Add Debt Now
                </button>
                <button id="closeSuccessBtn" class="flex-1 bg-gray-300 text-gray-700 py-2 rounded-lg hover:bg-gray-400">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let lastAddedCustomerId = null;
let lastAddedCustomerName = null;

// Search functionality with clear button
const searchInput = document.getElementById('searchInput');
const clearSearch = document.getElementById('clearSearch');

searchInput.addEventListener('input', function() {
    let search = this.value.toLowerCase();
    let rows = document.querySelectorAll('.customer-row');
    let hasResults = false;
    
    if (search.length > 0) {
        clearSearch.classList.remove('hidden');
    } else {
        clearSearch.classList.add('hidden');
    }
    
    rows.forEach(row => {
        let customerName = row.getAttribute('data-customer-name') || '';
        if (customerName.toLowerCase().includes(search)) {
            row.style.display = '';
            hasResults = true;
        } else {
            row.style.display = 'none';
        }
    });
    
    let tableBody = document.getElementById('customersTable');
    let noResultRow = document.getElementById('noResultRow');
    
    if (!hasResults && rows.length > 0 && search.length > 0) {
        if (!noResultRow) {
            let tr = document.createElement('tr');
            tr.id = 'noResultRow';
            tr.innerHTML = '<td colspan="5" class="px-6 py-12 text-center text-gray-500"><i class="fas fa-search text-4xl mb-2"></i><p>No customers found matching "' + search + '"</p></td>';
            tableBody.appendChild(tr);
        }
    } else if (noResultRow) {
        noResultRow.remove();
    }
});

clearSearch.addEventListener('click', function() {
    searchInput.value = '';
    clearSearch.classList.add('hidden');
    searchInput.dispatchEvent(new Event('input'));
});

// Modal functions
function openModal(modalId) {
    document.getElementById(modalId).classList.add('show');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
}

function openAddCustomerModal() {
    document.getElementById('addCustomerForm').reset();
    openModal('addCustomerModal');
}

function addDebt(customerId, customerName) {
    document.getElementById('debtCustomerId').value = customerId;
    document.getElementById('debtCustomerName').textContent = customerName;
    document.getElementById('addDebtForm').reset();
    openModal('addDebtModal');
}

function markAsPaid(customerId, customerName) {
    // Load unpaid debts for this customer
    fetch('', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_unpaid_debts&customer_id=' + customerId
    })
    .then(response => response.json())
    .then(result => {
        if (result.success && result.data.length > 0) {
            let options = '<option value="">Select a debt...</option>';
            result.data.forEach(debt => {
                let remaining = debt.remaining;
                options += `<option value="${debt.id}" data-amount="${remaining}">${debt.invoice_number} - ${formatCurrency(remaining)}</option>`;
            });
            document.getElementById('debtSelect').innerHTML = options;
            openModal('markPaidModal');
        } else {
            showToast('No unpaid debts for this customer', 'info');
        }
    });
}

function updatePaymentAmount() {
    let select = document.getElementById('debtSelect');
    let selected = select.options[select.selectedIndex];
    let amount = selected.getAttribute('data-amount');
    document.getElementById('paymentAmount').value = amount;
    document.getElementById('paidDebtId').value = select.value;
}

function deleteCustomer(id) {
    confirmAction('Are you sure you want to delete this customer? All associated debts will also be deleted.', () => {
        fetch('', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=delete_customer&customer_id=' + id
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

// Add Customer Form Submission
document.getElementById('addCustomerForm').addEventListener('submit', function(e) {
    e.preventDefault();
    let formData = new FormData(this);
    formData.append('action', 'add_customer');
    
    let submitBtn = this.querySelector('button[type="submit"]');
    let originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    
    fetch('', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            // Store the new customer info
            lastAddedCustomerId = result.customer_id;
            lastAddedCustomerName = result.customer_name;
            
            // Show success modal with option to add debt
            document.getElementById('successMessage').innerHTML = `Customer <strong>${result.customer_name}</strong> has been added successfully. Would you like to add a debt for this customer?`;
            closeModal('addCustomerModal');
            openModal('successModal');
        } else {
            showToast(result.message, 'error');
        }
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    })
    .catch(error => {
        showToast('Error adding customer', 'error');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});

// Add Debt Form Submission
document.getElementById('addDebtForm').addEventListener('submit', function(e) {
    e.preventDefault();
    let formData = new FormData(this);
    formData.append('action', 'add_debt');
    
    let submitBtn = this.querySelector('button[type="submit"]');
    let originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    
    fetch('', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast(result.message, 'success');
            closeModal('addDebtModal');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(result.message, 'error');
        }
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    })
    .catch(error => {
        showToast('Error adding debt', 'error');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});

// Mark as Paid Form Submission
document.getElementById('markPaidForm').addEventListener('submit', function(e) {
    e.preventDefault();
    let debtId = document.getElementById('paidDebtId').value;
    let amount = document.getElementById('paymentAmount').value;
    
    if (!debtId) {
        showToast('Please select a debt', 'error');
        return;
    }
    
    confirmAction(`Confirm payment of ${formatCurrency(amount)}?`, () => {
        fetch('', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=mark_paid&debt_id=' + debtId + '&amount=' + amount
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showToast(result.message, 'success');
                closeModal('markPaidModal');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(result.message, 'error');
            }
        });
    });
});

// Success Modal Buttons
document.getElementById('addDebtNowBtn').addEventListener('click', function() {
    closeModal('successModal');
    if (lastAddedCustomerId && lastAddedCustomerName) {
        addDebt(lastAddedCustomerId, lastAddedCustomerName);
    }
});

document.getElementById('closeSuccessBtn').addEventListener('click', function() {
    closeModal('successModal');
    location.reload();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>