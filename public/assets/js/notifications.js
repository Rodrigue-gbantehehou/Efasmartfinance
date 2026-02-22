document.addEventListener('DOMContentLoaded', function() {
    const notificationForm = document.getElementById('notification-settings-form');
    const saveButton = document.getElementById('save-notification-settings');

    if (!notificationForm || !saveButton) return;

    // Charger les préférences actuelles
    loadNotificationPreferences();

    // Gérer la soumission du formulaire
    saveButton.addEventListener('click', saveNotificationPreferences);
});

async function loadNotificationPreferences() {
    try {
        const response = await fetch('/dashboard/api/settings/notification', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error('Erreur lors du chargement des préférences');
        }

        const data = await response.json();
        
        if (data.status === 'success') {
            // Mettre à jour les cases à cocher avec les valeurs du serveur
            const prefs = data.preferences;
            
            // Notifications email
            document.querySelector('input[name="emailNotifications"]').checked = prefs.emailNotifications;
            document.querySelector('input[name="securityAlerts"]').checked = prefs.securityAlerts;
            document.querySelector('input[name="transactionAlerts"]').checked = prefs.transactionAlerts;
            document.querySelector('input[name="paymentReminders"]').checked = prefs.paymentReminders;
            document.querySelector('input[name="marketingEmail"]').checked = prefs.marketingEmail;
            
            // Notifications push
            document.querySelector('input[name="pushNotifications"]').checked = prefs.pushNotifications;
            
            // Fréquence des notifications (à implémenter côté serveur si nécessaire)
            // document.querySelector(`input[name="notificationFrequency"][value="${prefs.notificationFrequency}"]`).checked = true;
        }
    } catch (error) {
        console.error('Erreur:', error);
        showNotification('Erreur lors du chargement des préférences', 'error');
    }
}

async function saveNotificationPreferences() {
    const saveButton = document.getElementById('save-notification-settings');
    const originalButtonText = saveButton.innerHTML;
    
    try {
        // Désactiver le bouton et afficher un indicateur de chargement
        saveButton.disabled = true;
        saveButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Enregistrement...';

        // Récupérer les valeurs du formulaire
        const formData = {
            emailNotifications: document.querySelector('input[name="emailNotifications"]').checked,
            securityAlerts: document.querySelector('input[name="securityAlerts"]').checked,
            transactionAlerts: document.querySelector('input[name="transactionAlerts"]').checked,
            paymentReminders: document.querySelector('input[name="paymentReminders"]').checked,
            marketingEmail: document.querySelector('input[name="marketingEmail"]').checked,
            pushNotifications: document.querySelector('input[name="pushNotifications"]').checked,
            notificationFrequency: document.querySelector('input[name="notificationFrequency"]:checked')?.value || 'immediate'
        };

        const response = await fetch('/dashboard/api/settings/notification', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(formData),
            credentials: 'same-origin'
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Erreur lors de la mise à jour des préférences');
        }

        // Afficher un message de succès
        showNotification('Préférences enregistrées avec succès', 'success');

    } catch (error) {
        console.error('Erreur:', error);
        showNotification(error.message || 'Une erreur est survenue', 'error');
    } finally {
        // Réactiver le bouton et réinitialiser le texte
        saveButton.disabled = false;
        saveButton.innerHTML = originalButtonText;
    }
}

function showNotification(message, type = 'info') {
    // Créer la notification
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white font-medium z-50 transform transition-all duration-300 ${
        type === 'success' ? 'bg-green-500' : 
        type === 'error' ? 'bg-red-500' : 'bg-blue-500'
    }`;
    
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} mr-2"></i>
            <span>${message}</span>
        </div>
    `;
    
    // Ajouter la notification au DOM
    document.body.appendChild(notification);
    
    // Animation d'entrée
    setTimeout(() => {
        notification.classList.remove('opacity-0', 'translate-y-2');
        notification.classList.add('opacity-100');
    }, 10);
    
    // Supprimer la notification après 5 secondes
    setTimeout(() => {
        notification.classList.remove('opacity-100');
        notification.classList.add('opacity-0', 'translate-y-2');
        
        // Supprimer du DOM après l'animation
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 5000);
}

// Exposer les fonctions au scope global pour qu'elles soient accessibles depuis d'autres fichiers
window.NotificationSettings = {
    load: loadNotificationPreferences,
    save: saveNotificationPreferences
};
