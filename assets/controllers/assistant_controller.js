import { Controller } from '@hotwired/stimulus';

/*
 * Assistant en plusieurs étapes pour un long formulaire.
 *
 * - Une étape (cible "etape") visible à la fois, avec barre de progression et pastilles.
 * - « Suivant » vérifie seulement l'étape en cours et place les messages sous les champs.
 * - Après une erreur du serveur, l'assistant s'ouvre sur la première étape concernée.
 * - La dernière étape affiche un récapitulatif (cible "recap") avant l'envoi.
 * Sans JavaScript, toutes les étapes restent affichées : le formulaire fonctionne tel quel.
 */
export default class extends Controller {
    static targets = ['etape', 'pastille', 'barre', 'precedent', 'suivant', 'envoi', 'navigation', 'recap', 'compteur'];

    connect() {
        this.index = 0;
        this.atteinte = 0;
        this.navigationTargets.forEach((el) => el.classList.remove('hidden'));

        const enErreur = this.etapeTargets.findIndex((etape) => etape.querySelector('[aria-invalid=true], .om-error'));
        this.atteinte = Math.max(0, enErreur);
        this.afficher(Math.max(0, enErreur), false);

        this.element.addEventListener('input', (event) => this.effacerErreur(event.target));
        this.element.addEventListener('change', (event) => this.effacerErreur(event.target));
    }

    suivant() {
        if (!this.valider(this.etapeTargets[this.index])) return;
        this.atteinte = Math.max(this.atteinte, this.index + 1);
        this.afficher(this.index + 1);
    }

    precedent() {
        this.afficher(this.index - 1);
    }

    aller(event) {
        const cible = Number(event.currentTarget.dataset.etape);
        if (cible <= this.atteinte) this.afficher(cible);
    }

    // Dernier contrôle avant l'envoi : toutes les étapes, en ouvrant la première incomplète.
    envoyer(event) {
        const invalide = this.etapeTargets.findIndex((etape) => !this.valider(etape, false));
        if (invalide !== -1) {
            event.preventDefault();
            event.stopImmediatePropagation();
            this.afficher(invalide);
            this.valider(this.etapeTargets[invalide]);
        }
    }

    afficher(index, animer = true) {
        const total = this.etapeTargets.length;
        index = Math.max(0, Math.min(total - 1, index));
        const sens = index >= this.index ? '24px' : '-24px';
        this.index = index;

        this.etapeTargets.forEach((etape, i) => {
            etape.hidden = i !== index;
            etape.classList.remove('om-etape-entree');
            if (i === index && animer) {
                etape.style.setProperty('--sens', sens);
                void etape.offsetWidth;
                etape.classList.add('om-etape-entree');
            }
        });

        this.pastilleTargets.forEach((pastille, i) => {
            pastille.dataset.etat = i < index ? 'fait' : i === index ? 'actuel' : 'avenir';
            pastille.disabled = i > this.atteinte;
            if (i === index) pastille.setAttribute('aria-current', 'step');
            else pastille.removeAttribute('aria-current');
        });

        if (this.hasBarreTarget) this.barreTarget.style.width = `${((index + 1) / total) * 100}%`;
        if (this.hasCompteurTarget) this.compteurTarget.textContent = `Étape ${index + 1} sur ${total}`;

        const derniere = index === total - 1;
        this.precedentTargets.forEach((b) => (b.hidden = index === 0));
        this.suivantTargets.forEach((b) => (b.hidden = derniere));
        this.envoiTargets.forEach((b) => (b.hidden = !derniere));
        if (derniere) this.recapituler();

        if (animer) {
            const titre = this.etapeTargets[index].querySelector('legend, h2');
            this.element.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
            titre?.setAttribute('tabindex', '-1');
            titre?.focus({ preventScroll: true });
        }
    }

    valider(etape, signaler = true) {
        let premier = null;

        this.champs(etape).forEach((champ) => {
            const message = this.message(champ);
            if (message) {
                premier ??= champ;
                if (signaler) this.signaler(champ, message);
            }
        });

        const regle = etape.dataset.regle === 'localisation' ? this.regleLocalisation(etape) : null;
        if (regle) {
            premier ??= regle;
            if (signaler) this.signaler(regle, 'Choisissez une commune dans la liste, ou cochez la case pour la saisir.');
        }

        if (premier && signaler) {
            premier.focus();
            etape.classList.remove('om-secousse');
            void etape.offsetWidth;
            etape.classList.add('om-secousse');
        }

        return null === premier;
    }

    // Champs visibles de l'étape (ceux d'un panneau masqué sont ignorés).
    champs(etape) {
        return [...etape.querySelectorAll('input, select, textarea')].filter(
            (c) => !['hidden', 'button', 'submit'].includes(c.type) && !c.closest('[hidden], .om-hp') && !c.disabled,
        );
    }

    message(champ) {
        const v = champ.validity;
        if (v.valid) return null;
        if (v.valueMissing) return champ.type === 'checkbox' ? 'Cochez cette case pour continuer.' : 'Ce champ est obligatoire.';
        if (v.typeMismatch) return champ.type === 'email' ? 'Adresse e-mail invalide (ex. nom@exemple.org).' : 'Format invalide.';
        if (v.tooShort) return `Au moins ${champ.minLength} caractères (actuellement ${champ.value.length}).`;
        if (v.tooLong) return `Au plus ${champ.maxLength} caractères.`;
        return champ.validationMessage || 'Valeur invalide.';
    }

    regleLocalisation(etape) {
        const commune = etape.querySelector('select[name$="[commune]"]');
        const absente = etape.querySelector('input[name$="[communeAbsente]"]');
        return commune && !commune.value && !absente?.checked ? commune : null;
    }

    signaler(champ, message) {
        this.effacerErreur(champ);
        champ.setAttribute('aria-invalid', 'true');
        if (!champ.classList.contains('om-checkbox')) champ.classList.add('om-input-error');

        const erreur = document.createElement('p');
        erreur.className = 'om-error animate-fade-up';
        erreur.dataset.client = '';
        erreur.id = `${champ.id}_client`;
        erreur.innerHTML = '<i class="fa-solid fa-circle-exclamation mt-0.5" aria-hidden="true"></i>';
        erreur.append(document.createTextNode(message));
        champ.setAttribute('aria-describedby', [champ.getAttribute('aria-describedby'), erreur.id].filter(Boolean).join(' '));

        const ancre = champ.closest('label') ?? champ;
        ancre.insertAdjacentElement('afterend', erreur);
    }

    effacerErreur(champ) {
        if (!(champ instanceof HTMLElement) || !champ.id) return;
        document.getElementById(`${champ.id}_client`)?.remove();
        if (champ.validity?.valid) {
            champ.removeAttribute('aria-invalid');
            champ.classList.remove('om-input-error');
        }
        if (champ.name?.endsWith('[communeAbsente]') || champ.name?.endsWith('[commune]')) {
            const commune = this.element.querySelector('select[name$="[commune]"]');
            document.getElementById(`${commune?.id}_client`)?.remove();
            commune?.classList.remove('om-input-error');
        }
    }

    recapituler() {
        if (!this.hasRecapTarget) return;
        const valeur = (suffixe) => {
            const champ = this.element.querySelector(`[name$="[${suffixe}]"]`);
            if (!champ) return '';
            if (champ.tagName === 'SELECT') return champ.value ? champ.selectedOptions[0]?.textContent.trim() : '';
            return champ.value.trim();
        };

        const absente = this.element.querySelector('input[name$="[communeAbsente]"]')?.checked;
        const lieu = absente ? [valeur('communeSaisie'), valeur('ville'), valeur('pays')] : [valeur('commune')];
        const lignes = [
            ['Établissement', valeur('nom')],
            ['Localisation', [valeur('quartier'), ...lieu].filter(Boolean).join(', ')],
            ['Adresse', valeur('adresse')],
            ['Contacts', [valeur('telephone'), valeur('email')].filter(Boolean).join(' · ') || 'Non renseignés'],
        ];

        const liste = document.createElement('dl');
        liste.className = 'grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2';
        lignes.forEach(([libelle, texte]) => {
            const bloc = document.createElement('div');
            const dt = document.createElement('dt');
            dt.className = 'text-xs font-medium uppercase tracking-wide text-ink-500';
            dt.textContent = libelle;
            const dd = document.createElement('dd');
            dd.className = 'mt-0.5 font-medium text-ink-900';
            dd.textContent = texte || '—';
            bloc.append(dt, dd);
            liste.append(bloc);
        });
        this.recapTarget.replaceChildren(liste);
        this.recapTarget.closest('[data-recap-bloc]')?.removeAttribute('hidden');
    }
}
