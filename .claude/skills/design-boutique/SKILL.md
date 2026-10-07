---
name: design-boutique
description: Direction visuelle et règles d'interface de la boutique de formations COPROTEC (style « technique / atelier » dérivé du logo : bleu marine, jaune de marquage, titres condensés). À utiliser dès qu'on crée, modifie ou revoit une page, un template Twig, un composant Vue, du CSS (`assets/styles/`), un email HTML ou tout élément visible par le client de la boutique — même pour un petit ajout (bouton, message d'erreur, nouvelle page) ou quand on demande de « rendre plus joli », « moderniser », « refaire le design », « moins code IA ».
---

# Design de la boutique COPROTEC

La boutique vend des formations techniques (fluides frigorigènes, pompes à chaleur, habilitation électrique, gaz…) à des **artisans et entreprises du bâtiment**. Les visiteurs sont des chefs d'entreprise ou des secrétaires qui inscrivent des salariés : ils veulent trouver une date, voir le prix et réserver vite. L'interface doit inspirer la même confiance qu'un bon fournisseur professionnel : nette, précise, lisible sur un téléphone de chantier.

Direction retenue : **technique / atelier**. Elle s'inspire du logo (lettres condensées aux angles adoucis, rond jaune, bleu marine) et du vocabulaire de la signalétique et du marquage de chantier, pas du site vitrine coprotec.net, jugé vieillissant.

## Ce qu'on veut éviter : l'aspect « généré par IA »

Le premier jet de la boutique avait tous les tics des interfaces générées par défaut. On les retire, parce qu'ils rendent le site interchangeable avec n'importe quel SaaS et ne disent rien de COPROTEC :

- **bordure colorée épaisse sur un seul côté** (`border-left: 3px solid …`) pour « accentuer » un bloc, une alerte, un participant ou une citation : c'est le tic le plus reconnaissable, il est proscrit partout ;
- dégradés radiaux ou en diagonale dans les bandeaux et les badges, trames en grille décoratives ;
- en-tête en verre dépoli (`backdrop-filter: blur`) ;
- ombres douces doublées sur chaque carte, cartes qui « flottent » au survol (`translateY`), bloc qui chevauche le bandeau avec une marge négative ;
- grands arrondis partout (12–16 px), pastilles et badges arrondis multipliés ;
- carrés pastel numérotés devant chaque titre de section (« 1 », « 2 », « € ») ;
- surtitres en petites capitales espacées au-dessus des titres, libellés en capitales espacées ;
- bordures en pointillés (`dashed`) pour séparer ou pour un bouton « ajouter » ;
- icônes décoratives devant chaque information, flèche dans chaque bouton, emojis ;
- fonds teintés pastel pour tout ce qui est « sélectionné » ou « info » ;
- phrases d'accroche creuses (« Votre avenir commence ici »), textes qui répètent ce que l'interface montre déjà.

Quand tu hésites sur un effet, demande-toi s'il aide le client à choisir une session ou à remplir le formulaire. Sinon, retire-le.

## Identité

### Couleurs (tokens dans `assets/styles/app.css`, `:root`)

| Token | Valeur | Usage |
|---|---|---|
| `--c-marine` | `#0B4389` | Couleur de structure : en-tête, titres, boutons principaux, liens. Couleur du « TEC » du logo. |
| `--c-marine-fonce` | `#072B59` | Survol, pied de page, texte sur jaune. |
| `--c-jaune` | `#F9B620` | **Marquage**, utilisé avec parcimonie : un seul bouton d'action clé par écran (« Réserver », « Payer »), barre de repère, étape active. |
| `--c-gris-logo` | `#BCBDC0` | Uniquement décoratif (filets, séparateurs). Jamais pour du texte : contraste insuffisant. |
| `--c-texte` | `#1A1F26` | Texte courant. |
| `--c-texte-doux` | `#56606B` | Texte secondaire (contraste AA sur blanc). |
| `--c-fond` | `#F4F5F6` | Fond de page, gris neutre très légèrement froid. |
| `--c-surface` | `#FFFFFF` | Blocs de contenu. |
| `--c-trait` | `#D9DCE0` | Bordures 1 px. |
| `--c-ok` / `--c-alerte` / `--c-erreur` | `#1E7B4F` / `#A85A00` / `#B42318` | Places disponibles / peu de places / erreurs. |

Règles de contraste à respecter : texte marine ou foncé sur jaune (jamais blanc sur jaune) ; jamais de jaune comme couleur de texte sur fond blanc ; le bleu vif `#0071C2` de l'ancienne boutique n'est plus utilisé.

### Typographie

- **Titres : Barlow Condensed** (600, 700), rappel direct des lettres condensées du logo. Titres courts, casse normale ; capitales réservées aux étiquettes très courtes (date « 12 NOV », code formation).
- **Texte : Barlow** (400, 500, 600), même famille, lisible en petite taille.
- Chiffres tabulaires (`font-variant-numeric: tabular-nums`) pour prix, dates, places.
- Polices **auto-hébergées** via npm (`@fontsource/barlow`, `@fontsource/barlow-condensed`), importées dans `assets/app.js`. Pas de chargement depuis Google Fonts : la boutique collecte des données personnelles et on évite d'envoyer l'IP des visiteurs à un tiers (RGPD).
- Échelle : corps 16 px (1 rem) ; titres de page `clamp(1.9rem, 4vw, 2.75rem)` ; titres de bloc 1.35–1.5 rem ; étiquettes 0.8 rem.

### Formes

- Rayon **4 px** pour boutons, champs, blocs ; 2 px pour les étiquettes. Les angles adoucis mais nets rappellent les lettres du logo.
- Bordures **1 px `--c-trait`, identiques sur les quatre côtés** pour délimiter ; pas d'ombre, sauf une ombre franche et unique pour un élément réellement superposé (menu déroulant).
- L'identité vient de la **typographie condensée** et du **jaune réservé à l'action clé**, pas d'ornements. Pour séparer, préférer un filet horizontal plein (1 px) ou de l'espace.
- Le **rond jaune** du logo peut servir de motif ponctuel (pastille de l'étape active du tunnel), jamais en grand décor de fond.

### Mise en page

- Conteneur max 1140 px ; marges latérales 16 px sur mobile.
- Hiérarchie par la typographie et l'espacement, pas par les couleurs de fond. Fond de page gris clair, contenu sur blocs blancs bordés.
- Bandeau de page : fond marine uni, titre condensé blanc. Hauteur modeste : le catalogue doit apparaître sans défiler sur un écran de portable. Le contenu commence sous le bandeau, sans le chevaucher.
- Densité **moyenne à forte** : un client compare des dates et des prix ; éviter les grands vides et les cartes surdimensionnées.

## Composants

### Liste des sessions (catalogue, fiche formation)

C'est l'écran le plus important. Chaque session est une **ligne** d'une liste, pas une carte flottante :

- à gauche, un **bloc date** : jour en Barlow Condensed gros, mois en capitales (« NOV »), sur fond marine ou simplement séparé par un filet vertical ;
- au centre, code formation (étiquette sobre : texte marine, bordure 1 px, pas de police monospace) + intitulé + lieu et durée en texte secondaire, sans icône devant chaque élément (une icône de lieu tolérée si elle aide vraiment) ;
- à droite, prix HT en gras tabulaire, mention TTC en petit, places restantes, bouton « Réserver ».
- Lignes regroupées dans un seul bloc bordé et séparées par un filet ; survol = fond `--c-fond`, sans déplacement ni ombre.
- Session complète : ligne atténuée, bouton remplacé par « Complet », sans masquer l'information.
- Sur mobile, la ligne se replie : date + intitulé en haut, prix et bouton sur une rangée en dessous.

### Boutons

- **Action clé** (une par écran, dans le tunnel : Continuer, Confirmer, Payer) : classe `.btn-action`, fond jaune, texte marine foncé, Barlow 600. Dans une liste où l'action se répète (bouton « Réserver » de chaque session), utiliser le bouton principal marine : une colonne de boutons jaunes crie.
- **Principal** : fond marine, texte blanc.
- **Secondaire** : contour marine 1 px, fond transparent.
- Hauteur minimum 44 px (cible tactile). Libellés à l'infinitif, précis (« Réserver 3 places », pas « Valider »).

### Formulaires (tunnel de réservation, composant Vue `ReservationForm`)

- Libellé au-dessus du champ, en 500, toujours visible (pas de placeholder en guise de libellé).
- Champs 44 px de haut, bordure 1 px, focus = bordure marine 2 px + halo léger marine.
- Erreur : bordure `--c-erreur`, message en dessous en texte (pas seulement la couleur), annoncé via `aria-describedby`.
- Facultatif signalé par « (facultatif) » plutôt que d'étoiler les champs obligatoires.
- Sections (Participants, Entreprise, Coordonnées, Financement) : simple titre condensé, sans numéro décoratif ; la progression du tunnel est déjà numérotée dans le bandeau.
- Participants : chacun dans un bloc (card) bordé 1 px sur tout le contour, fond gris très clair pour le détacher de la section blanche, sous-titre « Participant 2 » et bouton « Retirer » en en-tête. Jamais de bordure colorée sur un seul côté.
- Choix (mode de paiement, financement) : options en blocs bordés ; sélection = bordure marine 2 px sur tout le contour, fond blanc.
- Résumé de commande à droite sur grand écran ; il se place sous le formulaire sur mobile.

### Messages et états

- Alertes : bloc bordé 1 px sur tout le contour (couleur d'état atténuée), fond très clair, titre en gras. Pas de barre latérale.
- États vides : une phrase utile + un moyen de contact (téléphone du service formation).
- Textes : vouvoiement, phrases courtes, vocabulaire métier exact (session, participant, prise en charge, OPCO). Pas d'exclamation ni d'emoji.

## Contraintes techniques du projet

- **Bootstrap 5 reste** la base (grille, formulaires, utilitaires). On le surcharge par les variables `--bs-*` et les tokens ci-dessus dans `assets/styles/app.css`, plutôt que d'ajouter un autre framework (pas de Tailwind). Le projet doit rester sobre (voir `CLAUDE.md`).
- Vue 3 uniquement pour les composants interactifs montés sur `data-vue` ; le reste est en Twig.
- Accessibilité : contraste AA, focus visible, cibles tactiles 44 px, `prefers-reduced-motion` respecté (transitions courtes, 150 ms, couleur uniquement).
- Mobile d'abord : vérifier chaque écran à 375 px de large.
- Emails HTML (`templates/emails/`) : mêmes couleurs, polices système (les polices web ne s'affichent pas dans la plupart des messageries).

## Méthode

1. Lire le template ou le composant concerné et `assets/styles/app.css` pour réutiliser les tokens et classes existants.
2. Appliquer les règles ci-dessus ; si un besoin n'est pas couvert, choisir la solution la plus simple cohérente avec la direction atelier et l'ajouter à ce fichier pour la suite.
3. Reconstruire le front (`npm run dev`) et vérifier l'écran dans le navigateur, en grand écran et à 375 px.
4. Relire la liste « Ce qu'on veut éviter » sur le résultat avant de conclure.
