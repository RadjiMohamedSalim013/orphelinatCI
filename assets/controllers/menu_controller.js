import { Controller } from '@hotwired/stimulus';

/*
 * Menu repliable (navigation mobile, tiroir d'administration).
 * Ouverture au bouton, fermeture au bouton, par Échap, au clic sur le voile
 * ou en suivant un lien ; le focus est déplacé dans le menu puis restitué.
 */
export default class extends Controller {
    static targets = ['panel', 'button', 'overlay'];

    connect() {
        this.onKey = (event) => {
            if (event.key === 'Escape' && this.isOpen) {
                this.close();
                this.buttonTarget.focus();
            }
        };
        document.addEventListener('keydown', this.onKey);
    }

    disconnect() {
        document.removeEventListener('keydown', this.onKey);
        document.body.classList.remove('overflow-hidden');
    }

    get isOpen() {
        return this.buttonTarget.getAttribute('aria-expanded') === 'true';
    }

    toggle() {
        this.isOpen ? this.close() : this.open();
    }

    open() {
        this.buttonTarget.setAttribute('aria-expanded', 'true');
        this.panelTarget.dataset.open = 'true';
        if (this.hasOverlayTarget) {
            this.overlayTarget.hidden = false;
            document.body.classList.add('overflow-hidden');
        }
        this.panelTarget.querySelector('a, button')?.focus();
    }

    close() {
        this.buttonTarget.setAttribute('aria-expanded', 'false');
        delete this.panelTarget.dataset.open;
        if (this.hasOverlayTarget) {
            this.overlayTarget.hidden = true;
            document.body.classList.remove('overflow-hidden');
        }
    }
}
