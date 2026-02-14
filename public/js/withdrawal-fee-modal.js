/**
 * Gestion du modal de choix des frais de retrait
 * Utilisé dans les pages show.html.twig et tontines.html.twig
 */

// Variables globales pour stocker les informations de la tontine sélectionnée
let currentTontineId = null;
let currentFeesDue = 0;
let currentFrequency = '';

/**
 * Affiche le modal de choix des frais ou redirige directement selon le contexte
 * @param {number} tontineId - ID de la tontine
 * @param {number} feesDue - Montant des frais dus
 * @param {string} frequency - Fréquence de la tontine (daily, weekly, monthly)
 * @param {number|null} grossSavings - (Optionnel) Épargne brute
 * @param {number|null} netAmount - (Optionnel) Net à recevoir
 */
function initiateWithdrawalFlow(tontineId, feesDue, frequency, grossSavings = null, netAmount = null) {
    currentTontineId = tontineId;
    currentFeesDue = feesDue;
    currentFrequency = frequency;

    // Si tontine journalière, bypass complet du modal de frais
    if (frequency === 'daily') {
        window.location.href = `/dashboard/withdrawals/request/${tontineId}`;
        return;
    }

    if (feesDue > 0) {
        // Mettre à jour le montant des frais dans le modal
        document.getElementById('modalFeeAmount').textContent = `- ${feesDue.toLocaleString('fr-FR')} FCFA`;

        // Gérer l'affichage optionnel de l'épargne brute
        const grossSavingsContainer = document.getElementById('modalGrossSavingsContainer');
        if (grossSavings !== null && grossSavingsContainer) {
            document.getElementById('modalGrossSavings').textContent = `${grossSavings.toLocaleString('fr-FR')} FCFA`;
            grossSavingsContainer.classList.remove('hidden');
            grossSavingsContainer.classList.add('flex');
        } else if (grossSavingsContainer) {
            grossSavingsContainer.classList.add('hidden');
            grossSavingsContainer.classList.remove('flex');
        }

        // Gérer l'affichage optionnel du net à recevoir
        const netAmountContainer = document.getElementById('modalNetAmountContainer');
        if (netAmount !== null && netAmountContainer) {
            document.getElementById('modalNetAmount').textContent = `${netAmount.toLocaleString('fr-FR')} FCFA`;
            netAmountContainer.classList.remove('hidden');
        } else if (netAmountContainer) {
            netAmountContainer.classList.add('hidden');
        }

        // Afficher le modal
        const modal = document.getElementById('feeChoiceModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    } else {
        // Pas de frais, redirection directe
        window.location.href = `/dashboard/withdrawals/request/${tontineId}`;
    }
}

/**
 * Option 1 : Payer les frais maintenant
 */
function choosePayFeesNow() {
    closeFeeModal();
    // Rediriger vers la page dédiée au paiement des frais
    window.location.href = `/dashboard/tontines/${currentTontineId}/pay-fees`;
}

/**
 * Option 2 : Déduire les frais du montant de retrait
 */
function chooseDeductFees() {
    closeFeeModal();
    // Rediriger vers le formulaire de retrait
    window.location.href = `/dashboard/withdrawals/request/${currentTontineId}`;
}

/**
 * Redirige directement vers la page de paiement des frais
 * @param {number} tontineId - ID de la tontine
 */
function openFeePaymentModal(tontineId) {
    window.location.href = `/dashboard/tontines/${tontineId}/pay-fees`;
}

/**
 * Ferme le modal de choix des frais
 */
function closeFeeModal() {
    const modal = document.getElementById('feeChoiceModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = '';
}
