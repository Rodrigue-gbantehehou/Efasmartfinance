/**
 * Fonction améliorée pour télécharger la carte de tontine
 * Corrige le problème de fichier 0Ko sur mobile
 */
async function downloadCard(tontineCode = '') {
    // Essayer de récupérer le bouton via l'événement (recommandé) ou via le sélecteur
    let button = (window.event && window.event.currentTarget) ? window.event.currentTarget : document.querySelector('button[onclick^="downloadCard"]:not(.hidden)');
    if (!button || button.tagName !== 'BUTTON') {
        button = document.querySelector('button[onclick^="downloadCard"]');
    }

    try {
        // Afficher le chargement
        if (button) {
            button.disabled = true;
            button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Préparation...';
        }

        // Charger html2canvas si nécessaire
        if (typeof html2canvas === 'undefined') {
            await new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js';
                script.onload = resolve;
                script.onerror = reject;
                document.head.appendChild(script);
            });
        }

        // Sélectionner la carte - priorité à celle qui est visible
        let cardContainer = null;
        const desktopCard = document.querySelector('#serviceCardContent');
        const modalCard = document.querySelector('#cardModal .relative[style*="aspect-ratio"]');

        // Détection de visibilité plus robuste
        const isVisible = (el) => {
            if (!el) return false;
            const style = window.getComputedStyle(el);
            return style.display !== 'none' && style.visibility !== 'hidden' && el.offsetParent !== null;
        };

        if (isVisible(modalCard)) {
            cardContainer = modalCard;
        } else if (isVisible(desktopCard)) {
            cardContainer = desktopCard;
        } else {
            // Fallback
            cardContainer = modalCard || desktopCard || document.querySelector('#cardModal .relative');
        }

        if (!cardContainer) {
            throw new Error('Conteneur de carte non trouvé dans le DOM');
        }

        // Petit délai pour s'assurer que le rendu est stable
        await new Promise(resolve => setTimeout(resolve, 200));

        // Précharger toutes les images
        const images = cardContainer.querySelectorAll('img');
        const imagePromises = Array.from(images).map(img => {
            if (img.complete && img.naturalWidth !== 0) return Promise.resolve();
            return new Promise((resolve) => {
                img.onload = resolve;
                img.onerror = resolve;
                setTimeout(resolve, 5000);
            });
        });
        await Promise.all(imagePromises);

        // Sauvegarder et remplacer les dégradés
        const gradientElements = cardContainer.querySelectorAll('.bg-gradient-to-br, .bg-gradient-to-r');
        const originalStyles = [];

        gradientElements.forEach((el, index) => {
            originalStyles[index] = el.style.background;
            if (el.classList.contains('bg-gradient-to-br')) {
                el.style.background = '#15803d';
            } else if (el.classList.contains('from-yellow-400')) {
                el.style.background = '#fbbf24';
            }
        });

        // Options optimisées pour html2canvas
        const options = {
            scale: 3,
            backgroundColor: '#ffffffff',
            useCORS: true,
            allowTaint: true,
            logging: false,
            imageTimeout: 15000,
            removeContainer: true
        };

        // Générer le canvas
        const canvas = await html2canvas(cardContainer, options);

        // Restaurer les styles
        gradientElements.forEach((el, index) => {
            el.style.background = originalStyles[index];
        });

        if (!canvas || canvas.width === 0) {
            throw new Error('Échec de la génération du rendu (canvas vide)');
        }

        // Convertir en blob pour un meilleur support mobile
        canvas.toBlob((blob) => {
            try {
                if (!blob) {
                    // Fallback to DataURL if Blob fails
                    const dataUrl = canvas.toDataURL('image/png');
                    const link = document.createElement('a');
                    link.download = tontineCode ? `carte-${tontineCode}.png` : `carte-tontine.png`;
                    link.href = dataUrl;
                    link.click();
                } else {
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.download = tontineCode ? `carte-${tontineCode}.png` : `carte-tontine.png`;
                    link.href = url;
                    if (window.innerWidth < 768) link.target = '_blank';
                    link.click();
                    setTimeout(() => URL.revokeObjectURL(url), 500);
                }

                // Feedback de succès
                if (button) {
                    button.innerHTML = '<i class="fa-solid fa-check"></i> Téléchargé !';
                    button.classList.add('bg-green-600');
                    setTimeout(() => {
                        button.innerHTML = '<i class="fa-solid fa-download"></i> Télécharger la carte';
                        button.classList.remove('bg-green-600');
                        button.disabled = false;
                    }, 2000);
                }
            } catch (e) {
                console.error('Erreur lors de la création du lien:', e);
                alert('Erreur lors de la création du fichier. Essayez de rester sur la page.');
            }
        }, 'image/png', 1.0);

    } catch (error) {
        console.error('Erreur downloadCard:', error);
        alert('Erreur: ' + (error.message || 'Le téléchargement a échoué.'));

        if (button) {
            button.innerHTML = '<i class="fa-solid fa-download"></i> Télécharger la carte';
            button.disabled = false;
        }
    }
}
