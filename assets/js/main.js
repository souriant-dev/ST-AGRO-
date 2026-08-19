// ST-AGRO — Interactions front-end partagées
document.addEventListener('DOMContentLoaded', () => {
    // Menu burger — page publique
    const burger = document.querySelector('.burger');
    const navPublique = document.querySelector('.nav-publique');
    if (burger && navPublique) {
        burger.addEventListener('click', () => navPublique.classList.toggle('ouverte'));
    }

    // Menu burger — espace applicatif
    const burgerApp = document.querySelector('.menu-burger-app');
    const sidebar = document.querySelector('.barre-laterale');
    if (burgerApp && sidebar) {
        burgerApp.addEventListener('click', () => sidebar.classList.toggle('ouverte'));
        document.addEventListener('click', (e) => {
            if (sidebar.classList.contains('ouverte') && !sidebar.contains(e.target) && !burgerApp.contains(e.target)) {
                sidebar.classList.remove('ouverte');
            }
        });
    }

    // Fermeture auto des messages flash
    const alerte = document.querySelector('.alerte');
    if (alerte) {
        setTimeout(() => { alerte.style.transition = 'opacity 400ms'; alerte.style.opacity = '0'; setTimeout(() => alerte.remove(), 400); }, 5000);
    }

    // Modales génériques : data-ouvrir-modale="id" / data-fermer-modale
    document.querySelectorAll('[data-ouvrir-modale]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById(btn.dataset.ouvrirModale)?.classList.add('ouverte');
        });
    });
    document.querySelectorAll('[data-fermer-modale]').forEach(btn => {
        btn.addEventListener('click', () => btn.closest('.fond-modale')?.classList.remove('ouverte'));
    });
    document.querySelectorAll('.fond-modale').forEach(fond => {
        fond.addEventListener('click', (e) => { if (e.target === fond) fond.classList.remove('ouverte'); });
    });

    // Scroll reveal léger pour la page publique
    const reveals = document.querySelectorAll('.fonction-carte, .role-carte');
    if (reveals.length && 'IntersectionObserver' in window) {
        const observateur = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                    observateur.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });
        reveals.forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(16px)';
            el.style.transition = 'opacity 500ms ease, transform 500ms ease';
            observateur.observe(el);
        });
    }
});

/** Marque une notification comme lue via fetch, puis redirige si un lien est fourni */
function marquerNotificationLue(id, url) {
    fetch('/agriculteur/api_notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=marquer_lue&id=' + encodeURIComponent(id)
    }).finally(() => { if (url) window.location.href = url; });
}
