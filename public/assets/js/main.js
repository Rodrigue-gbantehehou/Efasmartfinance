// Gestion du menu mobile

// Elements
const openBtn = document.getElementById('openMobileMenu');
const closeBtn = document.getElementById('closeMobileMenu');
const menu = document.getElementById('mobileMenu');
const overlay = document.getElementById('mobileMenuOverlay');

// Fonction pour ouvrir le menu
function openMenu() {
  menu.classList.remove('translate-x-full');
  overlay.classList.remove('hidden');
  openBtn?.setAttribute('aria-expanded', 'true');
  document.body.style.overflow = 'hidden';
}

// Fonction pour fermer le menu
function closeMenu() {
  menu.classList.add('translate-x-full');
  overlay.classList.add('hidden');
  openBtn?.setAttribute('aria-expanded', 'false');
  document.body.style.overflow = '';
}

// Événements
document.addEventListener('DOMContentLoaded', function() {
  if (openBtn) openBtn.addEventListener('click', openMenu);
  if (closeBtn) closeBtn.addEventListener('click', closeMenu);
  if (overlay) overlay.addEventListener('click', closeMenu);
  window.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeMenu(); });
});

// Année en cours pour le footer
document.addEventListener('DOMContentLoaded', function() {
  const yearElements = document.querySelectorAll('[id^="year"]');
  const currentYear = new Date().getFullYear();
  yearElements.forEach(el => el.textContent = currentYear);
});
