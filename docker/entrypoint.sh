#!/bin/sh
# Démarrage du conteneur : configuration, migrations, puis Apache.
set -e
cd /var/www/html

# Certificat de l'autorité de la base (Aiven…) fourni en contenu PEM.
if [ -n "${DATABASE_SSL_CA_PEM:-}" ]; then
    printf '%s\n' "$DATABASE_SSL_CA_PEM" > /var/www/html/var/mysql-ca.pem
    export DATABASE_SSL_CA=/var/www/html/var/mysql-ca.pem
fi

mkdir -p var public/uploads/images public/uploads/documents var/uploads/private

# Le cache est construit ici, avec les variables de l'hébergeur (SSL inclus).
rm -rf var/cache/prod
php bin/console cache:warmup --no-interaction

# Migrations (idempotentes) et référentiel géographique (ajoute seulement ce qui manque).
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
php bin/console app:referentiel:cote-divoire --no-interaction

# Compte administrateur (offres sans accès au terminal) : défini par ADMIN_EMAIL
# et ADMIN_PASSWORD, à retirer des variables une fois la connexion vérifiée.
if [ -n "${ADMIN_EMAIL:-}" ] && [ -n "${ADMIN_PASSWORD:-}" ]; then
    php bin/console app:create-admin --no-interaction
fi

chown -R www-data:www-data var public/uploads

exec "$@"
