// Format currency helper
function formatCurrency(amount) {
    if (typeof amount !== 'number') {
        amount = parseFloat(amount) || 0;
    }
    return new Intl.NumberFormat('fr-FR', {
        style: 'decimal',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount) ;
}

// Update tontines list
function updateTontinesList(tontines) {
    const tontinesList = document.getElementById('tontinesList');
    if (!tontinesList) return;
    
    // Clear existing content
    tontinesList.innerHTML = '';
    
    if (tontines.length === 0) {
        tontinesList.innerHTML = `
            <div class="text-center py-8">
                <i class="fa-solid fa-inbox text-4xl text-gray-300 mb-3"></i>
                <p class="text-gray-500">Aucune tontine trouvée</p>
            </div>
        `;
        return;
    }
    
    tontines.forEach(tontine => {
        const tontineElement = document.createElement('div');
        tontineElement.className = 'flex items-center justify-between p-3 md:p-4 border border-gray-100 rounded-lg hover:bg-gray-50 transition-colors';
        tontineElement.innerHTML = `
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-lg bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                    <i class="fa-solid fa-piggy-bank"></i>
                </div>
                <div>
                    <h4 class="font-medium text-gray-800">${tontine.name || 'Tontine #' + tontine.id}</h4>
                    <div class="flex items-center gap-2 text-xs text-gray-500">
                        <span>${formatCurrency(tontine.amount)}</span>
                        <span>•</span>
                        <span>${tontine.period === 'monthly' ? 'Mensuel' : 'Hebdomadaire'}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs px-2 py-1 rounded-full ${tontine.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}">
                    ${tontine.status === 'active' ? 'Active' : 'Terminée'}
                </span>
                <i class="fa-solid fa-chevron-right text-gray-400"></i>
            </div>
        `;
        tontinesList.appendChild(tontineElement);
    });
}

// Show loading state
function showLoading() {
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) {
        loadingOverlay.classList.remove('hidden');
    }
}

// Hide loading state
function hideLoading() {
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) {
        loadingOverlay.classList.add('hidden');
    }
}

// Show notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg flex items-center justify-between transform transition-all duration-300 ${
        type === 'success' ? 'bg-green-500 text-white' :
        type === 'error' ? 'bg-red-500 text-white' :
        type === 'warning' ? 'bg-yellow-500 text-white' :
        'bg-blue-500 text-white'
    }`;
    
    notification.innerHTML = `
        <span>${message}</span>
        <button class="ml-4 text-white hover:text-gray-200" onclick="this.parentElement.remove()">
            <i class="fa-solid fa-times"></i>
        </button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove notification after 5 seconds
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

// Initialize savings chart
function initSavingsChart() {
    const ctx = document.getElementById('savingsChart');
    if (!ctx) return;
    
    // Simple chart implementation
    const canvas = ctx.getContext('2d');
    if (canvas) {
        // Draw a simple line chart
        canvas.strokeStyle = '#10B981';
        canvas.lineWidth = 2;
        canvas.beginPath();
        canvas.moveTo(0, 100);
        canvas.lineTo(50, 80);
        canvas.lineTo(100, 90);
        canvas.lineTo(150, 60);
        canvas.lineTo(200, 70);
        canvas.lineTo(250, 50);
        canvas.stroke();
    }
}

// Refresh dashboard data
function refreshData() {
    showNotification('Actualisation en cours...', 'info');
    loadDashboardData();
    
    // Vibration feedback on mobile
    if ('vibrate' in navigator) {
        navigator.vibrate([30, 50, 30]);
    }
}

// Load dashboard data from API
async function loadDashboardData() {
    showLoading();
    
    try {
        // Fetch data from API
        const response = await fetch('/api/tontines');
        if (!response.ok) {
            throw new Error('Erreur lors du chargement des données');
        }
        
        const data = await response.json();
        
        // Update user info
        const userNameElement = document.getElementById('welcomeUserName');
        const userDisplayNameElement = document.getElementById('userDisplayName');
        
        if (userNameElement) {
            userNameElement.textContent = data.user.fullName || 'Utilisateur';
        }
        
        if (userDisplayNameElement) {
            userDisplayNameElement.textContent = data.user.fullName || 'Utilisateur';
        }
        
        // Format and update total balance
        const totalBalance = data.stats?.totalBalance || 0;
        const totalBalanceElement = document.getElementById('totalBalance');
        if (totalBalanceElement) {
            totalBalanceElement.textContent = formatCurrency(totalBalance);
        }
        
        // Update KPIs
        const activeTontinesCountElement = document.getElementById('activeTontinesCount');
        if (activeTontinesCountElement) {
            activeTontinesCountElement.textContent = data.stats?.activeTontines || 0;
        }
        
        const completedTontinesElement = document.getElementById('completedTontines');
        if (completedTontinesElement) {
            completedTontinesElement.textContent = data.stats?.completedTontines || 0;
        }
        
        // Update progress bars
        const totalTontines = data.stats?.totalTontines || 1;
        const activeProgress = ((data.stats?.activeTontines || 0) / totalTontines) * 100;
        const activeTontinesProgressElement = document.getElementById('activeTontinesProgress');
        if (activeTontinesProgressElement) {
            activeTontinesProgressElement.style.width = `${activeProgress}%`;
        }
        
        // Update upcoming payments
        const upcomingPaymentsCountElement = document.getElementById('upcomingPaymentsCount');
        if (upcomingPaymentsCountElement) {
            upcomingPaymentsCountElement.textContent = data.stats?.upcomingPayments || 0;
        }
        
        // Update monthly savings (example)
        const monthlySavingsElement = document.getElementById('monthlySavings');
        if (monthlySavingsElement) {
            monthlySavingsElement.textContent = formatCurrency(totalBalance / 12);
        }
        
        // If there are tontines, update the list
        if (data.tontines && data.tontines.length > 0) {
            updateTontinesList(data.tontines);
        }
        
        // Update next payment info
        const nextPaymentAmountElement = document.getElementById('nextPaymentAmount');
        const nextPaymentDateElement = document.getElementById('nextPaymentDate');
        
        if (data.tontines && data.tontines.length > 0) {
            const nextTontine = data.tontines.find(t => t.nextPayment) || data.tontines[0];
            if (nextTontine) {
                if (nextPaymentAmountElement) {
                    nextPaymentAmountElement.textContent = formatCurrency(nextTontine.amount);
                }
                if (nextPaymentDateElement && nextTontine.nextPayment) {
                    const date = new Date(nextTontine.nextPayment);
                    nextPaymentDateElement.textContent = date.toLocaleDateString('fr-FR');
                }
            }
        }
        
    } catch (error) {
        console.error('Erreur:', error);
        showNotification('Erreur lors du chargement des données', 'error');
    } finally {
        hideLoading();
    }
}

// Initialize dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Set current year
    const currentYearElement = document.getElementById('currentYear');
    if (currentYearElement) {
        currentYearElement.textContent = new Date().getFullYear();
    }
    
    // Initialize chart
    initSavingsChart();
    
    // Load dashboard data
    loadDashboardData();
    
    // User menu toggle
    const userMenuButton = document.getElementById('userMenuButton');
    const userMenu = document.getElementById('userMenu');
    
    if (userMenuButton && userMenu) {
        userMenuButton.addEventListener('click', function(e) {
            e.stopPropagation();
            userMenu.classList.toggle('hidden');
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!userMenu.contains(e.target) && e.target !== userMenuButton) {
                userMenu.classList.add('hidden');
            }
        });
    }
    
    // Add vibration on mobile for actions
    if ('vibrate' in navigator) {
        document.querySelectorAll('button, a').forEach(el => {
            el.addEventListener('touchstart', function() {
                navigator.vibrate(10);
            });
        });
    }
});
