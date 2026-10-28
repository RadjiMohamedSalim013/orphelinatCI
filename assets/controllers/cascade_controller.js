import { Controller } from '@hotwired/stimulus';

/*
 * Listes déroulantes en cascade (pays > ville > commune > quartier).
 *
 * Chaque <select data-cascade-target="select"> peut déclarer :
 *  - data-cascade-name   : son nom logique ;
 *  - data-cascade-child  : le nom de la liste qui dépend de lui ;
 *  - data-cascade-url    : URL renvoyant [{id, nom}] (la valeur du parent est ajoutée à la fin) ;
 *  - data-cascade-filter : à défaut d'URL, attribut data-* des <option> à comparer
 *                          à la valeur du parent (filtrage local, sans requête).
 *
 * Sans JavaScript, les listes restent utilisables : le serveur les pré-filtre.
 */
export default class extends Controller {
    static targets = ['select'];

    connect() {
        this.selectTargets.forEach((select) => {
            if (select.dataset.cascadeFilter) {
                const parent = this.parentOf(select);
                if (parent) this.filterLocally(select, parent.value, false);
            }
        });
    }

    change(event) {
        const childName = event.target.dataset.cascadeChild;
        if (childName) this.refresh(this.byName(childName), event.target.value);
    }

    async refresh(select, parentValue) {
        if (!select) return;

        if (select.dataset.cascadeFilter) {
            this.filterLocally(select, parentValue, true);
        } else if (select.dataset.cascadeUrl) {
            await this.load(select, parentValue);
        }

        const grandChild = select.dataset.cascadeChild;
        if (grandChild) this.refresh(this.byName(grandChild), select.value);
    }

    async load(select, parentValue) {
        const placeholder = select.options[0]?.cloneNode(true);
        select.value = '';

        if (!parentValue) {
            this.fill(select, placeholder, []);
            select.disabled = true;
            return;
        }

        select.disabled = true;
        select.setAttribute('aria-busy', 'true');
        this.fill(select, this.option('', 'Chargement…'), []);

        try {
            const response = await fetch(select.dataset.cascadeUrl + encodeURIComponent(parentValue), {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) throw new Error(response.statusText);
            const items = await response.json();
            this.fill(select, placeholder, items);
            select.disabled = items.length === 0;
        } catch (e) {
            this.fill(select, this.option('', 'Liste indisponible, réessayez'), []);
        } finally {
            select.removeAttribute('aria-busy');
        }
    }

    fill(select, placeholder, items) {
        select.replaceChildren();
        if (placeholder) select.append(placeholder);
        items.forEach((item) => select.append(this.option(item.id, item.nom)));
    }

    filterLocally(select, parentValue, reset) {
        const attribute = select.dataset.cascadeFilter;
        let visible = 0;

        select.querySelectorAll('option').forEach((option) => {
            if (option.value === '') return;
            const match = !parentValue || option.dataset[attribute] === String(parentValue);
            option.hidden = !match;
            option.disabled = !match;
            if (match) visible++;
        });
        select.querySelectorAll('optgroup').forEach((group) => {
            group.hidden = ![...group.querySelectorAll('option')].some((o) => !o.hidden);
        });

        if (reset && select.selectedOptions[0]?.hidden) select.value = '';
        select.disabled = Boolean(parentValue) && visible === 0;
    }

    option(value, label) {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        return option;
    }

    byName(name) {
        return this.selectTargets.find((s) => s.dataset.cascadeName === name);
    }

    parentOf(select) {
        return this.selectTargets.find((s) => s.dataset.cascadeChild === select.dataset.cascadeName);
    }
}
