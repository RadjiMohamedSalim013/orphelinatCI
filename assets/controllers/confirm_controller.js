import { Controller } from '@hotwired/stimulus';

/*
 * Confirmation des actions sensibles, placée sur <body>.
 * Tout formulaire portant data-confirm="Message" ouvre la fenêtre de
 * confirmation (élément <dialog> natif : focus piégé, fermeture par Échap).
 * Options : data-confirm-title, data-confirm-label, data-confirm-variant="danger".
 * Sans JavaScript, le formulaire est simplement envoyé.
 */
export default class extends Controller {
    static targets = ['dialog', 'title', 'message', 'accept'];

    intercept(event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed === '1') return;
        if (!this.hasDialogTarget || typeof this.dialogTarget.showModal !== 'function') return;

        event.preventDefault();
        event.stopImmediatePropagation();

        this.pending = { form, submitter: event.submitter ?? null };
        this.titleTarget.textContent = form.dataset.confirmTitle || 'Confirmer l’action';
        this.messageTarget.textContent = form.dataset.confirm;
        this.acceptTarget.textContent = form.dataset.confirmLabel || 'Confirmer';
        this.acceptTarget.className = form.dataset.confirmVariant === 'danger' ? 'om-btn-danger' : 'om-btn-primary';

        this.returnFocus = document.activeElement;
        this.dialogTarget.showModal();
        this.acceptTarget.focus();
    }

    accept() {
        const { form, submitter } = this.pending ?? {};
        this.dialogTarget.close();
        if (!form) return;

        form.dataset.confirmed = '1';
        form.requestSubmit(submitter && submitter.form === form ? submitter : undefined);
        delete form.dataset.confirmed;
        this.pending = null;
    }

    cancel() {
        this.dialogTarget.close();
    }

    closed() {
        this.pending = null;
        this.returnFocus?.focus?.();
    }

    backdrop(event) {
        if (event.target === this.dialogTarget) this.cancel();
    }
}
