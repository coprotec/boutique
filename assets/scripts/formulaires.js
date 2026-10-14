// Case de consentement du récapitulatif : envoyée sans être cochée, le bloc passe en erreur et revient à l'écran,
// à la place de la bulle du navigateur, peu visible. L'erreur disparaît dès que la case est cochée.
document.querySelectorAll('.consentement input[required]').forEach((caseACocher) => {
    const bloc = caseACocher.closest('.consentement');

    caseACocher.addEventListener('invalid', (event) => {
        event.preventDefault();
        bloc.classList.add('consentement--erreur');
        caseACocher.setAttribute('aria-invalid', 'true');
        bloc.scrollIntoView({ behavior: 'smooth', block: 'center' });
        caseACocher.focus({ preventScroll: true });
    });

    caseACocher.addEventListener('change', () => {
        if (caseACocher.checked) {
            bloc.classList.remove('consentement--erreur');
            caseACocher.removeAttribute('aria-invalid');
        }
    });
});
