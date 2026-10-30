<?php

use App\Controller\SecurityController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * Adresse de la page de connexion, lue dans ADMIN_LOGIN_PATH.
 *
 * Elle n'apparaît nulle part sur le site public (ni lien, ni robots.txt, ni sitemap) :
 * en production, choisir une valeur difficile à deviner, par exemple /espace-7f3k9q2m.
 * Les routes sont mises en cache : vider le cache après un changement (le conteneur
 * Docker le reconstruit à chaque démarrage).
 */
return static function (RoutingConfigurator $routes): void {
    $chemin = $_SERVER['ADMIN_LOGIN_PATH'] ?? $_ENV['ADMIN_LOGIN_PATH'] ?? '/login';
    if (!preg_match('#^/[a-z0-9][a-z0-9/_-]{2,80}$#', $chemin) || str_starts_with($chemin, '/admin')) {
        throw new \InvalidArgumentException('ADMIN_LOGIN_PATH doit commencer par « / », ne contenir que a-z, 0-9, « - », « _ » ou « / », et ne pas commencer par /admin.');
    }

    $routes->add('app_login', $chemin)->controller([SecurityController::class, 'login']);
};
