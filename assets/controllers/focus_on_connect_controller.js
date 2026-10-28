import { Controller } from '@hotwired/stimulus';

/*
 * Place le focus sur l'élément à son apparition (résumé d'erreurs d'un
 * formulaire) pour que les lecteurs d'écran l'annoncent immédiatement.
 */
export default class extends Controller {
    connect() {
        this.element.focus({ preventScroll: false });
    }
}
