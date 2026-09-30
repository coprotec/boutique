<script setup>
import { computed, reactive, ref, watch } from 'vue';
import Champ from '../tool/FormType/Champ.vue';

const props = defineProps({ config: { type: Object, required: true } });
const c = props.config;

const participantVide = () => ({ prenom: '', nom: '', dateNaissance: '', situation: '', numeroSecu: '' });

const saisie = c.saisie ?? {};
const form = reactive({
    participants: saisie.participants ?? Array.from({ length: Math.min(c.nbInitial, c.maxParticipants) }, participantVide),
    contact: { prenom: '', nom: '', email: '', emailConfirmation: '', telephone: '', ...(saisie.contact ?? {}) },
    societe: {
        raisonSociale: '', adresse: '', codePostal: '', ville: '', siret: '', ape: '', tvaIntracom: '',
        nbSalaries: null, opco: '', organisationProfessionnelle: '', dirigeantPrenom: '', dirigeantNom: '',
        telephone: '', email: '', ...(saisie.societe ?? {}),
    },
    financement: saisie.financement ?? 'aucun',
    identifiantFranceTravail: saisie.identifiantFranceTravail ?? '',
    remarque: saisie.remarque ?? '',
});
const cpf = ref(false);
const erreurs = ref({});
const erreurGenerale = ref('');
const envoi = ref(false);

const nb = computed(() => form.participants.length);
const totalHt = computed(() => (c.prixHtCentimes ?? 0) * nb.value);
const totalTtc = computed(() => Math.round(totalHt.value * (1 + c.tauxTva / 100)));
const depassePlaces = computed(() => c.placesRestantes !== null && nb.value > c.placesRestantes);
const maxAjout = computed(() => Math.min(c.maxParticipants, c.placesRestantes ?? c.maxParticipants));

const euros = (centimes) => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', minimumFractionDigits: centimes % 100 ? 2 : 0 }).format(centimes / 100);

function ajouter() {
    if (nb.value < maxAjout.value) form.participants.push(participantVide());
}
function retirer(i) {
    if (nb.value > 1) form.participants.splice(i, 1);
}

// Le contact est souvent le dirigeant : on pré-remplit sans écraser une saisie.
watch(() => [form.contact.prenom, form.contact.nom], ([prenom, nom], [ancienPrenom, ancienNom]) => {
    if (form.societe.dirigeantPrenom === ancienPrenom) form.societe.dirigeantPrenom = prenom;
    if (form.societe.dirigeantNom === ancienNom) form.societe.dirigeantNom = nom;
});

const erreur = (chemin) => erreurs.value[chemin];

async function envoyer() {
    erreurs.value = {};
    erreurGenerale.value = '';
    envoi.value = true;
    try {
        const reponse = await fetch(c.urlValidation, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': c.csrf },
            body: JSON.stringify({ ...form, societe: { ...form.societe, nbSalaries: form.societe.nbSalaries === '' ? null : form.societe.nbSalaries } }),
        });
        const data = await reponse.json().catch(() => ({}));
        if (reponse.ok && data.redirect) {
            window.location.assign(data.redirect);
            return;
        }
        if (reponse.status === 422 && Array.isArray(data.violations)) {
            for (const v of data.violations) {
                // « participants[0].nom » → « participants.0.nom »
                const chemin = v.propertyPath.replace(/\[(\d+)\]/g, '.$1');
                erreurs.value[chemin] ??= v.title;
            }
            erreurGenerale.value = 'Certains champs sont à corriger.';
            await Promise.resolve();
            document.querySelector('.is-invalid, .erreur-bloc')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
            erreurGenerale.value = data.title ?? 'Une erreur est survenue, merci de réessayer.';
        }
    } catch {
        erreurGenerale.value = 'Connexion impossible, vérifiez votre réseau et réessayez.';
    } finally {
        envoi.value = false;
    }
}
</script>

<template>
    <form class="tunnel" novalidate @submit.prevent="envoyer">
        <div class="tunnel__principal">
            <!-- 1. Participants -->
            <section class="etape">
                <header class="etape__entete">
                    <span class="etape__numero">1</span>
                    <div>
                        <h2 class="etape__titre">Participants</h2>
                        <p class="etape__aide">Une personne par bloc. Le n° de sécurité sociale est exigé pour le passeport prévention.</p>
                    </div>
                </header>

                <div v-if="erreur('participants')" class="erreur-bloc">{{ erreur('participants') }}</div>

                <div v-for="(p, i) in form.participants" :key="i" class="participant">
                    <div class="participant__entete">
                        <span class="participant__titre">Participant {{ i + 1 }}</span>
                        <button v-if="nb > 1" type="button" class="btn btn-sm btn-link text-danger" @click="retirer(i)">Retirer</button>
                    </div>
                    <div class="row g-3">
                        <Champ class="col-md-6" label="Prénom" v-model="p.prenom" :erreur="erreur(`participants.${i}.prenom`)" autocomplete="off" />
                        <Champ class="col-md-6" label="Nom" v-model="p.nom" :erreur="erreur(`participants.${i}.nom`)" autocomplete="off" />
                        <Champ class="col-md-4" label="Date de naissance" type="date" v-model="p.dateNaissance" :erreur="erreur(`participants.${i}.dateNaissance`)" />
                        <Champ class="col-md-4" label="Situation" type="select" :options="c.situations" v-model="p.situation" :erreur="erreur(`participants.${i}.situation`)" />
                        <Champ class="col-md-4" label="N° de sécurité sociale" v-model="p.numeroSecu" :erreur="erreur(`participants.${i}.numeroSecu`)"
                               placeholder="1 85 05 68 066 123 45" inputmode="text" maxlength="21" autocomplete="off" />
                    </div>
                </div>

                <button type="button" class="btn btn-outline-primary btn-ajouter" :disabled="nb >= maxAjout" @click="ajouter">
                    + Ajouter un participant
                </button>
                <p v-if="nb >= maxAjout && c.placesRestantes !== null && c.placesRestantes <= c.maxParticipants" class="etape__aide mt-2">
                    Plus de place disponible sur cette session au-delà de {{ maxAjout }} participant{{ maxAjout > 1 ? 's' : '' }}.
                </p>
            </section>

            <!-- 2. Entreprise -->
            <section class="etape">
                <header class="etape__entete">
                    <span class="etape__numero">2</span>
                    <div><h2 class="etape__titre">Entreprise</h2></div>
                </header>
                <div class="row g-3">
                    <Champ class="col-md-8" label="Raison sociale" v-model="form.societe.raisonSociale" :erreur="erreur('societe.raisonSociale')" autocomplete="organization" />
                    <Champ class="col-md-4" label="SIRET" v-model="form.societe.siret" :erreur="erreur('societe.siret')" inputmode="numeric" maxlength="17" placeholder="14 chiffres" />
                    <Champ class="col-12" label="Adresse" v-model="form.societe.adresse" :erreur="erreur('societe.adresse')" autocomplete="street-address" />
                    <Champ class="col-md-4" label="Code postal" v-model="form.societe.codePostal" :erreur="erreur('societe.codePostal')" inputmode="numeric" maxlength="5" autocomplete="postal-code" />
                    <Champ class="col-md-8" label="Ville" v-model="form.societe.ville" :erreur="erreur('societe.ville')" autocomplete="address-level2" />
                    <Champ class="col-md-4" label="Code APE" v-model="form.societe.ape" :erreur="erreur('societe.ape')" maxlength="6" placeholder="4322B" />
                    <Champ class="col-md-4" label="TVA intracommunautaire" facultatif v-model="form.societe.tvaIntracom" :erreur="erreur('societe.tvaIntracom')" placeholder="FR…" />
                    <Champ class="col-md-4" label="Nombre de salariés" type="number" v-model.number="form.societe.nbSalaries" :erreur="erreur('societe.nbSalaries')" min="0" />
                    <Champ class="col-md-6" label="OPCO" v-model="form.societe.opco" :erreur="erreur('societe.opco')" placeholder="Constructys, AKTO…" />
                    <Champ class="col-md-6" label="Organisation professionnelle" type="select" :options="c.organisations" v-model="form.societe.organisationProfessionnelle" :erreur="erreur('societe.organisationProfessionnelle')" />
                    <Champ class="col-md-6" label="Prénom du dirigeant" v-model="form.societe.dirigeantPrenom" :erreur="erreur('societe.dirigeantPrenom')" />
                    <Champ class="col-md-6" label="Nom du dirigeant" v-model="form.societe.dirigeantNom" :erreur="erreur('societe.dirigeantNom')" />
                    <Champ class="col-md-6" label="Téléphone de la société" type="tel" v-model="form.societe.telephone" :erreur="erreur('societe.telephone')" />
                    <Champ class="col-md-6" label="Email de la société" type="email" v-model="form.societe.email" :erreur="erreur('societe.email')" />
                </div>
            </section>

            <!-- 3. Contact -->
            <section class="etape">
                <header class="etape__entete">
                    <span class="etape__numero">3</span>
                    <div>
                        <h2 class="etape__titre">Vos coordonnées</h2>
                        <p class="etape__aide">Le récapitulatif et la suite de l'inscription sont envoyés à cette adresse.</p>
                    </div>
                </header>
                <div class="row g-3">
                    <Champ class="col-md-6" label="Prénom" v-model="form.contact.prenom" :erreur="erreur('contact.prenom')" autocomplete="given-name" />
                    <Champ class="col-md-6" label="Nom" v-model="form.contact.nom" :erreur="erreur('contact.nom')" autocomplete="family-name" />
                    <Champ class="col-md-6" label="Email" type="email" v-model="form.contact.email" :erreur="erreur('contact.email')" autocomplete="email" />
                    <Champ class="col-md-6" label="Confirmation de l'email" type="email" v-model="form.contact.emailConfirmation" :erreur="erreur('contact.emailConfirmation')" autocomplete="off" @paste.prevent />
                    <Champ class="col-md-6" label="Téléphone" type="tel" v-model="form.contact.telephone" :erreur="erreur('contact.telephone')" autocomplete="tel" />
                </div>
            </section>

            <!-- 4. Financement -->
            <section class="etape">
                <header class="etape__entete">
                    <span class="etape__numero">4</span>
                    <div><h2 class="etape__titre">Financement</h2></div>
                </header>
                <div class="choix" role="radiogroup" aria-label="Mode de financement">
                    <label v-for="f in c.financements" :key="f.valeur" class="choix__option" :class="{ 'choix__option--actif': !cpf && form.financement === f.valeur }">
                        <input type="radio" name="financement" :value="f.valeur" v-model="form.financement" @change="cpf = false">
                        <span>{{ f.libelle }}</span>
                    </label>
                    <label v-if="c.eligibleCpf" class="choix__option" :class="{ 'choix__option--actif': cpf }">
                        <input type="radio" name="financement" :checked="cpf" @change="cpf = true">
                        <span>Mon compte formation (CPF)</span>
                    </label>
                </div>
                <div v-if="erreur('financement')" class="erreur-bloc">{{ erreur('financement') }}</div>

                <div v-if="cpf" class="alerte-info mt-3">
                    Pour financer cette formation avec votre CPF, l'inscription se fait sur
                    <a href="https://www.moncompteformation.gouv.fr" target="_blank" rel="noopener">moncompteformation.gouv.fr</a>.
                </div>
                <div v-else-if="form.financement === 'france_travail'" class="row g-3 mt-1">
                    <Champ class="col-md-6" label="Identifiant demandeur d'emploi" v-model="form.identifiantFranceTravail" :erreur="erreur('identifiantFranceTravail')" placeholder="1234567A" maxlength="12" />
                </div>

                <div class="mt-3">
                    <Champ label="Remarque" facultatif type="textarea" v-model="form.remarque" :erreur="erreur('remarque')" />
                </div>
            </section>
        </div>

        <aside class="tunnel__resume">
            <div class="resume">
                <p class="resume__titre">Votre réservation</p>
                <dl class="resume__lignes">
                    <div><dt>Prix par participant</dt><dd>{{ euros(c.prixHtCentimes ?? 0) }} HT</dd></div>
                    <div><dt>Participants</dt><dd>× {{ nb }}</dd></div>
                    <div class="resume__total"><dt>Total HT</dt><dd>{{ euros(totalHt) }}</dd></div>
                    <div><dt>Total TTC (TVA {{ c.tauxTva }} %)</dt><dd>{{ euros(totalTtc) }}</dd></div>
                </dl>
                <p v-if="c.placesRestantes !== null" class="resume__places" :class="{ 'text-danger': depassePlaces }">
                    {{ c.placesRestantes }} place{{ c.placesRestantes > 1 ? 's' : '' }} restante{{ c.placesRestantes > 1 ? 's' : '' }} sur cette session
                </p>
                <div v-if="erreurGenerale" class="erreur-bloc" role="alert">{{ erreurGenerale }}</div>
                <button type="submit" class="btn btn-primary btn-lg w-100" :disabled="envoi || cpf || depassePlaces">
                    {{ envoi ? 'Vérification…' : 'Continuer' }}
                </button>
                <p class="resume__note">Étape suivante : choix du mode de paiement et confirmation.</p>
            </div>
        </aside>
    </form>
</template>
