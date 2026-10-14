// Mesure d'audience Matomo (QE-36) et bandeau de consentement (templates/_bandeau_cookies.html.twig).
//
// Matomo tourne toujours, mais en mode « requireCookieConsent » : sans accord, il ne dépose aucun cookie et compte
// des visites anonymes (exemption CNIL, à condition de régler l'instance : IP anonymisée, pas d'User ID, cf.
// CAHIER_DES_CHARGES.md). Avec accord, il suit le visiteur d'une page à l'autre (parcours, abandons du formulaire).
// Le choix est gardé 6 mois dans le cookie « mesure_audience » (oui / non), lu aussi par Twig pour ne pas
// réafficher le bandeau.
const bandeau = document.getElementById('bandeau-cookies');

if (bandeau) {
    const COOKIE = 'mesure_audience';
    const SIX_MOIS = 60 * 60 * 24 * 182;
    const choix = document.cookie.split('; ').find((c) => c.startsWith(`${COOKIE}=`))?.split('=')[1] ?? null;

    const _paq = (window._paq = window._paq || []);
    _paq.push(['requireCookieConsent']);
    if (choix === 'oui') _paq.push(['setCookieConsentGiven']);
    _paq.push(['trackPageView']);
    _paq.push(['enableLinkTracking']);

    const url = bandeau.dataset.matomoUrl.replace(/\/?$/, '/');
    _paq.push(['setTrackerUrl', `${url}matomo.php`]);
    _paq.push(['setSiteId', bandeau.dataset.matomoSite]);
    const script = document.createElement('script');
    script.async = true;
    script.src = `${url}matomo.js`;
    document.head.append(script);

    bandeau.addEventListener('click', (event) => {
        const bouton = event.target.closest('[data-cookies]');
        if (!bouton) return;
        const accepte = bouton.dataset.cookies === 'oui';
        const secure = window.location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = `${COOKIE}=${accepte ? 'oui' : 'non'}; max-age=${SIX_MOIS}; path=/; SameSite=Lax${secure}`;
        // Refus : Matomo efface ses cookies et repasse en mesure anonyme.
        _paq.push([accepte ? 'setCookieConsentGiven' : 'forgetCookieConsentGiven']);
        bandeau.hidden = true;
    });

    // Lien « Gestion des cookies » du pied de page : rouvre le bandeau pour changer d'avis.
    document.querySelectorAll('[data-cookies-ouvrir]').forEach((lien) => lien.addEventListener('click', () => {
        bandeau.hidden = false;
        bandeau.querySelector('[data-cookies="oui"]').focus();
    }));
}

