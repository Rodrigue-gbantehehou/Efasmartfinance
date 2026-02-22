// Configuration globale
const FEDAPAY_PK = '{{ fedapay_public_key}}';
const KKIA_PK = '{{ kkiapay_public_key }}';
let selectedPaymentMethod = null;
let currentTontineData = null;
let paymentAmount = 0;
let paymentCompleted = false;

// Variables globales pour le paiement
let paymentData = {
    tontineId: null,
    tontineName: '',
    amountPerPoint: 0,
    nextDueDate: '',
    progress: 0,
    totalPoints: 0,
    paidPoints: 0
};

// Initialisation au chargement
document.addEventListener('DOMContentLoaded', function() {
    // Gestion de la modal de paiement
    const modal = document.getElementById('paymentModal');
    const backdrop = modal.querySelector('.bg-black\\/50');
    
    if (backdrop) {
        backdrop.addEventListener('click', closePaymentModal);
    }
    
    // Fermer avec Escape
    document.addEventListener('keydown', (e) => { 
        if (e.key === 'Escape') closePaymentModal();
    });
});

// Fonction pour ouvrir le paiement depuis une tontine
function openTontinePayment(tontineId, tontineName, amount, dueDate, progress, totalPoints, paidPoints) {
    // Stocker les données
    paymentData = {
        tontineId: tontineId,
        tontineName: tontineName,
        amountPerPoint: amount,
        nextDueDate: dueDate,
        progress: progress,
        totalPoints: totalPoints,
        paidPoints: paidPoints
    };
    
    paymentAmount = amount;
    
    // Mettre à jour l'interface de la modal
    updatePaymentModal();
    
    // Ouvrir la modal
    openPaymentModal();
}

// Mettre à jour la modal de paiement
function updatePaymentModal() {
    document.getElementById('paymentTitle').textContent = `Paiement: ${paymentData.tontineName}`;
    document.getElementById('paymentDescription').textContent = `Versement pour la tontine ${paymentData.tontineName}`;
    document.getElementById('paymentAmount').textContent = `${paymentData.amountPerPoint.toLocaleString('fr-FR')} FCFA`;
    document.getElementById('paymentDueDate').textContent = paymentData.nextDueDate;
    document.getElementById('paymentProgress').textContent = `${paymentData.progress}%`;
    document.getElementById('paymentProgressBar').style.width = `${paymentData.progress}%`;
}

// Ouvrir la modal de paiement
function openPaymentModal() {
    // Réinitialiser la sélection
    resetPaymentSelection();
    
    // Afficher la modal
    const modal = document.getElementById('paymentModal');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Animation
    modal.classList.add('modal-enter');
    modal.querySelector('.modal-content-enter').classList.add('modal-content-enter');
}

// Fermer la modal
function closePaymentModal() {
    const modal = document.getElementById('paymentModal');
    modal.classList.add('hidden');
    document.body.style.overflow = '';
    
    // Réinitialiser
    resetPaymentUI();
}

// Réinitialiser la sélection
function resetPaymentSelection() {
    selectedPaymentMethod = null;
    document.querySelectorAll('.payment-option').forEach(option => {
        option.classList.remove('selected');
    });
    
    const paypalContainer = document.getElementById('paypal-button-container');
    paypalContainer.classList.add('hidden');
    paypalContainer.innerHTML = '';
    
    const confirmBtn = document.getElementById('confirm-payment-btn');
    confirmBtn.disabled = false;
    
    document.getElementById('confirm-text').classList.remove('hidden');
    document.getElementById('loading-text').classList.add('hidden');
    
    hideError();
}

// Sélectionner une méthode de paiement
function selectPayment(method) {
    selectedPaymentMethod = method;
    
    // Mettre à jour l'interface
    document.querySelectorAll('.payment-option').forEach(option => {
        option.classList.remove('selected');
    });
    
    // Trouver et sélectionner l'option
    document.querySelectorAll('.payment-option').forEach(option => {
        if (option.onclick && option.onclick.toString().includes(`'${method}'`)) {
            option.classList.add('selected');
        }
    });
    
    // Gérer PayPal spécialement
    const paypalContainer = document.getElementById('paypal-button-container');
    const confirmBtn = document.getElementById('confirm-payment-btn');
    
    if (method === 'paypal') {
        confirmBtn.classList.add('hidden');
        paypalContainer.classList.remove('hidden');
        initPayPal();
    } else if (method === 'cash') {
        confirmBtn.querySelector('#confirm-text').textContent = 'Confirmer le paiement en espèces';
        confirmBtn.classList.remove('hidden');
        paypalContainer.classList.add('hidden');
        paypalContainer.innerHTML = '';
    } else {
        confirmBtn.querySelector('#confirm-text').textContent = 'Payer maintenant';
        confirmBtn.classList.remove('hidden');
        paypalContainer.classList.add('hidden');
        paypalContainer.innerHTML = '';
    }
}

// Traiter le paiement
function processPayment() {
    if (!selectedPaymentMethod) {
        showError('Veuillez sélectionner un moyen de paiement');
        return;
    }
    
    // Mettre en état de chargement
    setLoading(true);
    
    // Traiter selon la méthode
    switch(selectedPaymentMethod) {
        case 'fedapay':
            processFedaPay();
            break;
        case 'kkiapay':
            processKkiaPay();
            break;
        case 'paypal':
            // PayPal se gère automatiquement
            break;
        case 'cash':
            processCashPayment();
            break;
        default:
            showError('Méthode de paiement non supportée');
            setLoading(false);
    }
}

// Paiement FedaPay
async function processFedaPay() {
    try {
        const checkout = FedaPay.init({
            public_key: FEDAPAY_PK,
            transaction: {
                amount: paymentAmount,
                currency: 'XOF',
                description: `Paiement tontine - ${paymentData.tontineName}`
            },
            callback: async function(response) {
                if (response && response.transaction && response.transaction.status === 'approved') {
                    await savePayment({
                        tontine_id: paymentData.tontineId,
                        amount: paymentAmount,
                        method: 'fedapay',
                        transaction_id: response.transaction.id
                    });
                } else {
                    showError('Paiement annulé ou échoué');
                    setLoading(false);
                }
            },
            onClose: function() {
                console.log('FedaPay modal fermé');
                setLoading(false);
            }
        });
        
        checkout.open();
    } catch (error) {
        console.error('Erreur FedaPay:', error);
        showError('Erreur lors du paiement FedaPay');
        setLoading(false);
    }
}

// Paiement KKiaPay
function processKkiaPay() {
    try {
        window.Kkiapay.open({
            amount: paymentAmount,
            key: KKIA_PK,
            callback: async function(response) {
                if (response && response.status === 'SUCCESS') {
                    await savePayment({
                        tontine_id: paymentData.tontineId,
                        amount: paymentAmount,
                        method: 'kkiapay',
                        transaction_id: response.transactionId
                    });
                } else {
                    showError('Paiement annulé ou échoué');
                    setLoading(false);
                }
            },
            onClose: function() {
                console.log('KKiaPay fermé');
                setLoading(false);
            },
            theme: 'green',
            position: 'center'
        });
    } catch (error) {
        console.error('Erreur KKiaPay:', error);
        showError('Erreur lors du paiement KKiaPay');
        setLoading(false);
    }
}

// Paiement en espèces
async function processCashPayment() {
    try {
        const response = await fetch('/tontine/cash-payment', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                tontine_id: paymentData.tontineId,
                amount: paymentAmount,
                method: 'cash'
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess('Paiement en espèces enregistré. Veuillez régler auprès du gestionnaire.');
            setTimeout(() => {
                closePaymentModal();
                if (data.redirect_url) {
                    window.location.href = data.redirect_url;
                }
            }, 2000);
        } else {
            showError(data.message || 'Erreur lors de l\'enregistrement');
            setLoading(false);
        }
    } catch (error) {
        console.error('Erreur:', error);
        showError('Erreur de connexion au serveur');
        setLoading(false);
    }
}

// Initialiser PayPal
function initPayPal() {
    if (typeof paypal === 'undefined') {
        showError('PayPal non disponible');
        return;
    }
    
    const paypalContainer = document.getElementById('paypal-button-container');
    paypalContainer.innerHTML = '';
    
    // Calculer le montant en USD (1 USD ≈ 600 XOF)
    const amountUSD = (paymentAmount / 600).toFixed(2);
    
    paypal.Buttons({
        style: {
            layout: 'vertical',
            color: 'gold',
            shape: 'rect',
            label: 'paypal'
        },
        
        createOrder: function(data, actions) {
            return actions.order.create({
                purchase_units: [{
                    amount: {
                        currency_code: 'USD',
                        value: amountUSD
                    },
                    description: `Paiement tontine - ${paymentData.tontineName}`
                }],
                application_context: {
                    shipping_preference: 'NO_SHIPPING'
                }
            });
        },
        
        onApprove: async function(data, actions) {
            setLoading(true);
            
            try {
                const details = await actions.order.capture();
                
                if (details.status === 'COMPLETED') {
                    await savePayment({
                        tontine_id: paymentData.tontineId,
                        amount: paymentAmount,
                        method: 'paypal',
                        transaction_id: details.id,
                        payer_email: details.payer.email_address
                    });
                } else {
                    showError('Paiement PayPal échoué');
                    setLoading(false);
                }
            } catch (error) {
                console.error('Erreur PayPal:', error);
                showError('Erreur lors du traitement PayPal');
                setLoading(false);
            }
        },
        
        onError: function(err) {
            console.error('Erreur PayPal:', err);
            showError('Erreur PayPal');
            setLoading(false);
        },
        
        onCancel: function(data) {
            console.log('Paiement PayPal annulé');
            setLoading(false);
        }
    }).render('#paypal-button-container');
}

// Sauvegarder le paiement
async function savePayment(paymentData) {
    try {
        const response = await fetch('/tontine/save-payment', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(paymentData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess('Paiement effectué avec succès !');
            setTimeout(() => {
                closePaymentModal();
                // Recharger la page ou mettre à jour l'interface
                if (data.redirect_url) {
                    window.location.href = data.redirect_url;
                } else {
                    window.location.reload();
                }
            }, 1500);
        } else {
            showError(data.message || 'Erreur lors de l\'enregistrement');
            setLoading(false);
        }
    } catch (error) {
        console.error('Erreur:', error);
        showError('Erreur de connexion au serveur');
        setLoading(false);
    }
}

// Utilitaires
function setLoading(isLoading) {
    const confirmBtn = document.getElementById('confirm-payment-btn');
    const confirmText = document.getElementById('confirm-text');
    const loadingText = document.getElementById('loading-text');
    
    if (isLoading) {
        confirmBtn.disabled = true;
        confirmText.classList.add('hidden');
        loadingText.classList.remove('hidden');
    } else {
        confirmBtn.disabled = false;
        confirmText.classList.remove('hidden');
        loadingText.classList.add('hidden');
    }
}

function showError(message) {
    const errorDiv = document.getElementById('payment-error');
    const errorText = errorDiv.querySelector('div');
    errorText.textContent = message;
    errorDiv.classList.remove('hidden');
    
    setTimeout(() => {
        errorDiv.classList.add('hidden');
    }, 5000);
}

function hideError() {
    document.getElementById('payment-error').classList.add('hidden');
}

function showSuccess(message) {
    // Créer une notification de succès
    const notification = document.createElement('div');
    notification.className = 'fixed top-4 right-4 z-50 bg-green-500 text-white px-4 py-3 rounded-lg shadow-lg flex items-center animate-slideIn';
    notification.innerHTML = `
        <i class="fa-solid fa-check-circle mr-2"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

function resetPaymentUI() {
    setLoading(false);
    hideError();
}

// Fonction pour gérer les clics sur "Payer" dans la liste des tontines
function handlePaymentClick(tontineId, tontineName, amount, dueDate, progress, totalPoints, paidPoints) {
    openTontinePayment(
        tontineId,
        tontineName,
        amount,
        dueDate,
        progress,
        totalPoints,
        paidPoints
    );
}