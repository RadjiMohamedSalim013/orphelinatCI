import { Controller } from '@hotwired/stimulus';

/*
 * Fait défiler un nombre de 0 jusqu'à sa valeur réelle quand il devient visible.
 * Le nombre final est déjà écrit dans le HTML : sans JavaScript, il s'affiche tel quel.
 */
export default class extends Controller {
    static values = { cible: Number };

    connect() {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) return;

        // La valeur réelle reste affichée tant que l'animation n'a pas commencé.
        this.observer = new IntersectionObserver(([entry]) => {
            if (!entry.isIntersecting) return;
            this.observer.disconnect();
            this.animer();
        });
        this.observer.observe(this.element);
    }

    disconnect() {
        this.observer?.disconnect();
    }

    animer() {
        const cible = this.cibleValue;
        const duree = Math.min(1400, 500 + cible * 40);
        const debut = performance.now();

        const etape = (maintenant) => {
            const t = Math.min(1, (maintenant - debut) / duree);
            const adouci = 1 - Math.pow(1 - t, 3);
            this.element.textContent = String(Math.round(cible * adouci));
            if (t < 1) requestAnimationFrame(etape);
        };
        requestAnimationFrame(etape);
    }
}
