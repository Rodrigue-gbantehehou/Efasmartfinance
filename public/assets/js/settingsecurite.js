// settingsprofile.js - Gestion de la section de sécurité
document.addEventListener('DOMContentLoaded', function() {
    // Initialisation du formulaire de sécurité
    const securityForm = document.getElementById('security-form');
    if (securityForm) {
        initSecurityForm();
    }

    // Charger les données de sécurité au chargement de la page
    if (window.location.hash === '#securite' || document.querySelector('#securite-section')) {
        loadSecurityData();
    }
});

// Initialisation du formulaire de sécurité
function initSecurityForm() {
    const form = document.getElementById('security-form');
    if (!form) return;

    // Gestionnaire de soumission du formulaire
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        updateSecuritySettings();
    });

    // Afficher/masquer le mot de passe
    const togglePasswordBtns = form.querySelectorAll('.toggle-password');
    togglePasswordBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
    });
}

// Charger les données de sécurité
async function loadSecurityData() {
    try {
        const response = await fetch('/dashboard/api/settings/security', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            throw new Error('Erreur lors du chargement des paramètres de sécurité');
        }

        const data = await response.json();
        
        if (data.status === 'success') {
            // Mettre à jour l'état du 2FA
            const twoFactorToggle = document.getElementById('enableTwoFactor');
            if (twoFactorToggle) {
                twoFactorToggle.checked = data.security.hasTwoFactor;
            }
            
            // Mettre à jour la liste des sessions actives
            updateActiveSessionsList(data.security.activeSessions);
        }
    } catch (error) {
        console.error('Erreur:', error);
        showAlert('error', 'Une erreur est survenue lors du chargement des paramètres de sécurité');
    }
}

// Mettre à jour les paramètres de sécurité
async function updateSecuritySettings() {
    const form = document.getElementById('security-form');
    const submitBtn = form.querySelector('button[type="submit"]');
    const submitBtnText = submitBtn.innerHTML;
    const formData = {};

    // Récupérer les données du formulaire
    const currentPassword = form.querySelector('#currentPassword').value;
    const newPassword = form.querySelector('#newPassword').value;
    const confirmPassword = form.querySelector('#confirmPassword').value;
    const enableTwoFactor = form.querySelector('#enableTwoFactor').checked;
    const logoutOtherDevices = form.querySelector('#logoutOtherDevices').checked;

    // Vérifier si l'utilisateur essaie de changer le mot de passe
    if (newPassword || confirmPassword) {
        // Vérifier que le mot de passe actuel est fourni
        if (!currentPassword) {
            showAlert('error', 'Veuillez entrer votre mot de passe actuel', 'currentPassword');
            return;
        }

        // Vérifier que les deux champs de mot de passe sont remplis
        if (!newPassword || !confirmPassword) {
            showAlert('error', 'Veuillez remplir les deux champs de mot de passe', 'newPassword');
            return;
        }

        // Vérifier que les mots de passe correspondent
        if (newPassword !== confirmPassword) {
            showAlert('error', 'Les nouveaux mots de passe ne correspondent pas', 'newPassword');
            return;
        }

        // Vérifier la longueur minimale du mot de passe
        if (newPassword.length < 8) {
            showAlert('error', 'Le mot de passe doit contenir au moins 8 caractères', 'newPassword');
            return;
        }

        // Vérifier la complexité du mot de passe (au moins une majuscule et un chiffre)
        if (!/(?=.*[A-Z])(?=.*\d)/.test(newPassword)) {
            showAlert('error', 'Le mot de passe doit contenir au moins une majuscule et un chiffre', 'newPassword');
            return;
        }

        // Si tout est valide, ajouter au formData
        formData.currentPassword = currentPassword;
        formData.newPassword = newPassword;
    }

    // Ajouter les autres paramètres
    formData.enableTwoFactor = enableTwoFactor;
    formData.logoutOtherDevices = logoutOtherDevices;

    try {
        // Afficher le chargement
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Enregistrement...';

        const response = await fetch('/dashboard/api/settings/security', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(formData)
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Erreur lors de la mise à jour des paramètres de sécurité');
        }

        // Afficher un message de succès
        showAlert('success', data.message || 'Paramètres de sécurité mis à jour avec succès');
        
        // Réinitialiser le formulaire si le mot de passe a été changé
        if (data.passwordChanged) {
            form.reset();
        }

        // Recharger les données de sécurité
        await loadSecurityData();

    } catch (error) {
        console.error('Erreur:', error);
        showAlert('error', error.message || 'Une erreur est survenue lors de la mise à jour des paramètres de sécurité');
    } finally {
        // Réactiver le bouton
        submitBtn.disabled = false;
        submitBtn.innerHTML = submitBtnText;
    }
}

// Mettre à jour la liste des sessions actives
function updateActiveSessionsList(sessions) {
    const sessionsList = document.getElementById('activeSessionsList');
    if (!sessionsList) return;

    if (!sessions || sessions.length === 0) {
        sessionsList.innerHTML = '<p class="text-gray-500 text-sm">Aucune session active</p>';
        return;
    }

    // Mettre à jour la liste des sessions
    sessionsList.innerHTML = sessions.map(session => `
        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg mb-2">
            <div>
                <p class="font-medium">${session.device || 'Appareil inconnu'}</p>
                <p class="text-sm text-gray-500">${session.location || 'Localisation inconnue'} • ${session.lastActivity || 'Inconnu'}</p>
            </div>
            <button class="text-red-500 hover:text-red-700" onclick="revokeSession('${session.id}')">
                <i class="fas fa-sign-out-alt"></i>
            </button>
        </div>
    `).join('');
}

// Révoquer une session
async function revokeSession(sessionId) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer cette session ?')) {
        return;
    }

    try {
        const response = await fetch(`/dashboard/api/settings/security/sessions/${sessionId}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            throw new Error('Erreur lors de la suppression de la session');
        }

        // Recharger les données de sécurité
        await loadSecurityData();
        showAlert('success', 'Session supprimée avec succès');

    } catch (error) {
        console.error('Erreur:', error);
        showAlert('error', error.message || 'Une erreur est survenue lors de la suppression de la session');
    }
}

// Fonction utilitaire pour afficher des alertes
function showAlert(type, message, fieldId = null) {
    // Supprimer les alertes existantes
    const existingAlerts = document.querySelectorAll('.alert-message');
    existingAlerts.forEach(alert => alert.remove());

    // Créer la nouvelle alerte
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert-message fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
        type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
    }`;
    alertDiv.textContent = message;

    // Ajouter l'alerte au corps du document
    document.body.appendChild(alertDiv);

    // Faire défiler jusqu'au champ en erreur si spécifié
    if (fieldId) {
        const field = document.getElementById(fieldId);
        if (field) {
            field.scrollIntoView({ behavior: 'smooth', block: 'center' });
            field.focus();
        }
    }

    // Supprimer l'alerte après 5 secondes
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}
