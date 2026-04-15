// Dashboard specific JavaScript

let paymentChart = null;
let revenueChart = null;

document.addEventListener('DOMContentLoaded', function() {
    loadDashboardData();
    setupAutoRefresh();
});

function loadDashboardData() {
    showLoading('dashboard-content');
    
    fetch('/paytrack/api/dashboard.php', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateStatistics(data.data.statistics);
            updateCharts(data.data);
            updateRecentActivity(data.data.recent_activity);
            updateRecentDebts(data.data.recent_debts);
        } else {
            showToast('Failed to load dashboard data', 'error');
        }
        hideLoading('dashboard-content');
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error loading dashboard', 'error');
        hideLoading('dashboard-content');
    });
}

function updateStatistics(stats) {
    // Update total customers
    const totalCustomersEl = document.getElementById('totalCustomers');
    if (totalCustomersEl) {
        totalCustomersEl.textContent = stats.total_customers.toLocaleString();
    }
    
    // Update unpaid debts
    const unpaidDebtsEl = document.getElementById('unpaidDebts');
    if (unpaidDebtsEl) {
        unpaidDebtsEl.textContent = stats.unpaid_debts.toLocaleString();
    }
    
    // Update total owed
    const totalOwedEl = document.getElementById('totalOwed');
    if (totalOwedEl) {
        totalOwedEl.textContent = formatCurrency(stats.total_owed);
    }
    
    // Update paid in 30 days
    const paid30DaysEl = document.getElementById('paid30Days');
    if (paid30DaysEl) {
        paid30DaysEl.textContent = formatCurrency(stats.total_paid_30days);
    }
    
    // Update overdue info
    const overdueCountEl = document.getElementById('overdueCount');
    if (overdueCountEl) {
        overdueCountEl.textContent = stats.overdue_count;
    }
    
    const overdueTotalEl = document.getElementById('overdueTotal');
    if (overdueTotalEl) {
        overdueTotalEl.textContent = formatCurrency(stats.overdue_total);
    }
}

function updateCharts(data) {
    // Payment Status Chart
    const paymentCtx = document.getElementById('paymentChart');
    if (paymentCtx) {
        if (paymentChart) {
            paymentChart.destroy();
        }
        
        paymentChart = new Chart(paymentCtx, {
            type: 'doughnut',
            data: {
                labels: ['Paid', 'Unpaid'],
                datasets: [{
                    data: [data.chart_data.paid, data.chart_data.unpaid],
                    backgroundColor: ['#10B981', '#F59E0B'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return `${label}: ${formatCurrency(value)} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart');
    if (revenueCtx && data.monthly_revenue && data.monthly_revenue.length > 0) {
        if (revenueChart) {
            revenueChart.destroy();
        }
        
        const months = data.monthly_revenue.map(item => item.month);
        const revenues = data.monthly_revenue.map(item => parseFloat(item.total));
        
        revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'Revenue',
                    data: revenues,
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Revenue: ${formatCurrency(context.raw)}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return formatCurrency(value);
                            }
                        }
                    }
                }
            }
        });
    }
}

function updateRecentActivity(activities) {
    const container = document.getElementById('recentActivity');
    if (!container) return;
    
    if (!activities || activities.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-8">No recent activity</p>';
        return;
    }
    
    let html = '';
    activities.forEach(activity => {
        html += `
            <div class="flex items-start space-x-3 pb-3 border-b">
                <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-info-circle text-gray-600 text-sm"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm text-gray-800">${escapeHtml(activity.description)}</p>
                    <p class="text-xs text-gray-500">${formatDateTime(activity.created_at)}</p>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function updateRecentDebts(debts) {
    const container = document.getElementById('recentDebts');
    if (!container) return;
    
    if (!debts || debts.length === 0) {
        container.innerHTML = '<tr><td colspan="4" class="px-6 py-12 text-center text-gray-500">No transactions found</td></tr>';
        return;
    }
    
    let html = '';
    debts.forEach(debt => {
        const remainingAmount = debt.amount - debt.amount_paid;
        html += `
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 text-sm text-gray-800">${escapeHtml(debt.customer_name)}</td>
                <td class="px-6 py-4 text-sm font-medium text-gray-800">${formatCurrency(remainingAmount)}</td>
                <td class="px-6 py-4">${getStatusBadgeHtml(debt.status)}</td>
                <td class="px-6 py-4 text-sm text-gray-600">${debt.due_date ? formatDate(debt.due_date) : 'N/A'}</td>
            </tr>
        `;
    });
    
    container.innerHTML = html;
}

function getStatusBadgeHtml(status) {
    const badges = {
        'paid': '<span class="badge badge-success">Paid</span>',
        'unpaid': '<span class="badge badge-danger">Unpaid</span>',
        'partial': '<span class="badge badge-warning">Partial</span>',
        'overdue': '<span class="badge badge-danger">Overdue</span>'
    };
    return badges[status] || `<span class="badge badge-secondary">${status}</span>`;
}

function setupAutoRefresh() {
    // Auto-refresh dashboard every 30 seconds
    setInterval(() => {
        loadDashboardData();
    }, 30000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}