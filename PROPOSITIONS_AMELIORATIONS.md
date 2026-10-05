# Propositions d'améliorations (revue du 2026-10-05)

Propositions issues d'une lecture du code (catalogue, fiche formation, confirmation, cahier des charges). **Rien n'est implémenté** : le CLAUDE.md demande de ne pas deviner le périmètre. Chaque point indique s'il est déjà dans le périmètre v1 (§5) ou s'il demande une décision.

## A. Dans le périmètre v1, petit effort

1. **Filtre « ville » au catalogue** (§5.7 le prévoit : formation, ville, mois ; seuls formation et mois existent).
   `SessionRepository::findReservables` + un `<select>` dans `catalogue/index.html.twig`, sur le modèle du filtre mois.
2. **Alerte « dernières places »** plus visible : le seuil `restantes <= 3` existe déjà (`places--peu`), il manque le libellé (« Plus que 2 places ») pour inciter à réserver.
3. **Fiche formation : prochaine session mise en avant** et bouton « Réserver la prochaine session » dans le hero, pour les liens arrivant du site vitrine.
4. **Confirmation : « Ajouter au calendrier »** (fichier .ics généré par session) et bouton « Imprimer le récapitulatif ». Utile aux clients qui inscrivent plusieurs salariés et doivent le transmettre en interne.
5. **Confirmation : bouton « Copier l'IBAN / le motif »** pour le virement, avec le montant et la référence `BTQ-…` déjà affichés. Réduit les erreurs de motif, donc le rapprochement par la compta.
6. **Page de réservation : conserver la saisie** si le client revient en arrière depuis le paiement (brouillon en `sessionStorage` côté Vue, avec `try/catch`). Pas de données sensibles : exclure le n° de sécurité sociale.
7. **Meta/SEO et partage** : `<title>` et `og:` par formation (la fiche a déjà un slug stable), utile pour les liens depuis le site vitrine.

## B. Hors périmètre v1 explicite, à valider (§5.8 / questions ouvertes)

8. **Copier un participant / import de plusieurs participants** (CSV ou « dupliquer le précédent ») pour les sociétés qui inscrivent 5 à 10 salariés (dépend de QE-3, nombre maximum).
9. **Mail de relance** pour une commande abandonnée à l'étape paiement (blocage 30 min, QE-7) : un seul mail « vos places seront libérées dans X min ».
10. **Demande de devis / convention avant paiement** pour les entreprises qui ne peuvent pas payer à la réservation (lié à QE-14/QE-15, virement différé).
11. **Liste d'attente** sur une session complète (un formulaire email), au lieu du seul message « Complet ».
12. **Affichage HT/TTC commutable** particulier/professionnel (QE-2, QE-11).

## C. Design / qualité

- Le tunnel est lisible. Pistes : contraste du badge `places--peu`, taille des cibles tactiles du bouton « Réserver » sur mobile, état de chargement du bouton de soumission pour éviter les doubles envois.
- `catalogue/index.html.twig` : `placesRestantes` est calculé pour toutes les sessions, y compris celles masquées par le filtre ; sans impact tant que le catalogue reste petit.
- `moisDisponibles` charge `findReservables` une seconde fois sans filtre ; une requête `DISTINCT` sur le mois suffirait.
- Tests : `tests/` ne contient que `bootstrap.php`. Les règles de places concurrentes (§5.4) et la signature Monetico méritent des tests en priorité.

## Ordre conseillé

1 → 5 → 4 → 3 (rapides, sans nouvelle règle métier), puis trancher 8, 9 et 11 avec le service formation.
