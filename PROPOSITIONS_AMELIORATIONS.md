# Propositions d'amélioration (revue du code, 2026-09-30)

Revue automatique du catalogue, de la fiche formation et de la page de confirmation. Rien n'est implémenté : conformément à `CLAUDE.md`, chaque ajout hors §5.2 du cahier des charges attend une validation. Classement par rapport gain / effort.

## A. Écarts avec le cahier des charges (à faire en priorité)

1. **Filtre « ville » manquant au catalogue.** Le §5.7 prévoit « formation, ville, mois » ; `CatalogueController::index` ne gère que formation et mois. Ajouter un `<select name="ville">` alimenté par `Session::lieu` (mêmes mécanismes que `moisDisponibles`). Effort : ~1 h. Utile aux clients qui cherchent près de chez eux.
2. **`<meta http-equiv="refresh">` dans le `body`** (`templates/commande/afficher.html.twig`, statut `paiement_en_cours`). Cette balise n'est valable que dans `<head>` ; certains navigateurs l'ignorent, et la page ne se met alors plus à jour après le paiement CB. Déplacer dans un bloc `head` de `base.html.twig`.
3. **Coordonnées de paiement codées en dur dans le template** (BIC, adresse du chèque, « sous 10 jours », téléphone `03 69 28 89 00`, `contact@coprotec.net`). Les passer en paramètres de configuration marqués `// PROVISOIRE (QE-14/QE-15)` : ces délais sont encore des questions ouvertes.

## B. Petits ajouts utiles aux clients (à valider avant de coder)

4. **« Ajouter à mon agenda » (.ics)** sur la page de confirmation et dans l'email de récapitulatif : un fichier `.ics` par session (dates, lieu, numéro de commande). Aucune dépendance, ~1 h. Réduit les oublis et les appels au service formation.
5. **Données structurées `schema.org/Event`** (JSON-LD) sur `/formation/{slug}` : une session = un `Event` avec dates, lieu, prix, disponibilité. Les sessions ressortent dans Google avec date et prix ; coût : un bloc Twig.
6. **Session complète : proposer une alternative.** Aujourd'hui la carte affiche « Complet » sans action. Options, de la plus sobre à la plus lourde : (a) lien `mailto:` pré-rempli vers le service formation (référence + date) ; (b) afficher la prochaine session de la même formation ; (c) liste d'attente par email (nouvelle entité, donc hors périmètre v1, à ne faire que sur demande).
7. **Copier l'IBAN / la référence en un clic** et bouton « Imprimer » sur la confirmation virement/chèque (Vue ciblé ou 5 lignes de JS). Les clients recopient la référence à la main, source d'erreurs de rapprochement.
8. **Seuil « peu de places »** (`restantes <= 3`, `_session.html.twig`) : le mettre en paramètre ; il est aujourd'hui figé dans la vue.

## C. Design / accessibilité

9. Indicateur d'étapes (`reservation/_entete`) : ajouter `aria-current="step"` et un libellé lisible par lecteur d'écran.
10. Page « paiement en cours » : `role="status"` / `aria-live="polite"` sur le message, pour que le changement d'état soit annoncé.
11. Catalogue mobile : appliquer le filtre dès le changement de `select` (petit `onchange`, avec le bouton conservé en repli sans JS) ; afficher le prix TTC en plus du HT selon la réponse à QE-11 (le grand public raisonne en TTC).

## Recommandation

Faire d'abord **A1–A3** (écarts et robustesse), puis **B4 et B5** (gain client réel, sans dépendance ni changement de modèle). B6(c) et toute liste d'attente restent à décider par les équipes.
