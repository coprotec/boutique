# Cahier des charges — Refonte "Boutique" COPROTEC (MVP)

> Statut (2026-10-02) : cadrage technique v1 bouclé, doc API SmartOF v2 étudiée, lot 1 (socle) réalisé. Les points encore ouverts sont des questions aux équipes (**QE-n**) et à l'éditeur SmartOF (**QSO-n**), regroupées dans [`QUESTIONS_EQUIPES.md`](QUESTIONS_EQUIPES.md). Ce cahier y renvoie par leur numéro.

## 1. Contexte

Le système de réservation de formations ("boutique") de COPROTEC existe aujourd'hui en trois variantes :

| Dossier | Description | Statut |
|---|---|---|
| `../boutique-old` | Version historique en PHP procédural/MVC maison, sans framework ni ORM | Legacy, en production, sert de référence métier. Dépôt Git renommé `boutique-old` |
| `../boutique-sf/boutique` | Refonte Symfony 7 + Vue 3 + Doctrine ORM | Jugée trop évoluée / complexe pour les besoins actuels |
| `boutique` (ce dossier) | Nouvelle refonte, MVP sobre | En cours de cadrage |

L'objectif est de repartir sur une base **technique volontairement plus simple**, en reprenant sélectivement la logique métier de `boutique-old` et la structure Symfony/Vue de `boutique-sf`, sans hériter de leur complexité.

**Changement majeur : l'ERP Navision (NAV / wsformation) est abandonné**, remplacé par **SmartOF**, qui regroupe les formations, les sessions, les inscriptions et les données clients. SmartOF contient aussi des formations **d'autres organismes**, réservées par d'autres canaux : la boutique ne doit afficher et vendre **que les formations COPROTEC** (cf. §5.2).

## 2. Objectifs du projet

- **But de la v1 : afficher les formations COPROTEC proposées et permettre de les réserver et de les payer.** Le reste (gestion des sessions, suivi des inscriptions, facturation) vit dans SmartOF.
- Garder une base de code simple à maintenir et à faire évoluer (peu d'abstractions, pas de dépendances superflues).
- Remplacer l'intégration Navision/wsformation par l'API SmartOF v2.
- Isoler ce projet dans son propre dépôt Git, indépendant de `boutique-old` et `boutique-sf`.

## 3. Hors périmètre

- `boutique-old` et `boutique-sf` restent en l'état, non modifiés par ce projet.
- Aucune fonctionnalité de `boutique-sf` n'est reprise par défaut : chaque brique est réévaluée à l'aune du besoin MVP.
- Hors v1 : compte client connecté, codes promo, back-office, facturation (gérée par SmartOF), regroupement avancé du catalogue par provenance.

## 4. Stack technique retenue

| Composant | Choix | Notes |
|---|---|---|
| Langage backend | PHP 8.2+ | |
| Framework | Symfony 7.x | |
| Accès données | Doctrine ORM | |
| Frontend interactif | Vue.js 3 | Composants ciblés montés sur des templates Twig, pas de SPA |
| Build assets | Webpack Encore | Organisation de `/coplanif` : entrée `vue` unique (`assets/vue/vue.js`, registre des composants), composants rangés par domaine. Différence voulue : chaque composant est monté sur son propre élément (`data-vue`), pas de `<div id="app">` global compilé dans le navigateur, pour éviter l’injection de template sur un site public |
| Base de données | MySQL | Copie locale du catalogue SmartOF + commandes de la boutique |
| Environnement dev | Docker | Conteneur app (php-apache) exposé en local + MySQL |
| Serveur web | Apache de l’hôte (reverse proxy, HTTPS ; déjà en place pour `boutique-old`) + Apache dans le conteneur (image `php:8.x-apache`) | Pas de Nginx : l’Apache existant tient les ports 80/443 et permet la bascule sans coupure |
| Tâches planifiées | Commandes Symfony (cron) | Synchro catalogue, expiration des blocages de places, relances d'envoi SmartOF |

## 5. Fonctionnalités

### 5.1 Rôle de la boutique

```mermaid
flowchart LR
  S[SmartOF] -->|synchro quotidienne<br/>+ à la demande| C[Catalogue boutique<br/>formations COPROTEC]
  C --> R[Réservation<br/>N participants]
  R --> P[Paiement<br/>CB / virement / financement]
  P -->|inscription directe| S
```

La boutique est une **vitrine + tunnel de paiement**. Une réservation payée devient directement une inscription dans SmartOF, **sans étape de validation** par le service formation (décidé : QE-1). La facturation est entièrement gérée par SmartOF : la boutique ne crée ni facture ni avoir.

### 5.2 Catalogue

- **Source :** copie locale des formations (produits) et sessions SmartOF, synchronisée **chaque jour tôt le matin**, plus un **déclenchement à la demande** : commande console relançable à tout moment, et notification envoyée par SmartOF si l'éditeur le permet (QSO-2).
- **Formations COPROTEC uniquement.** SmartOF regroupe aussi les formations d'autres organismes, qui ne doivent être ni affichées ni réservables. Le moyen de les distinguer reste à arrêter (QSO-3). Options, par ordre de préférence :
  1. **Champ personnalisé sur le produit** (ex. « Vendu sur la boutique COPROTEC » : Oui/Non, ou « Organisme »), renseigné par le service formation. Les champs personnalisés des produits sont filtrables par l'API (`filter[custom_fields.custom_field_N][eq]=…`), donc la synchro ne ramène que les bonnes formations.
  2. Un mécanisme natif de SmartOF (organisme, publication au catalogue en ligne), s'il existe et s'il est exposé par l'API (QSO-3).
  3. Une convention sur la référence (`customId`, filtrable en « contient ») ou sur les compétences : c'est fragile, car cela repose sur une saisie manuelle sans contrôle.

  Le filtre s'applique à la synchro **et** au moment de réserver : la boutique refuse toute réservation sur une session dont le produit n'est pas dans son catalogue.
- **Sessions affichées :** uniquement les sessions ouvertes à l'inscription (`GET /v2/sessions_ouvertes`), dans le futur. Les sessions passées disparaissent d'office. Date limite de réservation avant le début de la session : QE-6.
- **Cycle de vie :** le service formation crée, annule ou remplace les sessions dans SmartOF, et la synchro reflète ces changements. La conduite à tenir pour une session annulée qui a déjà des réservations payées est une question métier (QSO-17).
- **Contenu :** fiches simples en v1 (intitulé, dates, lieu, durée, prix, places restantes), car le détail est déjà sur le site vitrine. Le regroupement par provenance ou par thème viendra dans un lot ultérieur.

### 5.3 Réservation

- **Une réservation = une session, 1 à N participants, un seul paiement regroupé.** Exemple : une société inscrit 4 salariés à la même session et paie une seule fois pour les 4. Le formulaire affiche un bloc de champs par participant. Nombre maximum de participants : variable selon la formation (QE-3), à lire dans SmartOF (QSO-19).
- **Réservation en invité** (pas de compte), comme `boutique-old`. Particuliers : QE-2 (l'ancienne boutique imposait société et SIRET).
- **Champs repris de `boutique-old`, mieux structurés et validés champ par champ :**
  - Contact : prénom, nom, email (avec confirmation), téléphone.
  - Société : raison sociale, pays, adresse, code postal, ville, SIRET, APE, TVA intracommunautaire, nombre de salariés, OPCO, organisation professionnelle, dirigeant (prénom, nom), téléphone et email de la société, remarque.
  - **Pays de la société** (France par défaut). En France : adresse proposée par la Base Adresse Nationale au fil de la frappe (Géoplateforme IGN, sans clé, qui remplit adresse, code postal et ville ; la saisie manuelle reste possible), code postal à 5 chiffres, SIRET (clé de Luhn), APE et OPCO obligatoires. Ailleurs : saisie libre, code postal contrôlé pour les pays voisins (4 chiffres en Suisse, Belgique, Luxembourg…) ou générique sinon, sans SIRET, APE ni OPCO, TVA au format du pays (provisoire : QE-38, QSO-20).
  - **Téléphones** : format français strict (`0X XX XX XX XX` ou `+33`), ou international avec indicatif (`+41 76 333 01 11`, `0041…`), indiqué sous le champ. Stockés au format E.164 (`+41763330111`), affichés au format national pour la France.
  - Listes reprises de `boutique-old` (validité à confirmer : QE-29) : organisation professionnelle (Non-adhérent, CAPEB, FFB, SYNASAV…, sans effet sur le prix dans l'ancienne boutique) ; situation du participant (Salarié, Gérant non salarié, Demandeur d'emploi).
  - Par participant : prénom, nom, date de naissance, situation, **n° de sécurité sociale obligatoire** (passeport prévention, format `^[12][0-9]{4}([0-9]{2}|2A|2B)[0-9]{8}$`). Sans ce numéro, pas d'inscription en ligne : le message renvoie vers le service formation (règle de `boutique-old`).
- **Financements (repris de `boutique-old`) :**
  - Sans financement : paiement par CB ou virement (chèque supprimé : QE-8).
  - France Travail (ex-Pôle emploi) : identifiant demandeur d'emploi obligatoire (format de `boutique-old`), mode de paiement « financement ».
  - CPF : proposé seulement pour certaines formations. Il redirige vers moncompteformation.gouv.fr, sans réservation dans la boutique. Dans `boutique-old`, la seule formation concernée était codée en dur (`T68-25`). Ici, l'éligibilité doit venir de SmartOF, par exemple une case « Éligible CPF » sur le produit (QSO-18).
  - Financement en plusieurs parties (tiers + reste à charge) : QE-13.
- **Prix :** repris de SmartOF (`presetTarification` du produit, HT + TVA). **Prix unique par formation** (QE-10, tranchée) : même prix pour tous les clients et toutes les sessions ; lecture exacte dans l'API à confirmer (QSO-4). Total = prix unitaire × nombre de participants. Affichage HT et TTC (QE-11, à confirmer) ; aucun frais annexe (QE-12).

### 5.4 Places et blocage

Une place n'est prise que par une réservation **payée** (ou engagée, pour le virement et France Travail : QE-8). Il n'y a pas de demande en attente de validation.

- **Places disponibles** = limite SmartOF − inscrits SmartOF − places bloquées ou payées dans la boutique mais pas encore transmises.
- **Blocage temporaire :** au passage au paiement, la boutique bloque les N places pendant un délai limité : **30 min**, réglable (`BOUTIQUE_BLOCAGE_PAIEMENT_MINUTES`) ; décision technique de l'équipe de développement (ex-QE-7, retirée du questionnaire). Sans paiement dans ce délai, les places sont libérées.
- **Réservations simultanées :** le calcul et le blocage se font sous verrou en base, sur la session. Exemple : il reste 6 places et deux réservations de 4 arrivent en même temps. La première bloque ses 4 places ; la seconde est arrêtée **avant paiement** avec un message clair (« il ne reste que 2 places »), et le client peut réduire son nombre de participants.
- **Contrôle en direct :** les places restantes sont revérifiées auprès de SmartOF (`GET /v2/sessions_ouvertes`) juste avant le paiement, pour tenir compte des inscriptions faites directement dans SmartOF.
- **Surréservation résiduelle :** un paiement reste possible sur une session remplie entre-temps par un autre canal. La boutique la détecte à l'envoi et alerte le service formation et la comptabilité ; le client est **remboursé par virement** (QE-9, tranchée).

### 5.5 Paiement

- **Paiement au moment de la réservation**, comme `boutique-old`.
- **CB : Monetico**, repris et amélioré depuis `boutique-old` (POST signé HMAC-SHA1 vers `p.monetico-services.com`, cf. `../boutique-old/view/reservation/etape3.inc.php`). Un environnement de test Monetico reste utilisable en permanence en préprod.
  - Le paiement n'est considéré comme validé qu'à réception de la **notification serveur à serveur de Monetico** (URL de retour « CGI2 », signature vérifiée). Le retour du navigateur du client sert seulement à l'affichage : il ne suffit pas à valider.
  - Référence de commande unique, envoyée à Monetico et reprise dans SmartOF (`customId`). Format : `BQ` + AAMMJJ + 4 caractères, ex. `BQ260930K7X2` (12 caractères, limite de la référence Monetico).
- **Virement et financement France Travail :** conservés dès le MVP. **Le chèque est supprimé** (QE-8, QE-21). Virement à régler sous **10 jours** ; les assistantes formation vérifient la réception et la notent dans SmartOF (QE-8).
- **Pas de financement partagé** : le client paie la totalité et se fait rembourser par son OPCO (QE-13).
- **Factures :** émises par SmartOF, rien à faire dans la boutique. La boutique transmet seulement l'information de paiement à SmartOF (QSO-7).
- **Annulation, remboursement, rétractation :** remboursements faits **directement par le service comptable** (avoirs ou virements), sans email automatique de la boutique (QE-17). Conditions d'annulation : QE-16 ; rétractation des particuliers : QE-2.

### 5.6 Communications

- SmartOF rédige la **convention** (QE-19).
- La boutique envoie au client une **confirmation de pré-inscription**, avec le **RIB** pour un virement ou un **reçu** pour un paiement CB (QE-20).
- **Avis interne à contact@coprotec.net** à chaque réservation (QE-22), **sans données personnelles** (minimisation RGPD) : n° de commande, formation, dates, nombre de participants, financement, paiement, montant ; le dossier complet se consulte dans SmartOF (commanditaire = n° de commande). Même principe pour l'**alerte technique** d'échec d'envoi à SmartOF.
- **Préprod :** `MAILER_OVERSEER` (liste d'adresses) reçoit tous les emails à la place des vrais destinataires, objet préfixé par le destinataire prévu. Vide en prod.

### 5.7 Écrans

| Écran | Contenu |
|---|---|
| Catalogue | Liste des sessions ouvertes des formations COPROTEC : intitulé, dates, lieu, prix, places restantes. Filtres simples (formation, ville, mois). |
| Fiche formation | Adresse stable par formation, pour les liens depuis le site vitrine (QE-31). Sessions à venir, infos essentielles, lien vers la fiche détaillée du site vitrine (QE-30). |
| Réservation — étape 1 | Nombre de participants, contact, société, participants, financement. Validation champ par champ. |
| Réservation — étape 2 | Mode de paiement, récapitulatif, acceptation des CGV. Les places sont bloquées à ce moment-là. |
| Paiement CB | Redirection vers Monetico, puis retour succès ou échec. |
| Confirmation | Récapitulatif, n° de commande, RIB pour le virement, reçu pour la CB. |
| Pages légales | Mentions légales, CGV, politique de confidentialité (QE-27). |
| Erreurs | Session complète ou fermée, formation introuvable, erreur de paiement. |

**Lieu :** affiché sur chaque session et rappelé dans le bandeau du tunnel, avec un lien « Itinéraire » (Google Maps) pour les lieux physiques. Pas de carte intégrée : elle déposerait des cookies tiers et imposerait un bandeau de consentement, pour un gain faible puisque la plupart des sessions ont lieu au même endroit. Adresse exacte des salles : QSO-16. **Pas d'images** de formation en v1 (direction visuelle typographique, images SmartOF : QSO-10).

Une seule langue (français). Le site doit s'afficher correctement sur mobile.

### 5.8 Session privée (intra) — lien d'inscription sans mot de passe + QR code

Fonction **retenue, non développée** (QE-37) : lien privé + QR code. Restent le prix (QE-37) et la création de la session côté SmartOF (QSO-15).

- **Besoin :** une entreprise réserve une session entière pour ses seuls salariés (ex. Leroy Merlin). Cette session ne doit pas apparaître au catalogue public.
- **Principe proposé :**
  1. Le service formation crée la session dans SmartOF, sans la publier au catalogue (QSO-15).
  2. La boutique génère pour cette session un **lien privé** : URL contenant un jeton aléatoire long, non devinable, sans compte ni mot de passe. Le jeton peut être révoqué et expire à la date limite de réservation de la session.
  3. La boutique produit le **QR code** de ce lien (image à imprimer ou à joindre au mail envoyé à l'entreprise).
  4. Le lien ouvre le formulaire de réservation habituel, pré-rempli et verrouillé sur la session et l'entreprise : l'entreprise y inscrit ses participants. Le contrôle des places et le `customId` restent identiques à une réservation normale.
  5. **Validation par le paiement** (décidé, QE-37) : une fois tous les participants renseignés, le paiement valide l'inscription, enregistrée d'abord dans la boutique, **puis** envoyée à SmartOF. Prix : catalogue par participant ou forfait (QE-37).
- **Création du lien :** pas de back-office en v1, donc une commande console (`app:session-privee:creer <session SmartOF> <entreprise>`) qui affiche l'URL et enregistre le QR code. Un écran d'administration viendra avec le back-office.
- **Sécurité :** jeton d'au moins 128 bits, page non indexée (`noindex`), limitation de débit sur l'URL. Quiconque possède le lien peut inscrire des participants : c'est accepté, le nombre de places de la session borne l'effet d'une fuite.

### 5.9 Hors v1

Compte client et connexion (y compris via SmartOF, non faisable par l'API aujourd'hui : QSO-11), codes promo, back-office, regroupement avancé du catalogue.

## 6. Intégration SmartOF (API v2)

Doc : [developers.smartof.fr](https://developers.smartof.fr), étudiée le 2026-09-25.

### 6.1 Accès et conventions

- **Auth :** clé d'intégration `smk_…` en `Authorization: Bearer`, créée par l'admin SmartOF (le porteur du projet) dans Paramètres → Intégrations → API REST v2. Elle est stockée comme secret (variable d'environnement), jamais dans le dépôt.
- **URL de base propre à l'instance** (`https://europe-west3-<instance>.cloudfunctions.net/external/api`), chemins `/v2/…`. Une **instance de test dédiée** sert au dev et à la préprod (QSO-1) : la préprod ne touche jamais le SmartOF de production.
- Max 4 requêtes concurrentes, pagination par curseur (≤ 1000 éléments).
- Schémas stricts : une clé inconnue donne une erreur 400, « non renseigné » s'écrit `""` (jamais `null`), les dates sont en ISO 8601 avec l'offset Europe/Paris et les dates de naissance en `YYYY-MM-DD`.

### 6.2 Lecture du catalogue

| Donnée | Endpoint | Usage |
|---|---|---|
| Formations | `GET /v2/produit_formations` | Synchro filtrée sur le critère COPROTEC (§5.2), en delta sur `updatedAt`. Les produits `archived` sont retirés. |
| Sessions | `GET /v2/session_formations` | Dates, lieu, statut, produit lié (`produitInstance.produitFormationUid`). |
| Sessions ouvertes et places | `GET /v2/sessions_ouvertes` | Liste des sessions réservables et remplissage (`inscrits`, `limite`). Endpoint non paginé, appelé aussi en direct avant chaque paiement. |
| Tarifs | `produit_formations.presetTarification.tarifs[].budget[]` | `prixUnitaireHT`, `tva`. |
| Champs personnalisés | `GET /v2/settings/custom_fields` | Configuration (types, options) servant à valider nos valeurs avant envoi. |

### 6.3 Envoi d'une réservation payée : inscription directe

Il n'y a pas de validation d'inscription : la boutique crée directement l'inscription. Elle n'utilise donc **pas** les « demandes d'inscription » (`/v2/demandes_inscription`), qui supposent une validation manuelle par l'organisme.

```mermaid
sequenceDiagram
  participant B as Boutique
  participant S as SmartOF
  B->>S: GET /v2/commanditaires?filter[customId][eq]=<n° commande>
  Note over B,S: déjà présent → rien à faire (relance sans doublon)
  B->>S: GET /v2/entreprises?filter[meta.siret][eq]=… (sinon POST /v2/entreprises)
  loop pour chaque participant
    B->>S: recherche de l'apprenant (sinon POST /v2/apprenants)
  end
  B->>S: POST /v2/commanditaires {type: Entreprise, sessionUid, entrepriseUid, apprenantUids, budget, customId}
```

- `customId` du commanditaire = **n° de commande de la boutique**. Il sert de référence commune et permet de relancer un envoi sans créer de doublon.
- Doublons : les entreprises sont retrouvées par SIRET et les apprenants par nom, prénom et date de naissance (les participants n'ont pas d'email dans `boutique-old`). La clé de rapprochement recommandée par SmartOF reste à confirmer (QSO-12).
- **Données sans champ natif dans SmartOF** (n° de sécu, mode de financement, identifiant France Travail, mode et référence du paiement, statut BPF…) : elles passent par des **champs personnalisés** (20 par entité : apprenant, entreprise, commanditaire). Le porteur du projet les crée dans SmartOF ; la liste est à valider avec les équipes (QE-23).
- **Échec d'envoi** (API indisponible, donnée refusée, session pleine) : la commande reste en file dans la boutique avec des relances automatiques. Au-delà de N échecs, un email d'alerte part.
- `POST /v2/commanditaires/{uid}/apprenants`, envisagé au départ, ne sert qu'à rattacher des apprenants **existants** à un commanditaire Entreprise existant. Il n'est pas utilisé.

### 6.4 Limites connues de l'API

- Pas d'enregistrement de paiement : les factures sont en lecture seule (QSO-7).
- Pas de webhook : d'où la synchro planifiée et la question QSO-2.
- Pas d'authentification apprenant (QSO-11).

## 7. Modèle de données (esquisse)

| Entité | Rôle | Origine |
|---|---|---|
| `Formation` | Produit SmartOF COPROTEC (intitulé, durée, tarifs) | Synchro |
| `Session` | Session d'une formation (dates, lieu, limite de places) | Synchro |
| `Commande` | Réservation : session, contact, société, financement, total, mode de paiement, statut, n° de commande | Boutique |
| `Participant` | Personne inscrite dans une commande | Boutique |
| `Paiement` | Transaction (Monetico ou hors ligne), référence, montant | Boutique |

Statuts d'une commande :

```mermaid
stateDiagram-v2
  [*] --> PlacesBloquees: passage au paiement
  PlacesBloquees --> Expiree: délai dépassé
  PlacesBloquees --> Payee: paiement CB validé
  PlacesBloquees --> EnAttentePaiement: virement / France Travail (QE-8)
  EnAttentePaiement --> Payee: réception constatée
  EnAttentePaiement --> Expiree: délai dépassé (QE-8)
  Payee --> Transmise: inscription créée dans SmartOF
  Payee --> ErreurTransmission: échec
  ErreurTransmission --> Transmise: relance réussie
```

Les données sensibles (n° de sécu) sont conservées le moins longtemps possible après la transmission (QE-26).

Coordonnées de la société dans `commande` : `societe_pays` (code ISO 3166-1 alpha-2 sur 2 caractères, `FR` par défaut), `societe_code_postal` (10 caractères pour les codes étrangers), `societe_siret` / `societe_ape` / `societe_opco` vides hors de France ; téléphones du contact et de la société en E.164.

## 8. Migration des données

- Source : dump de structure `../boutique-old/doc/db/boutique.sql` (tables `code_promo`, `evenements`, `mails_envoyes`, `paiements`, `participants`, `reservations`, `villes`). **Cette structure n'est pas à reprendre** : elle sert seulement à extraire les données utiles, converties vers le schéma Doctrine ci-dessus.
- Même principe pour les données SmartOF : leur format ne dicte pas le schéma cible.
- Besoin réel d'historique à confirmer (QE-25). Comme les inscriptions vivent désormais dans SmartOF, il est peut-être limité aux paiements, voire nul.

## 9. Exigences non fonctionnelles

- **Sécurité :**
  - HTTPS partout, protection CSRF sur les formulaires, validation côté serveur de chaque champ (la validation côté navigateur n'est qu'un confort).
  - Limitation du nombre de soumissions par adresse IP (Symfony RateLimiter) contre les robots.
  - Secrets (clé SmartOF, clé Monetico, accès email, base de données) dans des variables d'environnement, jamais dans le dépôt.
- **Données sensibles :** le n° de sécurité sociale est chiffré en base (chiffrement applicatif, libsodium) et effacé après la transmission à SmartOF, selon QE-26. Il n'apparaît jamais dans les logs ni dans les emails.
- **RGPD :** mentions d'information sur le formulaire, durées de conservation (QE-26), purge automatique des commandes anciennes. Un bandeau cookies n'est nécessaire que si un outil de mesure d'audience est ajouté (QE-36).
- **Accessibilité et compatibilité :** formulaires accessibles (libellés, messages d'erreur liés aux champs, navigation au clavier), affichage mobile, navigateurs récents (Chrome, Edge, Firefox, Safari).
- **Fuseau horaire :** Europe/Paris partout (serveur, base, dates envoyées à SmartOF).
- **Supervision :**
  - Logs applicatifs (Monolog), avec une alerte email en cas d'échec d'envoi SmartOF, d'échec de synchro ou de surréservation.
  - Journal des appels SmartOF et des notifications Monetico, sans données sensibles.
- **Sauvegardes :** sauvegarde quotidienne de la base de prod. Le catalogue peut être reconstruit depuis SmartOF ; seules les commandes sont précieuses.
- **Emails :** envoi par Brevo (déjà utilisé par `boutique-old`) via Symfony Mailer. Une adresse d'expédition dédiée reste à définir.

## 10. Environnements et déploiement

Même organisation que `/coplanif`, qui tourne déjà en préprod et en prod :

| Environnement | Hébergement | SmartOF | Monetico |
|---|---|---|---|
| dev | Docker local (app php-apache + MySQL 8) | Instance de test (QSO-1) | Mode test |
| préprod (`preprod.app.coprotec.net`) | VPS actuel de `boutique-old`, `docker-compose.preprod.yml`, conteneur derrière l’Apache de l’hôte (vhost `docker/apache-hote/`, certificat Let’s Encrypt existant réutilisé), dossier `/srv/preprod.app.coprotec.net` | Instance de test | Mode test (`p.monetico-services.com/test/`) |
| prod (`app.coprotec.net`, domaine de `boutique-old`) | Même VPS, `docker-compose.prod.yml` (127.0.0.1:8096) ; bascule = le vhost Apache de `app.coprotec.net` passe de `boutique-old` au conteneur | Production | Production |

- Déploiement par un script `deploy.sh <preprod|prod>` (build, migrations, cache), déclenchable par GitHub Actions via SSH, comme `coplanif`.
- Tâches planifiées (cron du conteneur ou du VPS) :
  - synchro du catalogue chaque matin ;
  - libération des places bloquées expirées (toutes les minutes) ;
  - relance des envois SmartOF en échec ;
  - purge RGPD.
- La préprod doit être accessible à Monetico (URL de notification publique en HTTPS) mais pas au public : protection par mot de passe (authentification HTTP) sauf sur l'URL de notification Monetico.

## 11. Prérequis techniques

| Élément | Statut | Détenteur |
|---|---|---|
| Doc API SmartOF v2 | Disponible | — |
| Clé API + instance SmartOF de test | À obtenir (QSO-1) | Porteur du projet / éditeur SmartOF |
| Champs personnalisés SmartOF (produit, apprenant, entreprise, commanditaire) | À créer après QSO-3, QE-23, QSO-18 | Porteur du projet |
| Identifiants Monetico de test et de prod (TPE, clé, code société) | Décidé : ceux de `boutique-old` (même TPE). Nouvelle URL de notification à déclarer chez Monetico ; valeurs à fournir dans `.env.local` (non versionné) | Porteur du projet / banque |
| Compte Brevo (clé API, expéditeur) | Décidé : celui de `boutique-old` ; valeurs dans `.env.local` | Porteur du projet |
| Noms de domaine préprod et prod, certificats HTTPS | Décidé : préprod `preprod.app.coprotec.net`, prod = domaine actuel de `boutique-old` (bascule à la mise en ligne). DNS et certificat préprod à créer | Porteur du projet |
| Serveur (VPS) préprod/prod | Décidé : VPS actuel de `boutique-old` (Ubuntu 22.04, 2 Go de RAM), procédure dans `DEPLOIEMENT_VPS.md` | Porteur du projet |
| Dépôt GitHub privé `boutique` + accès SSH pour le déploiement | À créer | Porteur du projet |
| Charte graphique (logo HD, couleurs, typographies) | Par défaut, celle de `boutique-old` : Bootstrap, Open Sans, bleu `#0071C2`, logo `assets/images/logo.png`. Alignement éventuel sur le site vitrine : QE-32 | Communication |
| Textes (CGV, mentions légales, emails, confirmation) | À fournir (QE-27, QE-33). Ceux de `boutique-old` servent de base provisoire. | Équipes |

## 12. Livrables

- Dépôt Git dédié (privé), préparé en local ; dépôt GitHub distant à créer le moment venu.
- Environnement Docker de dev (app php-apache + MySQL) ; vhost Apache de l’hôte fourni pour la préprod (`docker/apache-hote/`) ; procédure serveur `DEPLOIEMENT_VPS.md`.
- Environnements préprod (Monetico en mode test + instance SmartOF de test) et prod, avec `deploy.sh`.
- Documentation : ce cahier des charges, [`QUESTIONS_EQUIPES.md`](QUESTIONS_EQUIPES.md), `CLAUDE.md`.

## 13. Décisions actées

- [x] Nom du dépôt : `boutique` (nouveau) ; l'ancien est renommé `boutique-old` ; les deux sont privés.
- [x] Stack : Symfony 7.4 + Vue 3 (composants ciblés via Webpack Encore, pattern `/coplanif`) + Doctrine ORM + MySQL ; Apache de l’hôte en reverse proxy (celui de `boutique-old`), Apache dans le conteneur ; environnements dev Docker, préprod, prod.
- [x] Hébergement : VPS actuel de `boutique-old`, où les deux boutiques cohabitent jusqu’à la bascule ; préprod `preprod.app.coprotec.net` (accès restreint par auth basique + IP, sauf l’URL de notification Monetico) ; prod sur le domaine actuel de `boutique-old`.
- [x] Monetico et Brevo : accès de `boutique-old` réutilisés.
- [x] ERP : Navision abandonné, remplacé par **SmartOF** (API v2).
- [x] But v1 : afficher et rendre réservables et payables **les seules formations COPROTEC** présentes dans SmartOF.
- [x] Catalogue : copie locale synchronisée chaque matin tôt + déclenchement à la demande ; places revérifiées en direct avant paiement.
- [x] Inscription : **directe, sans validation** (QE-1), via entreprises, apprenants et commanditaires ; `customId` = n° de commande.
- [x] Réservation groupée : N participants pour une session, un seul paiement.
- [x] Places : prises par les réservations payées (pas de demandes en attente), blocage temporaire pendant le paiement, verrou contre les réservations simultanées.
- [x] Paiement au moment de la réservation (comme `boutique-old`) : Monetico CB + virement, financement France Travail (chèque supprimé, QE-8).
- [x] Financements et champs du formulaire repris de `boutique-old`, avec validation stricte par champ.
- [x] Facturation : entièrement dans SmartOF ; remboursements et avoirs : comptabilité.
- [x] Pas de code promo, pas de back-office, réservation en invité en v1.
- [x] Instance SmartOF de test dédiée pour dev et préprod (à obtenir : QSO-1).
- [x] Échec d'envoi à SmartOF : file de relances automatiques + email d'alerte.
- [x] Administration SmartOF (clé API, champs personnalisés) : porteur du projet (DSI).
- [x] Blocage des places pendant le paiement CB : 30 min, réglable ; décision technique (ex-QE-7).
- [x] Surréservation résiduelle : remboursement du client par virement (QE-9).
- [x] Prix : unique par formation, repris de SmartOF ; pas de tarif par type de client ni par session (QE-10) ; aucun frais annexe (QE-12).
- [x] Nombre maximum de participants par réservation : variable selon la formation, lu dans SmartOF (QE-3, QSO-19).
- [x] Session privée (intra) : lien privé + QR code envoyés à l'entreprise ; inscription validée par le paiement une fois tous les participants renseignés, enregistrée dans la boutique puis envoyée à SmartOF (QE-37).
- [x] Virement, France Travail : places bloquées dès la commande, comme une inscription ; **chèque supprimé** ; virement sous 10 jours, suivi dans SmartOF par les assistantes formation (QE-8, QE-21 ; réunion du 07/10).
- [x] Réservation en ligne jusqu'à **J-3** avant la session (QE-6). Prix affiché **HT et TTC** (QE-11). Pas de financement partagé (QE-13).
- [x] Emails : confirmation de pré-inscription + RIB ou reçu CB au client (QE-20) ; avis sans données personnelles à contact@coprotec.net (QE-22).
- [x] N° de sécurité sociale **effacé dès la transmission** à SmartOF (QE-26).
- [x] Session privée (intra) : **forfait négocié** pour la session (QE-37).
- [x] Sociétés hors de France acceptées : pays au choix, SIRET / APE / OPCO exigés en France seulement (2026-10-07 ; à confirmer : QE-38, QSO-20). Téléphones validés par regex sans bibliothèque (France strict, ailleurs indicatif + 7 à 15 chiffres) et stockés en E.164. Adresses françaises suggérées par la Base Adresse Nationale.

## 14. Plan de réalisation

Construction de l'application complète, lot par lot, jusqu'à une préprod testable. Tant qu'une QE ou une QSO n'a pas de réponse, le code utilise une **valeur par défaut provisoire**, isolée en configuration et signalée par un commentaire `// PROVISOIRE (QE-n)`.

| Lot | Contenu | Dépend de | État (2026-09-30) |
|---|---|---|---|
| 1. Socle | Squelette Symfony 7.4, Docker dev (php-apache + MySQL), Encore + Vue 3, gabarit de page (charte `boutique-old`) | — | Fait |
| 2. Catalogue | Entités `Formation` / `Session`, client API SmartOF, commande de synchro (filtre COPROTEC configurable), SmartOF simulé en dev, pages catalogue et fiche | QSO-1, QSO-3 (valeurs provisoires en attendant) | Fait (SmartOF simulé) |
| 3. Réservation | Formulaire (participants dynamiques en Vue), validations, `Commande` / `Participant`, blocage des places avec verrou, expiration | QE-3, QE-8 | Fait |
| 4. Paiement | Monetico (aller + notification serveur), virement / France Travail, emails via Brevo | Accès Monetico de test | Fait (simulateur Monetico en dev) |
| 5. Envoi SmartOF | Entreprise, apprenants, commanditaire ; file de relances ; alertes | QE-23, QSO-5, QSO-12 | Fait (SmartOF simulé) |
| 6. Préprod | `docker-compose.preprod.yml`, `deploy.sh`, cron, vhost Apache de l'hôte, GitHub Actions | Dépôt GitHub, DNS `preprod.app.coprotec.net` | Fichiers prêts, non déployé |
| 7. Recette | Parcours complets en préprod avec les équipes | QE-35 | À faire |
| 8. Session privée (intra) | Lien d'inscription privé à jeton (sans mot de passe), QR code, commande console de création, formulaire verrouillé sur la session et l'entreprise (§5.8) | QE-37 (prix), QSO-15 | Retenu, à développer |

## 15. Points ouverts

Voir [`QUESTIONS_EQUIPES.md`](QUESTIONS_EQUIPES.md) et les prérequis techniques du §11. Questions bloquantes pour le développement :

- **QSO-3** : critère d'identification des formations COPROTEC, qui conditionne la synchro du catalogue.
- **QSO-4 / QSO-13** : lecture du prix unique dans l'API et limite de places qui fait foi, qui conditionnent le calcul du prix et des places.
- **QSO-15** : création des sessions privées (intra) dans SmartOF, qui conditionne le lot 8 (prix : forfait négocié, QE-37).
- **QE-23** : toutes les données utiles partent dans SmartOF (n° de sécu, date de naissance, statut, financement, paiement, OPCO…) ; reste à créer les champs personnalisés côté SmartOF et à les déclarer dans `SMARTOF_CHAMPS_*`.
- **QSO-1** : instance de test, qui conditionne les tests d'intégration.

Ces questions bloquent chacune une partie précise, pas le démarrage : le squelette, Docker, le tunnel de réservation et Monetico en mode test peuvent avancer sans elles.

**À reprendre juste avant la mise en prod (reportés volontairement) :**

- **QE-2 — particuliers** : case « particulier » qui adapte les champs. Vérifier d'abord les règles (délai de rétractation et paiement exigible pour une personne qui finance elle-même sa formation, cf. Code du travail L6353-3 à 6), et l'enregistrement d'un apprenant sans entreprise dans SmartOF.
- **QE-36 — statistiques** : mesure d'audience et suivi des formulaires commencés mais non terminés. Choisir l'outil et le mode de consentement (ex. Matomo sans cookie).
- **QE-16 / QE-27 — CGV** : aucune CGV en place à ce jour, à rédiger et faire valider avant d'ouvrir la vente en ligne.
