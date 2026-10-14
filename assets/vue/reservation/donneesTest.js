// Données aléatoires mais valides pour le formulaire de réservation (outils de test, dev et préprod uniquement).
// Les formats suivent les contrôles serveur de src/Reservation/Formats.php : clé du n° de sécu, Luhn du SIRET, APE, téléphones.

const PRENOMS = ['Julien', 'Camille', 'Thomas', 'Sarah', 'Nicolas', 'Léa', 'Mathieu', 'Chloé', 'Antoine', 'Émilie', 'Kevin', 'Laura'];
const NOMS = ['Martin', 'Bernard', 'Muller', 'Schmitt', 'Meyer', 'Klein', 'Weber', 'Fischer', 'Roth', 'Keller', 'Dubois', 'Lefèvre'];
const SOCIETES = ['Thermi Services', 'Froid Plus', 'Clim Est', 'Énergies Habitat', 'Atelier Fluides', 'Chauffage Rhin', 'Élec Bâtiment'];
const ADRESSES_FR = [
    ['1 place de la Cathédrale', '67000', 'Strasbourg'],
    ['10 rue du Sauvage', '68100', 'Mulhouse'],
    ['5 rue des Clefs', '68000', 'Colmar'],
    ['2 place Stanislas', '54000', 'Nancy'],
    ['15 rue Serpenoise', '57000', 'Metz'],
];
const ADRESSES_CH = [
    ['Rue du Rhône 42', '1204', 'Genève'],
    ['Bahnhofstrasse 10', '8001', 'Zürich'],
    ['Avenue de la Gare 5', '1003', 'Lausanne'],
];
const APE = ['4321A', '4322A', '4322B', '4329A', '4399C'];
const OPCO = ['Constructys', 'AKTO', 'OPCO EP'];

const entier = (min, max) => min + Math.floor(Math.random() * (max - min + 1));
const choisir = (liste) => liste[entier(0, liste.length - 1)];
const chiffres = (n) => Array.from({ length: n }, () => entier(0, 9)).join('');
const pad = (n, longueur) => String(n).padStart(longueur, '0');
const sansAccents = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

/** Date de naissance entre 20 et 60 ans, au format AAAA-MM-JJ. */
function dateNaissance() {
    const d = new Date();
    d.setFullYear(d.getFullYear() - entier(20, 60), entier(0, 11), entier(1, 28));
    return `${d.getFullYear()}-${pad(d.getMonth() + 1, 2)}-${pad(d.getDate(), 2)}`;
}

/** N° de sécurité sociale cohérent avec la date de naissance, clé = 97 − (13 chiffres mod 97). Hors Corse (2A/2B). */
function numeroSecu(naissance) {
    const [annee, mois] = naissance.split('-');
    let departement = entier(1, 95);
    if (departement === 20) departement = 67;
    const corps = `${entier(1, 2)}${annee.slice(2)}${mois}${pad(departement, 2)}${pad(entier(1, 999), 3)}${pad(entier(1, 999), 3)}`;
    const cle = pad(97 - Number(BigInt(corps) % 97n), 2);
    const n = corps + cle;
    return `${n[0]} ${n.slice(1, 3)} ${n.slice(3, 5)} ${n.slice(5, 7)} ${n.slice(7, 10)} ${n.slice(10, 13)} ${n.slice(13)}`;
}

/** SIRET : 13 chiffres + chiffre de contrôle de Luhn. */
function siret() {
    const corps = chiffres(13);
    let somme = 0;
    [...corps].reverse().forEach((c, i) => {
        // Le chiffre de contrôle occupera le rang 0 : les chiffres du corps sont aux rangs 1 à 13, doublés aux rangs impairs.
        const v = Number(c) * (i % 2 === 0 ? 2 : 1);
        somme += v > 9 ? v - 9 : v;
    });
    const s = corps + ((10 - (somme % 10)) % 10);
    return `${s.slice(0, 3)} ${s.slice(3, 6)} ${s.slice(6, 9)} ${s.slice(9)}`;
}

const telephoneFr = (prefixe) => `${prefixe} ${chiffres(2)} ${chiffres(2)} ${chiffres(2)} ${chiffres(2)}`;
const telephoneCh = () => `+41 7${entier(6, 9)} ${chiffres(3)} ${chiffres(2)} ${chiffres(2)}`;

export function participant(situations) {
    const naissance = dateNaissance();
    return { prenom: choisir(PRENOMS), nom: choisir(NOMS), dateNaissance: naissance, situation: choisir(situations), numeroSecu: numeroSecu(naissance) };
}

/**
 * Formulaire complet. pays : 'FR' ou 'CH' (société étrangère, sans SIRET / APE / OPCO).
 * financement : 'aucun' ou 'france_travail'. Les emails sont en @example.com (domaine réservé, jamais distribué).
 */
export function formulaire(config, { nbParticipants = 1, pays = 'FR', financement = 'aucun' } = {}) {
    const prenom = choisir(PRENOMS);
    const nom = choisir(NOMS);
    const email = `${sansAccents(prenom)}.${sansAccents(nom)}.${chiffres(4)}@example.com`;
    const raisonSociale = `${choisir(SOCIETES)} ${chiffres(3)}`;
    const enFrance = pays === 'FR';
    const [adresse, codePostal, ville] = choisir(enFrance ? ADRESSES_FR : ADRESSES_CH);

    return {
        participants: Array.from({ length: nbParticipants }, () => participant(config.situations)),
        contact: { prenom, nom, email, emailConfirmation: email, telephone: enFrance ? telephoneFr('06') : telephoneCh() },
        societe: {
            raisonSociale, pays, adresse, codePostal, ville,
            siret: enFrance ? siret() : '',
            ape: enFrance ? choisir(APE) : '',
            tvaIntracom: enFrance ? '' : `CHE-${chiffres(3)}.${chiffres(3)}.${chiffres(3)} TVA`,
            nbSalaries: entier(1, 50),
            opco: enFrance ? choisir(OPCO) : '',
            organisationProfessionnelle: choisir(config.organisations),
            dirigeantPrenom: prenom,
            dirigeantNom: nom,
            telephone: enFrance ? telephoneFr('03') : telephoneCh(),
            email: `contact@${sansAccents(raisonSociale).replace(/[^a-z0-9]+/g, '-')}.example.com`,
        },
        financement,
        identifiantFranceTravail: financement === 'france_travail' ? `${chiffres(7)}A` : '',
        remarque: 'Réservation de test (outils de préprod).',
    };
}
