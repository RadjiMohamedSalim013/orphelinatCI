import { Controller } from '@hotwired/stimulus';

/*
 * Confort de saisie dans un formulaire :
 *  - compteur de caractères sous chaque zone de texte limitée (maxlength) ;
 *  - champs fichier : glisser-déposer, nom et poids du fichier, aperçu des images.
 */
export default class extends Controller {
    connect() {
        this.element.querySelectorAll('textarea[maxlength]').forEach((zone) => this.compteur(zone));
        this.element.querySelectorAll('input[type=file]').forEach((champ) => this.depot(champ));
    }

    compteur(zone) {
        const max = Number(zone.getAttribute('maxlength'));
        const info = document.createElement('p');
        info.className = 'mt-1 text-right text-xs tabular-nums text-ink-400 transition-colors';
        info.setAttribute('aria-live', 'polite');
        zone.insertAdjacentElement('afterend', info);

        const maj = () => {
            const n = zone.value.length;
            info.textContent = `${n} / ${max}`;
            info.classList.toggle('text-amber-700', n > max * 0.9);
        };
        zone.addEventListener('input', maj);
        maj();
    }

    depot(champ) {
        const apercu = document.createElement('div');
        apercu.className = 'mt-2 hidden items-center gap-3 rounded-md border border-ink-200 bg-white p-2 text-sm animate-fade-up';
        apercu.setAttribute('aria-live', 'polite');
        champ.insertAdjacentElement('afterend', apercu);

        ['dragenter', 'dragover'].forEach((type) => champ.addEventListener(type, () => champ.classList.add('!border-brand-500', '!bg-brand-50')));
        ['dragleave', 'drop'].forEach((type) => champ.addEventListener(type, () => champ.classList.remove('!border-brand-500', '!bg-brand-50')));

        champ.addEventListener('change', () => {
            apercu.replaceChildren();
            const fichier = champ.files?.[0];
            if (!fichier) {
                apercu.classList.replace('flex', 'hidden');
                return;
            }

            if (fichier.type.startsWith('image/')) {
                const img = document.createElement('img');
                img.src = URL.createObjectURL(fichier);
                img.alt = '';
                img.className = 'h-12 w-16 shrink-0 rounded object-cover';
                img.onload = () => URL.revokeObjectURL(img.src);
                apercu.append(img);
            } else {
                const icone = document.createElement('span');
                icone.className = 'flex h-12 w-12 shrink-0 items-center justify-center rounded bg-red-50 text-red-700';
                icone.innerHTML = '<i class="fa-solid fa-file-pdf" aria-hidden="true"></i>';
                apercu.append(icone);
            }

            const texte = document.createElement('div');
            texte.className = 'min-w-0';
            const nom = document.createElement('p');
            nom.className = 'truncate font-medium text-ink-800';
            nom.textContent = fichier.name;
            const poids = document.createElement('p');
            poids.className = 'text-xs text-ink-500';
            poids.textContent = `${(fichier.size / 1024 / 1024).toFixed(2)} Mo`;
            texte.append(nom, poids);
            apercu.append(texte);
            apercu.classList.replace('hidden', 'flex');
        });
    }
}
