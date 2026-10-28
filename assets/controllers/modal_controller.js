import { Controller } from '@hotwired/stimulus';

/*
 * Fenêtres modales de l'administration (éléments <dialog>).
 *
 * Bouton d'ouverture :
 *   data-action="modal#ouvrir" data-modal-cible-param="id-du-dialog"
 * Paramètres facultatifs pour réutiliser une même fenêtre sur plusieurs lignes :
 *   data-modal-action-param   → action du formulaire de la fenêtre
 *   data-modal-jeton-param    → valeur du champ _token
 *   data-modal-valeurs-param  → JSON { nomDuChamp: valeur } pour pré-remplir
 *   data-modal-textes-param   → JSON { clé: texte } affiché dans [data-modal-texte="clé"]
 * Fermeture : bouton data-action="modal#fermer", touche Échap, clic sur le voile.
 */
export default class extends Controller {
    ouvrir(event) {
        const p = event.params;
        const dialog = document.getElementById(p.cible);
        if (!dialog || typeof dialog.showModal !== 'function') return;

        const form = dialog.querySelector('form');
        if (form) {
            if (p.action) form.action = p.action;
            if (p.jeton) form.querySelector('input[name=_token]').value = p.jeton;
            Object.entries(p.valeurs ?? {}).forEach(([nom, valeur]) => {
                const champ = form.elements.namedItem(nom);
                if (!champ) return;
                if (champ.type === 'checkbox') champ.checked = Boolean(valeur);
                else champ.value = valeur ?? '';
            });
        }
        Object.entries(p.textes ?? {}).forEach(([cle, texte]) => {
            dialog.querySelectorAll(`[data-modal-texte="${cle}"]`).forEach((el) => {
                el.textContent = texte;
                el.closest('[data-modal-masquer-si-vide]')?.toggleAttribute('hidden', !texte);
            });
        });

        this.declencheur = event.currentTarget;
        dialog.showModal();
        (dialog.querySelector('[autofocus]') ?? dialog.querySelector('select, textarea, input:not([type=hidden])'))?.focus();

        if (!dialog.dataset.modalPret) {
            dialog.dataset.modalPret = '1';
            dialog.addEventListener('click', (e) => e.target === dialog && dialog.close());
            dialog.addEventListener('close', () => this.declencheur?.focus());
        }
    }

    fermer(event) {
        event.currentTarget.closest('dialog')?.close();
    }

    // Motif rapide : remplit le champ cible avec un texte prédéfini.
    remplir(event) {
        const champ = event.currentTarget.closest('form')?.elements.namedItem(event.params.champ);
        if (!champ) return;
        champ.value = event.params.texte;
        champ.focus();
    }
}
