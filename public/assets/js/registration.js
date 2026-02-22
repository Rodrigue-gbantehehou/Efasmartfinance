document.addEventListener('DOMContentLoaded', function() {
    const emailInput = document.querySelector('#registration_form_email');
    const form = document.querySelector('form[name="registration_form"]');
    
    if (!emailInput || !form) return;

    const emailError = document.createElement('div');
    emailError.className = 'text-red-500 text-sm mt-1';
    emailInput.parentNode.insertBefore(emailError, emailInput.nextSibling);
    emailError.style.display = 'none';

    let checkEmailTimeout;

    // Fonction pour valider l'email
    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    // Vérification en temps réel
    emailInput.addEventListener('input', function() {
        const email = this.value.trim();
        emailError.style.display = 'none';
        
        if (!email) return;
        
        if (!validateEmail(email)) {
            return;
        }

        clearTimeout(checkEmailTimeout);
        
        // Attendre que l'utilisateur ait fini de taper
        checkEmailTimeout = setTimeout(() => {
            checkEmailExists(email);
        }, 500);
    });

    // Soumission du formulaire
    if (form) {
        form.addEventListener('submit', function(event) {
            const email = emailInput.value.trim();
            
            if (email && emailError.textContent.includes('déjà utilisé')) {
                event.preventDefault();
                emailError.style.display = 'block';
                emailInput.focus();
            }
        });
    }

    // Vérification asynchrone de l'email
    function checkEmailExists(email) {
        const validateUrl = emailInput.dataset.validateEmailUrl;
        if (!validateUrl) return;

        emailError.textContent = 'Vérification en cours...';
        emailError.style.color = '#3b82f6'; // Bleu
        emailError.style.display = 'block';

        fetch(validateUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'email=' + encodeURIComponent(email)
        })
        .then(response => response.json())
        .then(data => {
            if (data.exists) {
                emailError.textContent = 'Cet email est déjà utilisé. Veuillez en choisir un autre.';
                emailError.style.color = '#ef4444'; // Rouge
                emailInput.setCustomValidity('Cet email est déjà utilisé');
            } else {
                emailError.textContent = '';
                emailError.style.color = '#10b981'; // Vert
                emailInput.setCustomValidity('');
            }
            emailError.style.display = 'block';
        })
        .catch(error => {
            console.error('Erreur lors de la vérification de l\'email:', error);
            emailError.textContent = 'Erreur lors de la vérification. La validation se fera à l\'envoi du formulaire.';
            emailError.style.color = '#ef4444'; // Rouge
            emailError.style.display = 'block';
        });
    }
});