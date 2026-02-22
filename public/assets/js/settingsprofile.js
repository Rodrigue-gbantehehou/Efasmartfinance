document.addEventListener('DOMContentLoaded', function () {
    // Initialisation des gestionnaires d'événements
    initProfileForm();

    // Afficher la section par défaut
    const defaultSection = document.querySelector('.section-content.active');
    if (defaultSection) {
        const sectionId = defaultSection.id.replace('-section', '');
        loadSectionData(sectionId);
    }
});

// Fonction pour charger les données d'une section
function loadSectionData(sectionId) {
    switch (sectionId) {
        case 'profil':
            loadProfileData();
            break;
        // Ajoutez d'autres cas pour les autres sections si nécessaire
    }
}

// Fonction pour basculer entre les sections
function showSection(sectionId) {
    // Masquer toutes les sections
    document.querySelectorAll('.section-content').forEach(section => {
        section.classList.add('hidden');
        section.classList.remove('active');
    });

    // Afficher la section sélectionnée
    const activeSection = document.getElementById(`${sectionId}-section`);
    if (activeSection) {
        activeSection.classList.remove('hidden');
        activeSection.classList.add('active');

        // Charger les données de la section
        loadSectionData(sectionId);
    }

    // Mettre à jour la navigation
    updateActiveNav(sectionId);
}

// Fonction pour mettre à jour la navigation active
function updateActiveNav(sectionId) {
    // Mettre à jour les boutons de navigation (Mobile & Desktop)
    document.querySelectorAll('[data-section-btn]').forEach(btn => {
        const isSelected = btn.dataset.sectionBtn === sectionId;

        if (btn.closest('.md\\:hidden')) { // Mobile
            if (isSelected) {
                btn.className = "px-5 py-2.5 rounded-xl text-xs font-bold transition-all bg-green-600 text-white shadow-sm shadow-green-200";
            } else {
                btn.className = "px-5 py-2.5 rounded-xl text-xs font-bold transition-all text-green-700 hover:bg-green-50";
            }
        } else { // Desktop
            if (isSelected) {
                btn.className = "w-full flex items-center px-4 py-3.5 text-sm font-bold text-white bg-green-600 rounded-2xl shadow-lg shadow-green-100 transition-all";
                const icon = btn.querySelector('i');
                if (icon) icon.className = icon.className.replace('opacity-60', '').trim();
            } else {
                btn.className = "w-full flex items-center px-4 py-3.5 text-sm font-bold text-gray-500 hover:bg-green-50 hover:text-green-700 rounded-2xl transition-all";
                const icon = btn.querySelector('i');
                if (icon && !icon.className.includes('opacity-60')) {
                    icon.className += ' opacity-60';
                }
            }
        }
    });
}

// Initialisation du formulaire de profil
function initProfileForm() {
    const form = document.getElementById('profile-form');
    if (!form) return;

    // Gestionnaire de soumission du formulaire
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        updateProfile();
    });
}

// Charger les données du profil depuis l'API
async function loadProfileData() {
    try {
        const response = await fetch('/dashboard/api/settings/profile', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            throw new Error('Erreur lors du chargement des données du profil');
        }

        const data = await response.json();

        if (data.status === 'success') {
            // Mettre à jour les champs du formulaire
            const { firstname, lastname, email, phone } = data.profile;
            const fields = {
                'firstname': firstname,
                'lastname': lastname,
                'email': email,
                'phone': phone
            };

            for (const [id, value] of Object.entries(fields)) {
                const el = document.getElementById(id);
                if (el) el.value = value || '';
            }
        }
    } catch (error) {
        console.error('Erreur:', error);
        showAlert('error', 'Une erreur est survenue lors du chargement des données du profil');
    }
}

// Mettre à jour le profil via l'API
async function updateProfile() {
    const form = document.getElementById('profile-form');
    const submitBtn = form.querySelector('button[type="submit"]');
    const submitBtnText = submitBtn.innerHTML;

    try {
        // Afficher le chargement
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Enregistrement...';

        const formData = {
            firstname: document.getElementById('firstname')?.value.trim() || '',
            lastname: document.getElementById('lastname')?.value.trim() || '',
            email: document.getElementById('email')?.value.trim() || '',
            phone: document.getElementById('phone')?.value.trim() || ''
        };

        const response = await fetch('/dashboard/api/settings/profile', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(formData)
        });

        const data = await response.json();

        if (response.ok && data.status === 'success') {
            showAlert('success', 'Profil mis à jour avec succès');
        } else {
            throw new Error(data.message || 'Erreur lors de la mise à jour du profil');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showAlert('error', error.message || 'Une erreur est survenue lors de la mise à jour du profil');
    } finally {
        // Réactiver le bouton
        submitBtn.disabled = false;
        submitBtn.innerHTML = submitBtnText;
    }
}

// Fonction utilitaire pour afficher des alertes
function showAlert(type, message) {
    // Supprimer les alertes existantes
    const existingAlerts = document.querySelectorAll('.alert-message');
    existingAlerts.forEach(alert => alert.remove());

    // Créer la nouvelle alerte
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert-message fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
        }`;
    alertDiv.textContent = message;

    // Ajouter l'alerte au corps du document
    document.body.appendChild(alertDiv);

    // Supprimer l'alerte après 5 secondes
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}
