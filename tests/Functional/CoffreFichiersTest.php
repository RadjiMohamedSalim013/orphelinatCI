<?php

namespace App\Tests\Functional;

use App\Service\CoffreFichiers;
use Psr\Log\NullLogger;

/**
 * Simule un redémarrage sur un disque éphémère : le fichier disparaît du disque
 * mais reste servi grâce à sa copie en base.
 */
class CoffreFichiersTest extends AppWebTestCase
{
    public function testFichierRestaureApresPerteDuDisque(): void
    {
        $dossier = static::getContainer()->getParameter('uploads_images_directory');
        @mkdir($dossier, 0777, true);
        $nom = 'test-coffre-'.bin2hex(random_bytes(4)).'-640.webp';
        $chemin = $dossier.'/'.$nom;

        $image = imagecreatetruecolor(4, 4);
        imagewebp($image, $chemin);

        $coffre = new CoffreFichiers($this->em->getConnection(), new NullLogger(), true);
        $coffre->sauver(CoffreFichiers::chemin('image', $nom), $chemin);
        $original = file_get_contents($chemin);
        unlink($chemin);

        $this->client->request('GET', '/uploads/images/'.$nom);

        self::assertResponseIsSuccessful();
        self::assertFileExists($chemin, 'Le fichier est réécrit sur le disque pour les requêtes suivantes.');
        self::assertSame($original, file_get_contents($chemin));
        unlink($chemin);
    }

    public function testFichierInconnuOuCheminInvalide(): void
    {
        $this->client->request('GET', '/uploads/images/inexistant-640.webp');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/uploads/images/..%2F..%2F.env');
        self::assertResponseStatusCodeSame(404);
    }
}
