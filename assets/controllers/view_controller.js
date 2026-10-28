import { Controller } from '@hotwired/stimulus';

/*
 * Bascule « Liste / Carte » des résultats sur petit écran.
 * Sur grand écran, les deux vues sont visibles côte à côte (classes lg:*).
 */
export default class extends Controller {
    static targets = ['tab', 'panel'];

    show(event) {
        const name = event.currentTarget.dataset.view;

        this.tabTargets.forEach((tab) => tab.setAttribute('aria-selected', String(tab.dataset.view === name)));
        this.panelTargets.forEach((panel) => panel.toggleAttribute('data-active', panel.dataset.view === name));

        // Leaflet recalcule sa taille quand la carte devient visible.
        window.dispatchEvent(new Event('resize'));
    }
}
