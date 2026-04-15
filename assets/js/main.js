// Main JavaScript for PayTrack

// Global variables
let currentUser = null;
let apiBaseUrl = '/paytrack/api/';

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
    setupEventListeners();
    loadUserData();
    initTheme();
});

function initializeApp() {
    setupCSRF();
    initializeTooltips();
    checkNotifications();
}

function initTheme() {
    const savedTheme = localStorage.getItem('theme') || 
                       document.documentElement.getAttribute('data-theme') || 
                       'light';
    
    if (savedTheme === 'dark') {
        enableDarkMode();
    } else {
        enableLightMode();
    }
    
    // Add theme toggle button to sidebar if not exists
    addThemeToggle();
}

function enableDarkMode() {
    document.documentElement.setAttribute('data-theme', 'dark');
    localStorage.setItem('theme', 'dark');
    
    // Update theme in database if logged in
    if (currentUser) {
        fetch(apiBaseUrl + 'settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'theme', theme: 'dark' })
        }).catch(console.error);
    }
    
    // Update toggle icon
    const toggleIcon = document.getElementById('themeToggleIcon');
    if (toggleIcon) {
        toggleIcon.className = 'fas fa-moon';
    }
}

function enableLightMode() {
    document.documentElement.setAttribute('data-theme', 'light');
    localStorage.setItem('theme', 'light');
    
    // Update theme in database if logged in
    if (currentUser) {
        fetch(apiBaseUrl + 'settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'theme', theme: 'light' })
        }).catch(console.error);
    }
    
    // Update toggle icon
    const toggleIcon = document.getElementById('themeToggleIcon');
    if (toggleIcon) {
        toggleIcon.className = 'fas fa-sun';
    }
}

function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    if (currentTheme === 'dark') {
        enableLightMode();
        showToast('Light mode enabled', 'info');
    } else {
        enableDarkMode();
        showToast('Dark mode with neon effects enabled', 'success');
    }
}

function addThemeToggle() {
    const sidebarNav = document.querySelector('nav');
    if (sidebarNav && !document.getElementById('themeToggleBtn')) {
        const themeLi = document.createElement('li');
        themeLi.className = 'mt-auto';
        themeLi.innerHTML = `
            <button id="themeToggleBtn" class="nav-link w-full text-left" onclick="toggleTheme()">
                <i id="themeToggleIcon" class="fas ${document.documentElement.getAttribute('data-theme') === 'dark' ? 'fa-moon' : 'fa-sun'}"></i>
                <span>Toggle Theme</span>
            </button>
        `;
        sidebarNav.appendChild(themeLi);
    }
}

function setupCSRF() {
    const token = document.querySelector('meta[name="csrf-token"]');
    if (token) {
        window.csrfToken = token.content;
    }
}

function setupEventListeners() {
    // Close modals when clicking outside
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(this.id);
            }
        });
    });
    
    // Handle ESC key to close modals
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal.flex').forEach(modal => {
                closeModal(modal.id);
            });
        }
    });
}

function initializeTooltips() {
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

function showTooltip(e) {
    const tooltipText = e.target.getAttribute('data-tooltip');
    if (!tooltipText) return;
    
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = tooltipText;
    tooltip.style.position = 'absolute';
    tooltip.style.backgroundColor = 'var(--bg-tertiary)';
    tooltip.style.color = 'var(--text-primary)';
    tooltip.style.padding = '5px 10px';
    tooltip.style.borderRadius = '5px';
    tooltip.style.fontSize = '12px';
    tooltip.style.zIndex = '1000';
    tooltip.style.border = '1px solid var(--border-color)';
    
    const rect = e.target.getBoundingClientRect();
    tooltip.style.top = rect.top - 30 + 'px';
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    
    document.body.appendChild(tooltip);
    e.target._tooltip = tooltip;
}

function hideTooltip(e) {
    if (e.target._tooltip) {
        e.target._tooltip.remove();
        delete e.target._tooltip;
    }
}

function loadUserData() {
    fetch(apiBaseUrl + 'auth.php', {
        method: 'GET',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            currentUser = data.user;
            updateUserInterface();
            checkSubscriptionStatus();
        }
    })
    .catch(error => console.error('Error loading user data:', error));
}

function updateUserInterface() {
    const userNameElements = document.querySelectorAll('.user-name');
    userNameElements.forEach(el => {
        if (currentUser) {
            el.textContent = currentUser.full_name;
        }
    });
    
    // Show plan badge
    const planBadge = document.getElementById('userPlan');
    if (planBadge && currentUser) {
        planBadge.textContent = currentUser.plan_name + ' Plan';
        if (currentUser.plan_name === 'Pro') {
            planBadge.className = 'badge badge-info';
        } else {
            planBadge.className = 'badge badge-warning';
        }
    }
}

function checkSubscriptionStatus() {
    if (currentUser && currentUser.subscription_status !== 'active') {
        showToast(`Your subscription is ${currentUser.subscription_status}. Please upgrade to continue using all features.`, 'warning');
    }
}

function checkNotifications() {
    if (Notification.permission === 'granted') {
        fetchNotifications();
    } else if (Notification.permission !== 'denied') {
        Notification.requestPermission().then(permission => {
            if (permission === 'granted') {
                fetchNotifications();
            }
        });
    }
}

function fetchNotifications() {
    fetch(apiBaseUrl + 'notifications.php', {
        method: 'GET',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.notifications && data.notifications.length > 0) {
            showNotification(data.notifications[0]);
        }
    })
    .catch(error => console.error('Error fetching notifications:', error));
}

function showNotification(notification) {
    if (Notification.permission === 'granted') {
        new Notification(notification.title, {
            body: notification.message,
            icon: '/assets/images/logo.png'
        });
    }
}

// Utility Functions
function formatCurrency(amount) {
    return new Intl.NumberFormat('rw-RW', {
        style: 'currency',
        currency: 'RWF',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount);
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function formatDateTime(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <div class="flex items-center space-x-2">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function confirmAction(message, callback, options = {}) {
    // Create custom confirmation modal
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modal.innerHTML = `
        <div class="modal-content max-w-md w-full mx-4 p-6">
            <div class="text-center mb-4">
                <i class="fas fa-question-circle text-4xl text-warning mb-2"></i>
                <h3 class="text-lg font-semibold mb-2">Confirm Action</h3>
                <p class="text-secondary">${message}</p>
            </div>
            <div class="flex space-x-3">
                <button id="confirmYes" class="flex-1 btn btn-primary">Yes, Confirm</button>
                <button id="confirmNo" class="flex-1 btn bg-gray-500 text-white hover:bg-gray-600">Cancel</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    document.getElementById('confirmYes').addEventListener('click', () => {
        modal.remove();
        callback();
    });
    
    document.getElementById('confirmNo').addEventListener('click', () => {
        modal.remove();
        if (options.onCancel) options.onCancel();
    });
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Copied to clipboard!', 'success');
    }).catch(err => {
        console.error('Failed to copy:', err);
        showToast('Failed to copy', 'error');
    });
}

// Modal Management
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        // Add animation
        const content = modal.querySelector('.modal-content');
        if (content) {
            content.style.animation = 'fadeIn 0.3s ease-out';
        }
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

// Loading States
function showLoading(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.disabled = true;
        const originalHtml = element.innerHTML;
        element.dataset.originalHtml = originalHtml;
        element.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Loading...';
    }
}

function hideLoading(elementId) {
    const element = document.getElementById(elementId);
    if (element && element.dataset.originalHtml) {
        element.innerHTML = element.dataset.originalHtml;
        element.disabled = false;
        delete element.dataset.originalHtml;
    }
}

// Export functionality
function exportToCSV(data, filename) {
    if (!data || data.length === 0) {
        showToast('No data to export', 'error');
        return;
    }
    
    const headers = Object.keys(data[0]);
    const csvRows = [headers.join(',')];
    
    for (const row of data) {
        const values = headers.map(header => {
            let value = row[header] || '';
            if (typeof value === 'string') {
                value = value.replace(/"/g, '""');
            }
            return `"${value}"`;
        });
        csvRows.push(values.join(','));
    }
    
    const blob = new Blob([csvRows.join('\n')], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    window.URL.revokeObjectURL(url);
    showToast('Export completed successfully', 'success');
}

// Search highlight function
function highlightSearchTerm(text, searchTerm) {
    if (!searchTerm || !text) return text;
    const regex = new RegExp(`(${searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
    return text.replace(regex, '<span class="search-highlight">$1</span>');
}

// PDF Generation
function generatePDF(elementId, filename) {
    showToast('Generating PDF...', 'info');
    
    // Clone the element to avoid affecting the original
    const element = document.getElementById(elementId);
    if (!element) {
        showToast('Element not found', 'error');
        return;
    }
    
    const clone = element.cloneNode(true);
    clone.style.position = 'absolute';
    clone.style.left = '-9999px';
    clone.style.top = '-9999px';
    document.body.appendChild(clone);
    
    const opt = {
        margin: [0.5, 0.5, 0.5, 0.5],
        filename: filename,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
    };
    
    html2pdf().set(opt).from(clone).save().then(() => {
        document.body.removeChild(clone);
        showToast('PDF generated successfully', 'success');
    }).catch(error => {
        document.body.removeChild(clone);
        console.error('PDF generation error:', error);
        showToast('Failed to generate PDF', 'error');
    });
}