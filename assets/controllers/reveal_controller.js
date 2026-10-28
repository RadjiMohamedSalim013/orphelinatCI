import { Controller } from '@hotwired/stimulus';

/*
 * Fait apparaître les éléments [data-reveal] lorsqu'ils entrent à l'écran.
 * Le décalage en cascade se règle avec la variable CSS --i (style="--i: 2").
 */
export default class extends Controller {
    connect() {
        document.documentElement.dataset.reveal = 'actif';
        const elements = this.element.querySelectorAll('[data-reveal]');
        if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            elements.forEach((el) => el.classList.add('is-visible'));
            return;
        }

        this.observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    this.observer.unobserve(entry.target);
                }
            });
        }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

        elements.forEach((el) => this.observer.observe(el));
    }

    disconnect() {
        this.observer?.disconnect();
    }
}
