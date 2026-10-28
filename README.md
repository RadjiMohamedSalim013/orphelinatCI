# OrphaMap CI

Annuaire des orphelinats : recherche par pays, ville et commune, fiches détaillées, propositions et signalements du public, administration avec vérification.

Symfony 7.4 · Doctrine ORM 3 / MySQL 8 · Twig · Tailwind CSS 3.4 (compilé, sans Node.js) · AssetMapper + Stimulus · Leaflet / OpenStreetMap.

## Installation locale

```bash
composer install
# Adapter DATABASE_URL dans .env.local si nécessaire
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate
php bin/console app:referentiel:cote-divoire      # villes et communes de Côte d'Ivoire (idempotent, --dry-run disponible)
php bin/console app:create-admin                  # compte administrateur (e-mail et mot de passe demandés, ou variables ADMIN_EMAIL / ADMIN_PASSWORD)
```

CSS : `php bin/console tailwind:build --watch` dans un second terminal (le binaire Tailwind est téléchargé automatiquement).

Serveur de développement (le routeur est nécessaire pour servir `/assets` avec le serveur intégré de PHP) :

```bash
php -S 127.0.0.1:8000 -t public bin/dev-router.php
```

Sous WAMP/Apache, `public/.htaccess` assure la réécriture vers `index.php`.

## Variables d'environnement

| Variable | Rôle | Défaut |
|---|---|---|
| `DATABASE_URL` | Connexion MySQL | `.env` |
| `APP_SECRET` | Secret Symfony (à définir en production) | — |
| `MAILER_DSN` | Envoi des notifications aux proposants | `null://null` (aucun envoi) |
| `MAILER_FROM` | Expéditeur des notifications | `no-reply@orphamap.ci` |
| `MAP_TILE_URL` | Fournisseur de tuiles de carte | tuiles OpenStreetMap |
| `MAP_ATTRIBUTION` | Mention obligatoire du fournisseur | © OpenStreetMap |

Les tuiles OpenStreetMap publiques conviennent à un trafic modéré ([politique d'usage](https://operations.osmfoundation.org/policies/tiles/)). Au-delà, configurer un fournisseur dédié via `MAP_TILE_URL`. Aucun géocodage automatique n'est effectué : les coordonnées sont saisies par l'administration.

## Production

```bash
APP_ENV=prod composer install --no-dev --optimize-autoloader
APP_ENV=prod php bin/console tailwind:build --minify
APP_ENV=prod php bin/console asset-map:compile
php bin/console doctrine:migrations:migrate --no-interaction
```

`render.yaml` enchaîne ces étapes. Les justificatifs envoyés par le public sont stockés dans `var/uploads/private/` (jamais servis directement) : ce dossier doit être persistant et sauvegardé, comme `public/uploads/`.

## Tests

```bash
php bin/console doctrine:database:create --env=test --if-not-exists
php bin/phpunit
```

Les tests fonctionnels utilisent la base `orphamap_test` (recréée à chaque exécution) et couvrent la recherche, les fiches, les propositions, les signalements, l'administration et le contrôle d'accès.

## Fonctionnement

- **Propositions** : jamais publiées automatiquement. Statuts : en attente → en vérification → acceptée / refusée (motif obligatoire). L'acceptation crée la fiche une seule fois (verrou en base) ; les doublons probables (nom proche, même téléphone) sont signalés. Le proposant suit sa demande via un lien privé et reçoit un e-mail s'il en a laissé un.
- **Signalements** : enregistrés pour l'administration, sans modification directe de la fiche.
- **Fiches** : actives (listées), fermées (consultables, hors recherche) ou archivées (masquées). La suppression définitive n'est possible qu'après archivage.
- **Anti-spam** : jeton CSRF, champ piège, délai minimal de saisie, 5 envois par heure et par IP.

## Interface

Système de design dans `assets/styles/app.css` : tokens CSS (couleurs `brand`, `trust`, `ink`, rayons, ombres) exposés à Tailwind par `tailwind.config.js`, et composants `om-*` (boutons, champs, cartes, badges, alertes, tableaux, squelettes, fenêtre de confirmation). Contrôleurs Stimulus dans `assets/controllers/` : listes en cascade, carte, confirmation (`data-confirm` sur un formulaire), notifications, menus, bascule liste/carte.
