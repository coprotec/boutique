# Boutique formations — questions aux équipes et à SmartOF

> Mis à jour le 2026-10-01. Référencé par [`CAHIER_DES_CHARGES.md`](CAHIER_DES_CHARGES.md).
>
> - **QE-n** : question aux équipes COPROTEC (service formation, comptabilité, direction). Aucune n'est technique.
> - **QSO-n** : question à l'éditeur SmartOF, à poser en réunion.
> - **(bloquant)** : la réponse est nécessaire avant de développer la partie concernée.
>
> Pour répondre, complétez la colonne « Réponse ». « Comme aujourd'hui » est une réponse valable si vous précisez ce qui se fait aujourd'hui.
>
> Les numéros ne sont jamais réattribués : une question retirée ou tranchée laisse un trou dans la numérotation (voir « Questions déjà tranchées » en fin de partie 1).

## Ce que la boutique fera (pour situer les questions)

La nouvelle boutique affiche les formations **COPROTEC** saisies dans SmartOF et permet de les réserver et de les payer en ligne. Une réservation peut inscrire **plusieurs personnes** à une même session, avec un seul paiement. Une fois payée, l'inscription est créée directement dans SmartOF. Les factures, le suivi des inscriptions et les documents de formation restent gérés dans SmartOF.

---

## Partie 1 — Questions aux équipes (QE)

### Parcours d'inscription

| # | Question | Contexte / proposition | Réponse |
|---|---|---|---|
| QE-1 | Confirmez-vous qu'une réservation payée sur le site devient **directement une inscription**, sans validation par le service formation ? | C'est le fonctionnement retenu, comme l'ancienne boutique. | |
| QE-2 | Les **particuliers** (sans société) doivent-ils pouvoir réserver ? | L'ancienne boutique exigeait une société et un SIRET : les réservations étaient réservées aux entreprises. | |
| QE-3 | Combien de participants au maximum par réservation ? | Une société peut inscrire plusieurs salariés à la même session. | |
| QE-37 | **Session privée (intra) :** quand une entreprise réserve une session entière pour ses seuls salariés, faut-il passer par la boutique ? | Exemple : Leroy Merlin veut une session complète pour son personnel. **Proposition :** le service formation crée la session dans SmartOF ; la boutique génère un **lien privé** (absent du catalogue, sans mot de passe) et son **QR code**, envoyés à l'entreprise, qui inscrit ses participants par ce lien. **a)** Le besoin est-il assez fréquent pour développer cette fonction, ou tout reste-t-il géré hors boutique (devis + virement) ? **b)** Si oui, quel paiement : CB en ligne, virement, ou au choix ? **c)** Prix : celui du catalogue par participant, ou un forfait négocié pour la session ? **d)** Qui inscrit : un responsable de l'entreprise pour tout le groupe, ou chaque salarié lui-même en scannant le QR code ? | |

### Catalogue et sessions

| # | Question | Contexte / proposition | Réponse |
|---|---|---|---|
| QE-4 | **(bloquant)** Comment reconnaître dans SmartOF une formation COPROTEC à vendre sur la boutique ? | SmartOF contient aussi les formations d'autres organismes, qui ne doivent pas apparaître. **Proposition :** une case « Vendu sur la boutique COPROTEC » (Oui/Non) sur la fiche formation dans SmartOF, renseignée par le service formation à chaque création. Le service formation l'accepte-t-il ? Existe-t-il déjà un repère fiable (organisme, référence, catégorie) ? | |
| QE-5 | Quand le service formation **annule ou remplace** une session qui a déjà des réservations payées sur le site : qui prévient les clients, et que leur propose-t-on (report sur une autre date, remboursement) ? | La boutique retire automatiquement la session du site ; le suivi des clients déjà inscrits se fait dans SmartOF. | |
| QE-6 | Jusqu'à quand une session reste-t-elle réservable en ligne (la veille, X jours avant le début) ? | Les sessions passées disparaissent automatiquement. | |

### Places

| # | Question | Contexte / proposition | Réponse |
|---|---|---|---|
| QE-8 | **(bloquant)** Pour un paiement par **virement ou chèque**, ou un **financement France Travail** : les places sont-elles bloquées dès la commande, avant réception du paiement ou de l'accord de prise en charge ? | Sinon, la session peut se remplir avant l'arrivée du virement ou de l'accord. | |

### Tarifs

| # | Question | Contexte / proposition | Réponse |
|---|---|---|---|
| QE-11 | Le prix affiché est-il en HT, en TTC, ou les deux ? | | |
| QE-12 | Y a-t-il des frais en plus du prix de la formation (repas, supports, examen) ? | | |

### Financement

| # | Question | Contexte / proposition | Réponse |
|---|---|---|---|
| QE-13 | Une inscription peut-elle être financée **en plusieurs parties** (une part prise en charge par un tiers, un reste à charge payé par le client) ? Si oui, le client paie-t-il sa part en ligne ? | Les modes de financement de l'ancienne boutique sont conservés : sans financement, France Travail, CPF (redirigé vers moncompteformation.gouv.fr), OPCO. | |

### Paiement hors carte bancaire

| # | Question | Contexte / proposition | Réponse |
|---|---|---|---|
| QE-14 | Pour un virement ou un chèque : qui constate la réception du paiement, et où le note-t-il ? | La boutique n'a pas de back-office en v1. | |
| QE-15 | Faut-il un délai maximum pour recevoir un virement ou un chèque ? Que se passe-t-il s'il est dépassé (relance, annulation, places libérées) ? | L'ancienne boutique demandait l'envoi du chèque **sous 10 jours**, sans quoi l'inscription n'était pas validée. Même règle pour le virement ? | |

### Annulation, remboursement, rétractation

| # | Question | Contexte / proposition | Réponse |
|---|---|---|---|
| QE-16 | Quelles sont les conditions d'annulation par le client (délais, frais, report) ? Les CGV actuelles restent-elles valables ? | Elles seront affichées et acceptées lors de la réservation. | |
| QE-17 | La comptabilité gère les remboursements (avoir ou remboursement direct). Pouvez-vous décrire le traitement actuel ? Parmi ces options, laquelle vous ferait gagner le plus de temps ? | **A.** Un email automatique à la comptabilité à chaque annulation, avec tout le dossier (n° de commande, référence du paiement CB, montant, participants). **B.** En plus, remboursement CB déclenché en un clic depuis la boutique, sans passer par l'espace Monetico (lot ultérieur, car il faut un écran d'administration). **C.** Rien de spécial, la comptabilité continue comme aujourd'hui. | |
| QE-18 | Pour les particuliers (si QE-2 = oui) : comment gère-t-on le **délai de rétractation de 14 jours**, en particulier quand la session commence avant la fin de ce délai ? | Cela impose des mentions et une case à cocher dans le formulaire. | |

### Communications au client

| # | Question | Contexte / proposition | Réponse |
|---|---|---|---|
| QE-19 | Qu'envoie SmartOF automatiquement lors d'une inscription (convocation, convention, accès extranet), et à quel moment ? | Le site ne doit pas envoyer ces documents en double. | |
| QE-20 | Qu'attendez-vous du site juste après la réservation : récapitulatif, reçu de paiement CB, coordonnées bancaires pour un virement, adresse d'envoi d'un chèque ? | | |
| QE-21 | Le « bordereau » PDF de l'ancienne boutique (paiement par chèque) est-il toujours nécessaire ? | | |
| QE-22 | Qui doit être prévenu en interne à chaque nouvelle réservation (adresse email de service) ? | | |

### Paramétrage SmartOF

| # | Question | Contexte / proposition | Réponse |
|---|---|---|---|
| QE-23 | **(bloquant)** Le service informatique va créer dans SmartOF des cases supplémentaires pour recevoir les informations du site : n° de sécurité sociale, mode de financement, identifiant France Travail, mode et référence de paiement, OPCO… Validez-vous cette liste ? Manque-t-il une information dont vous avez besoin dans SmartOF ? | Ces cases apparaîtront sur les fiches apprenant, entreprise et inscription. | |
| QE-24 | Quel **statut BPF** (bilan pédagogique et financier) appliquer aux inscriptions venant du site, selon le financement ? Exemples : salarié d'entreprise privée, demandeur d'emploi (France Travail). | SmartOF demande ce classement pour chaque apprenant et chaque entreprise. | |

### Historique et données personnelles

| # | Question | Contexte / proposition | Réponse |
|---|---|---|---|
| QE-25 | De quel historique de l'ancienne boutique avez-vous encore besoin (réservations, paiements) ? Sur quelle période, et pour qui (comptabilité, contrôle Qualiopi) ? | | |
| QE-26 | Une fois l'inscription transmise à SmartOF, combien de temps la boutique doit-elle garder les données du client ? Le n° de sécurité sociale peut-il être effacé de la boutique dès sa transmission ? | RGPD : garder le minimum. | |
| QE-27 | Les CGV doivent-elles être mises à jour pour le nouveau site ? Par qui ? Les mentions légales et la politique de confidentialité du site vitrine (liées depuis la boutique, comme aujourd’hui) couvrent-elles la boutique, notamment la collecte du n° de sécurité sociale ? | L’ancienne boutique renvoie vers les pages mentions légales et confidentialité de coprotec.net. | |

### Formulaire : compléments

| # | Question | Contexte / proposition | Réponse |
|---|---|---|---|
| QE-28 | Quelles formations acceptent le **CPF** ? | Dans l'ancienne boutique, une seule formation était concernée (T68-25, attestation d'aptitude fluides frigorigènes cat. 1), et elle était inscrite en dur dans le code. **Proposition :** une case « Éligible CPF » sur la fiche formation dans SmartOF, cochée par le service formation. | |
| QE-29 | Les listes du formulaire sont-elles toujours valables ? **Organisation professionnelle :** Non-adhérent, CAPEB, FFB, SYNASAV… **Situation du participant :** Salarié, Gérant non salarié, Demandeur d'emploi. Être adhérent change-t-il le prix ? | Dans l'ancienne boutique, l'organisation professionnelle n'avait aucun effet sur le prix. | |

### Contenus, image et mise en ligne

| # | Question | Contexte / proposition | Réponse |
|---|---|---|---|
| QE-30 | Les informations obligatoires **Qualiopi** (objectifs, prérequis, délais d'accès, accessibilité aux personnes handicapées, contact) doivent-elles figurer sur la boutique, ou un lien vers la fiche du site vitrine suffit-il ? | En v1, les fiches de la boutique restent simples. | |
| QE-31 | Le **site vitrine** renverra-t-il vers la boutique depuis chaque fiche formation ? Qui gère le site vitrine et peut y ajouter ces liens ? | La boutique fournira une adresse stable par formation. | |
| QE-32 | **Charte graphique :** garde-t-on l'apparence de l'ancienne boutique (bleu COPROTEC, police Open Sans), ou faut-il s'aligner sur le site vitrine ? Qui fournit le logo en haute définition, les couleurs et les visuels ? | Sans réponse, l'apparence de l'ancienne boutique est reprise. | |
| QE-33 | Qui rédige et valide les **textes** : page d'accueil du catalogue, emails, page de confirmation, instructions de virement (RIB) et d'envoi de chèque ? | Les textes de l'ancienne boutique servent de base provisoire. | |
| QE-34 | Quelle **date de mise en ligne** est souhaitée ? Y a-t-il des périodes à éviter ? L'ancienne boutique est-elle coupée le jour J, avec une redirection vers la nouvelle ? | | |
| QE-35 | Qui **teste et valide** la boutique en préproduction avant sa mise en ligne (parcours complets, paiement test, contrôle dans SmartOF) ? | Il faut une ou deux personnes du service formation et de la comptabilité. | |
| QE-36 | Souhaitez-vous des **statistiques** de fréquentation et de réservation ? | L’ancienne boutique charge Google Tag Manager (`GTM-MXWHNLW`). Le reprendre impose un bandeau de consentement aux cookies. Faut-il le garder, et qui gère ce compte ? | |

### Questions déjà tranchées

| # | Question | Décision |
|---|---|---|
| QE-7 | Durée du blocage des places pendant le paiement CB. | Question technique, retirée du questionnaire : réglée par l'équipe de développement (30 minutes, réglable). |
| QE-9 | Réservation payée sur une session remplie entre-temps par un autre canal. | Le client est **remboursé par virement**. La boutique alerte le service formation et la comptabilité. |
| QE-10 | Tarif à appliquer quand une formation a plusieurs tarifs. | **Prix unique**, fixé sur la formation dans SmartOF : même prix pour tous les clients et toutes les sessions. |

---

## Partie 2 — Questions à l'éditeur SmartOF (QSO)

À poser en réunion avec SmartOF. Elles viennent de la lecture de la documentation API v2 ([developers.smartof.fr](https://developers.smartof.fr)).

| # | Question | Pourquoi | Réponse SmartOF |
|---|---|---|---|
| QSO-1 | **(bloquant)** Pouvez-vous fournir une **instance de test** (URL et clé dédiées), séparée de la production ? Coût ? Peut-on y copier notre paramétrage (produits, champs personnalisés) ? | Tester des réservations réelles en préprod sans toucher à la production. | |
| QSO-2 | **Synchro à la demande :** SmartOF peut-il nous prévenir quand une formation ou une session est créée, modifiée ou annulée (webhook, appel vers une URL de notre choix) ? Sinon, est-ce prévu ? | Nous synchronisons une fois par jour, tôt le matin, et voulons pouvoir actualiser dès qu'une session est publiée. | |
| QSO-3 | **(bloquant)** Notre instance contient aussi des formations d'autres organismes. Existe-t-il dans SmartOF un moyen natif, lisible par l'API, de savoir à quel organisme appartient une formation, ou si elle est publiée au catalogue en ligne ? Sinon, confirmez-vous qu'un champ personnalisé sur le produit est la bonne pratique ? | Afficher et vendre uniquement les formations COPROTEC. | |
| QSO-4 | Nos formations ont un **prix unique** (QE-10). Confirmez-vous qu'il se lit dans `presetTarification.tarifs[]` du produit (un seul tarif, HT + TVA) ? Un prix peut-il être surchargé au niveau de la session, et faut-il alors l'ignorer ? | Afficher et facturer le bon prix. | |
| QSO-5 | Pour une réservation déjà payée, confirmez-vous que la bonne pratique est l'inscription directe (`POST /v2/apprenants` + `POST /v2/commanditaires`), et non `demandes_inscription` ? Est-ce qu'elle déclenche les mêmes automatismes (convocation, convention, extranet) qu'une inscription saisie dans l'interface ? L'API refuse-t-elle une inscription qui dépasse la limite de places ? Le compteur `inscrits` de `sessions_ouvertes` est-il mis à jour immédiatement ? | Éviter les doublons d'emails et la surréservation. | |
| QSO-6 | Le `customId` d'un commanditaire est-il **unique** (refus d'un doublon) ? | Nous y mettons notre n° de commande pour pouvoir relancer un envoi sans créer de doublon. | |
| QSO-7 | Comment indiquer qu'une inscription est **déjà payée par CB**, pour que la facture SmartOF apparaisse comme réglée ? Un enregistrement des paiements par l'API est-il prévu ? | Éviter à la comptabilité un rapprochement manuel. | |
| QSO-8 | La limite de 4 requêtes simultanées est-elle partagée avec nos autres intégrations ? `GET /v2/sessions_ouvertes` n'est pas paginé : y a-t-il une limite de volume ? | Nous l'appelons en direct avant chaque paiement. | |
| QSO-9 | Peut-on créer une clé API à droits restreints (lecture du catalogue, création d'apprenants, d'entreprises et de commanditaires uniquement) ? | Limiter l'impact si la clé du serveur web fuitait. | |
| QSO-10 | Les images des produits (champs personnalisés de type Image) sont-elles accessibles par une URL publique ? | Illustrer le catalogue. | |
| QSO-11 | *(Lot ultérieur)* Un apprenant peut-il s'authentifier via SmartOF (SSO, OAuth, extranet) pour se connecter à la boutique ? | Aucun endpoint ne le permet aujourd'hui. | |
| QSO-12 | Quelle clé recommandez-vous pour retrouver un apprenant existant et éviter les doublons ? Nos participants n'ont pas toujours d'email : nom + prénom + date de naissance suffit-il ? | Ne pas créer deux fois la même personne. | |
| QSO-13 | **(bloquant)** Quelle **limite de places** fait foi : `remplissage.limite` de `sessions_ouvertes`, ou `effectifMax` du produit ou de la session ? Que signifie une limite « ∞ » ? | Calculer les places restantes affichées et bloquées. | |
| QSO-14 | Pour une session en plusieurs créneaux non consécutifs, faut-il lire les créneaux (`creneau_formations`) pour afficher les dates exactes, ou `dateDebut` / `dateFin` suffisent-ils ? | Afficher les bonnes dates au client. | |
| QSO-15 | Pour une **session intra** (réservée à une seule entreprise, QE-37) : comment la créer dans SmartOF pour qu'elle **n'apparaisse pas** dans `sessions_ouvertes` tout en restant lisible par l'API (limite de places, inscrits) ? Peut-on y rattacher l'entreprise cliente ? | Proposer un lien d'inscription privé sans exposer la session au catalogue public. | |
| QSO-16 | Les **salles de formation** (`salle_formations`) exposent-elles une **adresse postale** par l'API (rue, code postal, ville), ou seulement un nom ? | Afficher l'adresse exacte de la session et un lien d'itinéraire fiable. | |
