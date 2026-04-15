// Customers page specific JavaScript

let currentCustomerId = null;
let currentSearchTerm = '';

document.addEventListener('DOMContentLoaded', function() {
    setupCustomerEventListeners();
    loadCustomers();
});

function setupCustomerEventListeners() {
    // Search input with debounce
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        let typingTimer;
        searchInput.addEventListener('keyup', function() {
            clearTimeout(typingTimer);
            typingTimer = setTimeout(() => {
                currentSearchTerm = this.value;
                loadCustomers(this.value);
            }, 300);
        });
        
        // Add clear button
        const clearBtn = document.getElementById('clearSearch');
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                searchInput.value = '';
                currentSearchTerm = '';
                loadCustomers();
            });
        }
    }
    
    // Add customer form
    const addCustomerForm = document.getElementById('addCustomerForm');
    if (addCustomerForm) {
        addCustomerForm.addEventListener('submit', handleAddCustomer);
    }
    
    // Add debt form
    const addDebtForm = document.getElementById('addDebtForm');
    if (addDebtForm) {
        addDebtForm.addEventListener('submit', handleAddDebt);
    }
    
    // Mark as paid form
    const markAsPaidForm = document.getElementById('markAsPaidForm');
    if (markAsPaidForm) {
        markAsPaidForm.addEventListener('submit', handleMarkAsPaid);
    }
}

function loadCustomers(search = '') {
    showLoading('customersTable');
    
    let url = '/paytrack/api/customers.php';
    if (search) {
        url += '?search=' + encodeURIComponent(search);
    }
    
    fetch(url, {
        method: 'GET',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderCustomersTable(data.data, search);
        } else {
            showToast('Failed to load customers', 'error');
        }
        hideLoading('customersTable');
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error loading customers', 'error');
        hideLoading('customersTable');
    });
}

function renderCustomersTable(customers, searchTerm) {
    const container = document.getElementById('customersTableBody');
    if (!container) return;
    
    if (!customers || customers.length === 0) {
        container.innerHTML = `
            <tr>
                <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                    <i class="fas fa-users text-4xl mb-2"></i>
                    <p>No customers found</p>
                    ${searchTerm ? '<p class="text-sm mt-1">Try a different search term</p>' : ''}
                    <button onclick="openAddCustomerModal()" class="mt-2 text-blue-600 hover:text-blue-800">Add your first customer</button>
                </td>
            </tr>
        `;
        return;
    }
    
    let html = '';
    customers.forEach(customer => {
        // Highlight search term in customer name
        let displayName = customer.name;
        if (searchTerm) {
            displayName = highlightSearchTerm(customer.name, searchTerm);
        }
        
        const statusBadge = customer.total_debt > 0 
            ? '<span class="badge badge-danger">Has Debt</span>'
            : '<span class="badge badge-success">No Debt</span>';
        
        html += `
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <div>
                        <p class="font-medium text-gray-800">${displayName}</p>
                        <p class="text-xs text-gray-500">ID: #${customer.id}</p>
                    </div>
                </td>
                <td class="px-6 py-4">
                    ${customer.email ? `<p class="text-sm"><i class="fas fa-envelope text-gray-400 mr-1"></i> ${escapeHtml(customer.email)}</p>` : ''}
                    ${customer.phone ? `<p class="text-sm"><i class="fas fa-phone text-gray-400 mr-1"></i> ${escapeHtml(customer.phone)}</p>` : ''}
                </td>
                <td class="px-6 py-4">
                    <span class="font-semibold ${customer.total_debt > 0 ? 'text-red-600' : 'text-green-600'}">
                        ${formatCurrency(customer.total_debt)}
                    </span>
                </td>
                <td class="px-6 py-4">
                    ${statusBadge}
                </td>
                <td class="px-6 py-4">
                    <div class="flex space-x-2">
                        <button onclick="viewCustomer(${customer.id})" class="text-blue-600 hover:text-blue-800" data-tooltip="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="openAddDebtModal(${customer.id}, '${escapeHtml(customer.name)}')" class="text-green-600 hover:text-green-800" data-tooltip="Add Debt">
                            <i class="fas fa-plus-circle"></i>
                        </button>
                        ${customer.total_debt > 0 ? `
                        <button onclick="openMarkAsPaidModal(${customer.id}, '${escapeHtml(customer.name)}', ${customer.total_debt})" class="text-purple-600 hover:text-purple-800" data-tooltip="Mark as Paid">
                            <i class="fas fa-check-double"></i>
                        </button>
                        ` : ''}
                        <button onclick="deleteCustomer(${customer.id})" class="text-red-600 hover:text-red-800" data-tooltip="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    container.innerHTML = html;
    initializeTooltips();
}

function handleAddCustomer(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = {
        action: 'add',
        name: formData.get('name'),
        email: formData.get('email'),
        phone: formData.get('phone'),
        address: formData.get('address'),
        notes: formData.get('notes')
    };
    
    showLoading('addCustomerBtn');
    
    fetch('/paytrack/api/customers.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast(result.message, 'success');
            closeModal('addCustomerModal');
            e.target.reset();
            loadCustomers(currentSearchTerm);
        } else {
            showToast(result.message, 'error');
        }
        hideLoading('addCustomerBtn');
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error adding customer', 'error');
        hideLoading('addCustomerBtn');
    });
}

function handleAddDebt(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = {
        action: 'add',
        customer_id: parseInt(formData.get('customer_id')),
        amount: parseFloat(formData.get('amount')),
        description: formData.get('description'),
        due_date: formData.get('due_date')
    };
    
    showLoading('addDebtBtn');
    
    fetch('/paytrack/api/debts.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast(result.message, 'success');
            closeModal('addDebtModal');
            e.target.reset();
            loadCustomers(currentSearchTerm);
        } else {
            showToast(result.message, 'error');
        }
        hideLoading('addDebtBtn');
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error adding debt', 'error');
        hideLoading('addDebtBtn');
    });
}

function handleMarkAsPaid(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const debtId = formData.get('debt_id');
    const amount = parseFloat(formData.get('amount'));
    
    confirmAction(`Are you sure you want to mark this debt as paid? Amount: ${formatCurrency(amount)}`, () => {
        showLoading('markAsPaidBtn');
        
        fetch('/paytrack/api/payments.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'add',
                debt_id: parseInt(debtId),
                amount: amount,
                payment_method: 'cash',
                payment_date: new Date().toISOString().split('T')[0]
            })
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showToast(result.message, 'success');
                closeModal('markAsPaidModal');
                loadCustomers(currentSearchTerm);
            } else {
                showToast(result.message, 'error');
            }
            hideLoading('markAsPaidBtn');
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error recording payment', 'error');
            hideLoading('markAsPaidBtn');
        });
    });
}

function openAddCustomerModal() {
    openModal('addCustomerModal');
}

function openAddDebtModal(customerId, customerName) {
    document.getElementById('debtCustomerId').value = customerId;
    document.getElementById('customerName').textContent = customerName;
    openModal('addDebtModal');
}

function openMarkAsPaidModal(customerId, customerName, totalDebt) {
    // First get all unpaid debts for this customer
    fetch(`/paytrack/api/debts.php?customer_id=${customerId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const unpaidDebts = data.data.filter(d => d.status !== 'paid');
                
                if (unpaidDebts.length === 0) {
                    showToast('No unpaid debts for this customer', 'info');
                    return;
                }
                
                // Create select options for debts
                const debtSelect = document.getElementById('markAsPaidDebtId');
                if (debtSelect) {
                    debtSelect.innerHTML = '<option value="">Select a debt...</option>';
                    unpaidDebts.forEach(debt => {
                        const remaining = debt.amount - debt.amount_paid;
                        debtSelect.innerHTML += `
                            <option value="${debt.id}" data-amount="${remaining}">
                                ${debt.invoice_number} - ${formatCurrency(remaining)} (Due: ${debt.due_date || 'N/A'})
                            </option>
                        `;
                    });
                }
                
                document.getElementById('markAsPaidCustomerName').textContent = customerName;
                openModal('markAsPaidModal');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error loading debts', 'error');
        });
}

function updatePaymentAmount() {
    const debtSelect = document.getElementById('markAsPaidDebtId');
    const selectedOption = debtSelect.options[debtSelect.selectedIndex];
    const amount = selectedOption.getAttribute('data-amount');
    const amountInput = document.getElementById('markAsPaidAmount');
    if (amountInput && amount) {
        amountInput.value = amount;
        amountInput.readOnly = true;
    }
}

function viewCustomer(id) {
    window.location.href = `customer_detail.php?id=${id}`;
}

function deleteCustomer(id) {
    confirmAction('Are you sure you want to delete this customer? This will also delete all associated debts and payments.', () => {
        fetch('/paytrack/api/customers.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id: id })
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showToast(result.message, 'success');
                loadCustomers(currentSearchTerm);
            } else {
                showToast(result.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error deleting customer', 'error');
        });
    });
}

function exportCustomers() {
    fetch('/paytrack/api/customers.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const exportData = data.data.map(c => ({
                    'ID': c.id,
                    'Name': c.name,
                    'Email': c.email,
                    'Phone': c.phone,
                    'Total Debt': c.total_debt,
                    'Created At': c.created_at
                }));
                exportToCSV(exportData, `customers_${new Date().toISOString().split('T')[0]}.csv`);
            }
        });
}