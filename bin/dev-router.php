<?php

/*
 * Routeur pour le serveur web intégré de PHP (développement local) :
 *   php -S 127.0.0.1:8000 -t public bin/dev-router.php
 *
 * Les fichiers existants de public/ sont servis tels quels ; tout le reste,
 * y compris /assets/* d'AssetMapper en mode dev, passe par Symfony.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = dirname(__DIR__).'/public'.$path;

if ('/' !== $path && is_file($file) && !str_ends_with($file, '.php')) {
    return false;
}

// Le serveur intégré ne recopie pas toujours l'environnement dans $_SERVER (Windows) :
// sans cela, DATABASE_URL=... php -S ... serait ignoré au profit du fichier .env.
foreach (getenv() as $name => $value) {
    $_SERVER[$name] ??= $value;
}

$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__).'/public/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';

require dirname(__DIR__).'/public/index.php';
