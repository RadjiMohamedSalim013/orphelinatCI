import { Controller } from '@hotwired/stimulus';

/*
 * Notification : se ferme d'elle-même après un délai (sauf erreurs),
 * pause au survol et au focus, fermeture au bouton ou avec Échap.
 */
export default class extends Controller {
    static values = { delay: { type: Number, default: 6000 } };

    connect() {
        this.onKey = (event) => event.key === 'Escape' && this.dismiss();
        document.addEventListener('keydown', this.onKey);
        if (this.delayValue > 0) this.start();
    }

    disconnect() {
        document.removeEventListener('keydown', this.onKey);
        clearTimeout(this.timer);
    }

    start() {
        if (this.delayValue > 0) this.timer = setTimeout(() => this.dismiss(), this.delayValue);
    }

    pause() {
        clearTimeout(this.timer);
    }

    dismiss() {
        this.element.style.transition = 'opacity .15s ease';
        this.element.style.opacity = '0';
        setTimeout(() => this.element.remove(), 150);
    }
}
