<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/*
 * Connexion chiffrée à MySQL lorsque l'hébergeur de la base l'impose (Aiven…).
 * Activée seulement si DATABASE_SSL_CA (chemin du certificat de l'autorité)
 * est défini au moment de la construction du cache, ce que fait
 * docker/entrypoint.sh au démarrage du conteneur.
 */
return static function (ContainerConfigurator $container): void {
    $ca = $_SERVER['DATABASE_SSL_CA'] ?? $_ENV['DATABASE_SSL_CA'] ?? getenv('DATABASE_SSL_CA');
    if (!is_string($ca) || '' === $ca) {
        return;
    }

    $container->extension('doctrine', [
        'dbal' => [
            'options' => [
                PDO::MYSQL_ATTR_SSL_CA => '%env(DATABASE_SSL_CA)%',
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
            ],
        ],
    ]);
};
