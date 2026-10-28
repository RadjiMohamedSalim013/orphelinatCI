#!/usr/bin/env bash
#
# Construit un paquet de production prêt à téléverser :
#   bin/build-release.sh            -> var/release/orphamap-AAAAMMJJ-HHMM.tar.gz
#
# Contenu : code, dépendances de production, CSS Tailwind minifié et assets
# compilés. Exclus : tests, outils de développement, fichiers envoyés
# (public/uploads, var/uploads) et toute configuration locale (.env.local).
set -euo pipefail

racine="$(cd "$(dirname "$0")/.." && pwd)"
cd "$racine"

horodatage="$(date +%Y%m%d-%H%M)"
build="var/release/build"
archive="var/release/orphamap-$horodatage.tar.gz"

echo "› Préparation de $build"
rm -rf "$build"
mkdir -p "$build"

tar -cf - \
    --exclude='./.git' --exclude='./vendor' --exclude='./var' --exclude='./tests' \
    --exclude='./public/uploads' --exclude='./public/assets' --exclude='./public/_apercu' \
    --exclude='./.env.local' --exclude='./.env.*.local' --exclude='./.phpunit.cache' \
    --exclude='./phpunit.dist.xml' --exclude='./docs' --exclude='./TODO.md' --exclude='./SPEC_ORPHAMAP.txt' \
    --exclude='./compose*.yaml' --exclude='./node_modules' --exclude='./.env.test' \
    . | tar -xf - -C "$build"

echo "› CSS de production (Tailwind, minifié)"
php bin/console tailwind:build --minify --env=prod --no-debug >/dev/null
mkdir -p "$build/var/tailwind"
cp var/tailwind/app.built.css "$build/var/tailwind/app.built.css"

cd "$build"

echo "› Dépendances de production"
APP_ENV=prod composer install --no-dev --optimize-autoloader --classmap-authoritative --no-scripts --no-interaction --quiet

echo "› Compilation des assets"
APP_ENV=prod APP_DEBUG=0 php bin/console importmap:install --quiet
APP_ENV=prod APP_DEBUG=0 php bin/console asset-map:compile --quiet

# Le CSS est désormais dans public/assets : inutile de livrer var/.
rm -rf var
mkdir -p var public/uploads/images public/uploads/documents
cp "$racine/public/uploads/.htaccess" public/uploads/.htaccess

# Valeurs de production par défaut ; les secrets vont dans .env.local sur le serveur.
sed -i 's/^APP_ENV=dev/APP_ENV=prod/' .env
rm -f .env.dev

cd "$racine"
tar -czf "$archive" -C "$build" .
rm -rf "$build"

echo "✔ Paquet prêt : $archive ($(du -h "$archive" | cut -f1))"
