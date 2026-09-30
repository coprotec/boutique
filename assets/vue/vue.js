import { createApp } from 'vue';

import ReservationForm from './reservation/ReservationForm.vue';

/*
 * Point d'entrée Vue unique, avec le registre des composants (organisation de /coplanif).
 *
 * Différence volontaire avec /coplanif : pas de <div id="app"> global compilé dans le navigateur. Sur un site
 * public, un texte saisi par un client et réaffiché par Twig serait interprété par Vue (injection de template).
 * Chaque composant est donc monté sur l'élément qui le déclare, avec ses props en JSON :
 *
 *   <div data-vue="reservation-form" data-props="{{ props|json_encode|e('html_attr') }}"></div>
 */
const composants = {
    'reservation-form': ReservationForm,
};

document.querySelectorAll('[data-vue]').forEach((el) => {
    const composant = composants[el.dataset.vue];
    if (!composant) {
        console.error(`Composant Vue inconnu : ${el.dataset.vue}`);
        return;
    }
    createApp(composant, JSON.parse(el.dataset.props || '{}')).mount(el);
});
