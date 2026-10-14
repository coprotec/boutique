<script setup>
import { ref } from 'vue';

// Panneau flottant de test : affiché seulement si BOUTIQUE_OUTILS_TEST=1 (dev, préprod). Ne touche pas au formulaire
// lui-même : il émet des demandes que ReservationForm applique.
const emit = defineEmits(['remplir', 'vider']);
const pays = ref('FR');
const financement = ref('aucun');

const remplir = (envoyer = false) => emit('remplir', { pays: pays.value, financement: financement.value, envoyer });
</script>

<template>
    <details class="outils-test">
        <summary class="outils-test__titre">Outils de test</summary>
        <p class="outils-test__note">Visible en dev et en préprod uniquement. Données aléatoires, emails en @example.com.</p>

        <label class="form-label small mb-1" for="outils-pays">Société</label>
        <select id="outils-pays" v-model="pays" class="form-select form-select-sm mb-2">
            <option value="FR">En France</option>
            <option value="CH">En Suisse (sans SIRET)</option>
        </select>

        <label class="form-label small mb-1" for="outils-financement">Financement</label>
        <select id="outils-financement" v-model="financement" class="form-select form-select-sm mb-3">
            <option value="aucun">Aucun (CB ou virement)</option>
            <option value="france_travail">France Travail</option>
        </select>

        <div class="d-grid gap-2">
            <button type="button" class="btn btn-sm btn-primary" @click="remplir()">Remplir le formulaire</button>
            <button type="button" class="btn btn-sm btn-outline-primary" @click="remplir(true)">Remplir et continuer</button>
            <button type="button" class="btn btn-sm btn-link text-danger" @click="emit('vider')">Vider</button>
        </div>
    </details>
</template>
