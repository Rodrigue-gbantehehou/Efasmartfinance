document.addEventListener('DOMContentLoaded', function() {
    // Gestion des onglets
    const tabs = document.querySelectorAll('[data-tab]');
    const tabContents = document.querySelectorAll('.section-content');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const target = this.getAttribute('data-tab');
            
            // Mettre à jour les onglets actifs
            tabs.forEach(t => t.classList.remove('bg-primary', 'text-white'));
            tabs.forEach(t => t.classList.add('bg-gray-200', 'text-gray-700', 'hover:bg-gray-300'));
            this.classList.remove('bg-gray-200', 'text-gray-700', 'hover:bg-gray-300');
            this.classList.add('bg-primary', 'text-white');
            
            // Afficher le contenu correspondant
            tabContents.forEach(content => content.classList.add('hidden'));
            document.getElementById(`${target}-section`).classList.remove('hidden');
        });
    });
    
    // Gestion du formulaire de notifications
    const notificationForm = document.getElementById('notification-form');
    
    if (notificationForm) {
        // Charger les préférences actuelles
        loadNotificationPreferences();
        
        notificationForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = {
                emailNotifications: document.getElementById('email-notifications').checked,
                pushNotifications: document.getElementById('push-notifications').checked,
                transactionAlerts: document.getElementById('transaction-alerts').checked,
                marketingEmail: document.getElementById('marketing-email').checked,
                paymentReminders: document.getElementById('payment-reminders').checked,
                securityAlerts: document.getElementById('security-alerts').checked
            };
            
            const submitButton = notificationForm.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            
            // Afficher un indicateur de chargement
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Enregistrement...';
            
            // Envoyer la requête AJAX
            fetch('/dashboard/api/settings/notification', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showAlert('success', data.message);
                } else {
                    showAlert('error', 'Une erreur est survenue lors de la mise à jour des paramètres.');
                    console.error('Erreur:', data.errors);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showAlert('error', 'Une erreur est survenue lors de la communication avec le serveur.');
            })
            .finally(() => {
                // Réactiver le bouton et restaurer le texte original
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            });
        });
    }
    
    // Fonction pour charger les préférences de notification
    function loadNotificationPreferences() {
        fetch('/dashboard/api/settings/notification')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success' && data.preferences) {
                    const prefs = data.preferences;
                    document.getElementById('email-notifications').checked = prefs.emailNotifications || false;
                    document.getElementById('push-notifications').checked = prefs.pushNotifications || false;
                    document.getElementById('transaction-alerts').checked = prefs.transactionAlerts || false;
                    document.getElementById('marketing-email').checked = prefs.marketingEmail || false;
                    document.getElementById('payment-reminders').checked = prefs.paymentReminders || false;
                    document.getElementById('security-alerts').checked = prefs.securityAlerts || false;
                }
            })
            .catch(error => {
                console.error('Erreur lors du chargement des préférences:', error);
            });
    }
    
    // Fonction pour afficher les messages d'alerte
    function showAlert(type, message) {
        // Supprimer les anciennes alertes
        const oldAlerts = document.querySelectorAll('.alert-message');
        oldAlerts.forEach(alert => alert.remove());
        
        // Créer la nouvelle alerte
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert-message fixed top-4 right-4 p-4 rounded-lg shadow-lg ${
            type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
        }`;
        
        const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        alertDiv.innerHTML = `
            <div class="flex items-center">
                <i class="fas ${icon} mr-2"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(alertDiv);
        
        // Supprimer l'alerte après 5 secondes
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
    
    // Afficher/masquer le formulaire de changement de mot de passe
    const passwordForm = document.getElementById('passwordForm');
    const showPasswordFormBtn = document.getElementById('showPasswordForm');
    const cancelPasswordBtn = document.getElementById('cancelPasswordBtn');
    
    if (showPasswordFormBtn) {
        showPasswordFormBtn.addEventListener('click', function() {
            this.style.display = 'none';
            if (passwordForm) passwordForm.style.display = 'block';
        });
    }
    
    if (cancelPasswordBtn) {
        cancelPasswordBtn.addEventListener('click', function() {
            if (passwordForm) passwordForm.style.display = 'none';
            if (showPasswordFormBtn) showPasswordFormBtn.style.display = 'block';
        });
    }
});
