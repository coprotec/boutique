<script setup>
import { computed, useId } from 'vue';

// Champ de formulaire Bootstrap avec son aide et son message d'erreur serveur reliés (aria-describedby).
// Options d'un select : chaînes, ou objets { valeur, libelle }.
defineOptions({ inheritAttrs: false });
const props = defineProps({
    label: { type: String, required: true },
    type: { type: String, default: 'text' },
    erreur: { type: String, default: null },
    aide: { type: String, default: null },
    options: { type: Array, default: () => [] },
    facultatif: { type: Boolean, default: false },
    class: { type: String, default: '' },
});
const modele = defineModel();
const id = useId();
const decrit = computed(() => [props.aide && `${id}-aide`, props.erreur && `${id}-erreur`].filter(Boolean).join(' ') || null);
const choix = computed(() => props.options.map((o) => (typeof o === 'string' ? { valeur: o, libelle: o } : o)));
</script>

<template>
    <div :class="props.class">
        <label :for="id" class="form-label">{{ label }} <span v-if="facultatif" class="text-body-secondary fw-normal">(facultatif)</span></label>
        <select v-if="type === 'select'" :id="id" v-model="modele" class="form-select" :class="{ 'is-invalid': erreur }"
                :aria-invalid="!!erreur" :aria-describedby="decrit" v-bind="$attrs">
            <option value="" disabled>Choisir…</option>
            <option v-for="o in choix" :key="o.valeur" :value="o.valeur">{{ o.libelle }}</option>
        </select>
        <textarea v-else-if="type === 'textarea'" :id="id" v-model="modele" class="form-control" rows="3" :class="{ 'is-invalid': erreur }"
                  :aria-invalid="!!erreur" :aria-describedby="decrit" v-bind="$attrs"></textarea>
        <input v-else :id="id" v-model="modele" :type="type" class="form-control" :class="{ 'is-invalid': erreur }"
               :aria-invalid="!!erreur" :aria-describedby="decrit" v-bind="$attrs">
        <div v-if="erreur" :id="`${id}-erreur`" class="invalid-feedback">{{ erreur }}</div>
        <div v-if="aide" :id="`${id}-aide`" class="form-text">{{ aide }}</div>
    </div>
</template>
