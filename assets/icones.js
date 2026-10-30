/*
 * Icônes Lucide utilisées par les contrôleurs JavaScript (mêmes tracés que
 * assets/icons/lucide/*.svg, rendus côté serveur par ux_icon()).
 */
const TRACES = {
    'circle-alert': '<circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>',
    'file-text': '<path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"/><path d="M14 2v5a1 1 0 0 0 1 1h5M10 9H8m8 4H8m8 4H8"/>',
};

export function icone(nom, classe = '') {
    const gabarit = document.createElement('template');
    gabarit.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="1em" height="1em" class="om-icone ${classe}" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2">${TRACES[nom] ?? ''}</g></svg>`;

    return gabarit.content.firstElementChild;
}
