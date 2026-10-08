<script setup>
import { onBeforeUnmount, ref, useId } from 'vue';

// Adresse française avec suggestions de la Base Adresse Nationale (Géoplateforme IGN, sans clé, ex-api-adresse.data.gouv.fr).
// Le choix d'une suggestion émet { adresse, codePostal, ville } ; la saisie libre reste possible si l'API ne répond pas.
// Combobox ARIA : flèches haut/bas, Entrée pour choisir, Échap pour fermer.
defineOptions({ inheritAttrs: false });
const props = defineProps({
    label: { type: String, required: true },
    erreur: { type: String, default: null },
    class: { type: String, default: '' },
});
const emit = defineEmits(['choisir']);
const modele = defineModel({ type: String, default: '' });
const id = useId();

const API = 'https://data.geopf.fr/geocodage/search';
const suggestions = ref([]);
const active = ref(-1);
let minuterie = null;
let requete = null;

function rechercher() {
    clearTimeout(minuterie);
    active.value = -1;
    const q = modele.value.trim();
    if (q.length < 3) {
        suggestions.value = [];
        return;
    }
    minuterie = setTimeout(async () => {
        requete?.abort();
        requete = new AbortController();
        try {
            const url = `${API}?${new URLSearchParams({ q, autocomplete: 1, limit: 6, index: 'address' })}`;
            const reponse = await fetch(url, { signal: requete.signal });
            const data = reponse.ok ? await reponse.json() : { features: [] };
            // Une commune seule ne remplit pas la ligne d'adresse : on garde numéros, voies et lieux-dits.
            suggestions.value = (data.features ?? [])
                .map((f) => f.properties)
                .filter((p) => p.type !== 'municipality');
        } catch {
            suggestions.value = []; // API indisponible ou requête annulée : saisie manuelle.
        }
    }, 250);
}

function choisir(p) {
    modele.value = p.name;
    emit('choisir', { adresse: p.name, codePostal: p.postcode, ville: p.city });
    suggestions.value = [];
}

function clavier(e) {
    const n = suggestions.value.length;
    if (!n) return;
    if (e.key === 'ArrowDown') active.value = (active.value + 1) % n;
    else if (e.key === 'ArrowUp') active.value = (active.value - 1 + n) % n;
    else if (e.key === 'Enter' && active.value >= 0) choisir(suggestions.value[active.value]);
    else if (e.key === 'Escape') suggestions.value = [];
    else return;
    e.preventDefault();
}

// Laisse le temps au clic sur une suggestion d'être pris en compte avant de fermer la liste.
const fermer = () => setTimeout(() => { suggestions.value = []; }, 150);

onBeforeUnmount(() => {
    clearTimeout(minuterie);
    requete?.abort();
});
</script>

<template>
    <div :class="props.class" class="adresse-fr">
        <label :for="id" class="form-label">{{ label }}</label>
        <input :id="id" v-model="modele" type="text" class="form-control" :class="{ 'is-invalid': erreur }"
               role="combobox" aria-autocomplete="list" :aria-expanded="suggestions.length > 0" :aria-controls="`${id}-liste`"
               :aria-activedescendant="active >= 0 ? `${id}-s${active}` : null"
               :aria-invalid="!!erreur" :aria-describedby="`${id}-aide` + (erreur ? ` ${id}-erreur` : '')"
               autocomplete="off" @input="rechercher" @keydown="clavier" @blur="fermer" v-bind="$attrs">
        <ul v-show="suggestions.length" :id="`${id}-liste`" class="adresse-fr__liste list-group" role="listbox">
            <li v-for="(s, i) in suggestions" :id="`${id}-s${i}`" :key="s.id" role="option" :aria-selected="i === active"
                class="list-group-item list-group-item-action" :class="{ active: i === active }" @mousedown.prevent="choisir(s)">
                {{ s.label }}
            </li>
        </ul>
        <div v-if="erreur" :id="`${id}-erreur`" class="invalid-feedback">{{ erreur }}</div>
        <div :id="`${id}-aide`" class="form-text">Commencez à taper puis choisissez l'adresse proposée, ou complétez à la main.</div>
    </div>
</template>
