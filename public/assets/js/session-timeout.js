document.addEventListener('DOMContentLoaded', function() {
    const INACTIVITY_TIMEOUT = 30 * 60 * 1000; // 30 minutes
    const CHECK_INTERVAL = 5 * 60 * 1000; // Vérification toutes les 5 minutes
    
    let warningTimer;
    let checkInterval;
    let activityDetected = false;

    // Vérifier l'état de la session
    async function checkSession() {
        try {
            const response = await fetch('/check-session', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            });

            // Vérifier si la réponse est du JSON
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.indexOf("application/json") !== -1) {
                const data = await response.json();
                if (!data.valid) {
                    cleanup();
                    window.location.href = '/logout';
                }
            } else {
                console.warn('Réponse non-JSON reçue de /check-session');
            }
        } catch (error) {
            console.error('Erreur lors de la vérification de la session:', error);
        }
    }

    // Nettoyer tous les timers
    function cleanup() {
        clearTimeout(warningTimer);
        clearInterval(checkInterval);
    }

    // Fonction de déconnexion
    async function logout() {
        // NE PAS envoyer de requête update-activity ici
        // La session sera invalidée côté serveur par /check-session
        cleanup();
        window.location.href = '/logout';
    }

    // Réinitialiser le timer d'inactivité
    function resetInactivityTimer() {
        clearTimeout(warningTimer);
        warningTimer = setTimeout(logout, INACTIVITY_TIMEOUT);
    }

    // Démarrer la vérification périodique
    function startCheckInterval() {
        clearInterval(checkInterval);
        checkSession(); // Vérifier immédiatement
        checkInterval = setInterval(checkSession, CHECK_INTERVAL);
    }

    // Événements qui réinitialisent le timer d'inactivité
    function attachActivityListeners() {
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'];
        
        events.forEach(event => {
            document.addEventListener(event, resetInactivityTimer, { passive: true });
        });
    }

    // Initialisation
    function init() {
        cleanup();
        attachActivityListeners();
        resetInactivityTimer();
        startCheckInterval();
    }

    // Démarrer
    init();
    
    // Nettoyer lors du déchargement de la page
    window.addEventListener('beforeunload', cleanup);
});