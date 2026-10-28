import { Controller } from '@hotwired/stimulus';

/*
 * Petits comportements d'interface :
 *  - sync    : une case à cocher affiche ses cibles "panel" quand elle est cochée ;
 *  - loading : à l'envoi du formulaire, désactive les boutons "submit" et affiche
 *              un indicateur (évite les doubles soumissions) ; la cible "busy"
 *              (ou les éléments désignés par data-ui-busy-selector-value)
 *              reçoit aria-busy pour afficher un état de chargement.
 */
export default class extends Controller {
    static targets = ['panel', 'submit', 'busy'];
    static values = { busySelector: String };

    connect() {
        const checkbox = this.element.querySelector('[data-action~="change->ui#sync"]');
        if (checkbox) this.panelTargets.forEach((panel) => (panel.hidden = !checkbox.checked));
    }

    sync(event) {
        this.panelTargets.forEach((panel) => {
            panel.hidden = !event.target.checked;
            if (!panel.hidden) panel.querySelector('input, select, textarea')?.focus();
        });
    }

    loading(event) {
        if (event.defaultPrevented) return;

        this.submitTargets.forEach((button) => {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            const label = button.dataset.loadingLabel;
            if (label) {
                const spinner = document.createElement('span');
                spinner.className = 'om-spinner';
                spinner.setAttribute('aria-hidden', 'true');
                button.replaceChildren(spinner, document.createTextNode(' ' + label));
            }
        });
        const busy = [...this.busyTargets, ...(this.busySelectorValue ? document.querySelectorAll(this.busySelectorValue) : [])];
        busy.forEach((element) => element.setAttribute('aria-busy', 'true'));
    }
}
