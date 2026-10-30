/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import L from 'leaflet';

/*
 * Carte Leaflet, chargée seulement sur les pages qui en affichent une (contrôleur paresseux).
 *
 * - points   : [{id, nom, latitude, longitude, commune, verifie, url}]
 * - editable : un clic place le marqueur et remplit les champs latitude/longitude
 *              (saisie manuelle par l'administrateur, aucun géocodage automatique).
 */
export default class extends Controller {
    static targets = ['canvas', 'latitude', 'longitude', 'message'];
    static values = {
        points: Array,
        tileUrl: String,
        attribution: String,
        editable: Boolean,
        center: { type: Array, default: [7.54, -5.55] },
        zoom: { type: Number, default: 7 },
    };

    connect() {
        // Retirer le squelette avant l'initialisation : Leaflet décide alors lui-même du positionnement.
        if (this.hasCanvasTarget) this.canvasTarget.classList.remove('om-skeleton');
        this.map = L.map(this.hasCanvasTarget ? this.canvasTarget : this.element, { scrollWheelZoom: false });
        L.tileLayer(this.tileUrlValue, { attribution: this.attributionValue, maxZoom: 19 }).addTo(this.map);

        const markers = this.pointsValue
            .filter((p) => p.latitude !== null && p.longitude !== null)
            .map((p) => L.marker([Number(p.latitude), Number(p.longitude)], { title: p.nom, icon: this.repere(p.verifie) }).bindPopup(this.popup(p)));

        if (markers.length > 0) {
            const group = L.featureGroup(markers).addTo(this.map);
            this.map.fitBounds(group.getBounds().pad(0.2), { maxZoom: 15 });
        } else {
            this.map.setView(this.centerValue, this.zoomValue);
        }

        if (this.editableValue) this.enableEditing(markers[0] ?? null);
        this.map.on('click', () => this.map.scrollWheelZoom.enable());
    }

    disconnect() {
        this.map?.remove();
    }

    // Repère aux couleurs du site : vert pour une fiche vérifiée, orange sinon.
    repere(verifie) {
        const couleur = verifie ? 'var(--trust-700)' : 'var(--brand-600)';

        return L.divIcon({
            className: 'om-repere',
            html: `<svg viewBox="0 0 32 42" width="32" height="42" aria-hidden="true"><path d="M16 1C7.7 1 1 7.6 1 15.8 1 27 16 41 16 41s15-14 15-25.2C31 7.6 24.3 1 16 1Z" style="fill: rgb(${couleur})" stroke="#fff" stroke-width="2"/><circle cx="16" cy="16" r="5.5" fill="#fff"/></svg>`,
            iconSize: [32, 42],
            iconAnchor: [16, 41],
            popupAnchor: [0, -36],
        });
    }

    enableEditing(existing) {
        let marker = existing;

        const place = (latlng) => {
            if (!marker) marker = L.marker(latlng, { draggable: true, icon: this.repere(true) }).addTo(this.map);
            marker.setLatLng(latlng);
            marker.dragging?.enable();
            marker.off('dragend').on('dragend', () => this.writeInputs(marker.getLatLng()));
            this.writeInputs(latlng);
        };

        if (marker) place(marker.getLatLng());
        this.map.on('click', (e) => place(e.latlng));

        [this.latitudeTarget, this.longitudeTarget].forEach((input) =>
            input.addEventListener('change', () => {
                const lat = parseFloat(this.latitudeTarget.value.replace(',', '.'));
                const lng = parseFloat(this.longitudeTarget.value.replace(',', '.'));
                if (Number.isFinite(lat) && Number.isFinite(lng)) {
                    place(L.latLng(lat, lng));
                    this.map.setView([lat, lng], Math.max(this.map.getZoom(), 14));
                }
            }),
        );
    }

    writeInputs(latlng) {
        this.latitudeTarget.value = latlng.lat.toFixed(7);
        this.longitudeTarget.value = latlng.lng.toFixed(7);
    }

    // Construit le contenu de la bulle par le DOM : aucun HTML injecté depuis les données.
    popup(point) {
        const box = document.createElement('div');
        const title = document.createElement('strong');
        title.textContent = point.nom;
        box.append(title);

        const meta = document.createElement('div');
        meta.textContent = [point.commune, point.verifie ? 'Vérifié' : 'Non vérifié'].filter(Boolean).join(' · ');
        meta.style.color = '#6b7280';
        box.append(meta);

        if (point.url) {
            const link = document.createElement('a');
            link.href = point.url;
            link.textContent = 'Voir la fiche';
            box.append(link);
        }
        return box;
    }

    showMessage(text) {
        if (this.hasMessageTarget) {
            this.messageTarget.textContent = text;
            this.messageTarget.hidden = false;
        }
    }
}
