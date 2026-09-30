<script setup>
import { useId } from 'vue';

// Champ de formulaire Bootstrap avec son message d'erreur serveur relié (aria-describedby).
defineOptions({ inheritAttrs: false });
const props = defineProps({
    label: { type: String, required: true },
    type: { type: String, default: 'text' },
    erreur: { type: String, default: null },
    options: { type: Array, default: () => [] },
    facultatif: { type: Boolean, default: false },
    class: { type: String, default: '' },
});
const modele = defineModel();
const id = useId();
</script>

<template>
    <div :class="props.class">
        <label :for="id" class="form-label">{{ label }} <span v-if="facultatif" class="text-body-secondary fw-normal">(facultatif)</span></label>
        <select v-if="type === 'select'" :id="id" v-model="modele" class="form-select" :class="{ 'is-invalid': erreur }"
                :aria-invalid="!!erreur" :aria-describedby="erreur ? `${id}-erreur` : null" v-bind="$attrs">
            <option value="" disabled>Choisir…</option>
            <option v-for="o in options" :key="o" :value="o">{{ o }}</option>
        </select>
        <textarea v-else-if="type === 'textarea'" :id="id" v-model="modele" class="form-control" rows="3" :class="{ 'is-invalid': erreur }"
                  :aria-invalid="!!erreur" :aria-describedby="erreur ? `${id}-erreur` : null" v-bind="$attrs"></textarea>
        <input v-else :id="id" v-model="modele" :type="type" class="form-control" :class="{ 'is-invalid': erreur }"
               :aria-invalid="!!erreur" :aria-describedby="erreur ? `${id}-erreur` : null" v-bind="$attrs">
        <div v-if="erreur" :id="`${id}-erreur`" class="invalid-feedback">{{ erreur }}</div>
    </div>
</template>
