<?php

namespace App\Tests\Functional;

use App\Entity\Commune;
use App\Entity\Orphelinat;
use App\Entity\Pays;
use App\Entity\User;
use App\Entity\Ville;
use App\Enum\OrphelinatStatut;
use App\Enum\VerificationStatut;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Base des tests fonctionnels : base orphamap_test remise à zéro avant chaque
 * test, avec un petit jeu de données (2 pays, 3 villes, 4 communes, 4 fiches).
 */
abstract class AppWebTestCase extends WebTestCase
{
    private static bool $schemaReady = false;

    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    /** @var array<string, object> */
    protected array $ref = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        if (!self::$schemaReady) {
            $tool = new SchemaTool($this->em);
            $metadata = $this->em->getMetadataFactory()->getAllMetadata();
            $tool->dropSchema($metadata);
            $tool->createSchema($metadata);
            self::$schemaReady = true;
        }

        $connection = $this->em->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($connection->createSchemaManager()->listTableNames() as $table) {
            $connection->executeStatement(sprintf('TRUNCATE TABLE `%s`', $table));
        }
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        $this->seed();
    }

    private function seed(): void
    {
        $ci = (new Pays())->setNom('Côte d’Ivoire');
        $gh = (new Pays())->setNom('Ghana');
        $abidjan = (new Ville())->setNom('Abidjan')->setPays($ci);
        $bouake = (new Ville())->setNom('Bouaké')->setPays($ci);
        $accra = (new Ville())->setNom('Accra')->setPays($gh);
        $cocody = (new Commune())->setNom('Cocody')->setVille($abidjan);
        $yopougon = (new Commune())->setNom('Yopougon')->setVille($abidjan);
        $bouakeCommune = (new Commune())->setNom('Bouaké')->setVille($bouake);
        $accraCommune = (new Commune())->setNom('Accra Central')->setVille($accra);

        $this->ref = compact('ci', 'gh', 'abidjan', 'bouake', 'accra', 'cocody', 'yopougon', 'bouakeCommune', 'accraCommune');
        foreach ($this->ref as $entity) {
            $this->em->persist($entity);
        }

        $this->ref['saintJoseph'] = $this->orphelinat('Orphelinat Saint-Joseph', $cocody, '+225 07 11 22 33 44', VerificationStatut::Verifie);
        $this->ref['espoir'] = $this->orphelinat('Foyer Espoir', $yopougon);
        $this->ref['lumiere'] = $this->orphelinat('Centre Lumière', $bouakeCommune);
        $this->ref['ancien'] = $this->orphelinat('Orphelinat Archivé', $cocody)->setStatut(OrphelinatStatut::Archive);
        $this->ref['ferme'] = $this->orphelinat('Maison Fermée', $yopougon)->setStatut(OrphelinatStatut::Ferme);

        $admin = (new User())->setEmail('admin@test.local')->setRoles(['ROLE_ADMIN']);
        $admin->setPassword(static::getContainer()->get('security.user_password_hasher')->hashPassword($admin, 'motdepasse-admin'));
        $simple = (new User())->setEmail('user@test.local')->setRoles([]);
        $simple->setPassword('x');
        $this->em->persist($admin);
        $this->em->persist($simple);
        $this->ref['admin'] = $admin;
        $this->ref['user'] = $simple;

        $this->em->flush();
        // Les requêtes suivantes doivent relire la base, comme en conditions réelles.
        $this->em->clear();
    }

    private function orphelinat(string $nom, Commune $commune, ?string $telephone = null, VerificationStatut $verification = VerificationStatut::NonVerifie): Orphelinat
    {
        $o = (new Orphelinat())
            ->setNom($nom)
            ->setCommune($commune)
            ->setAdresse('Adresse de '.$nom)
            ->setTelephone($telephone)
            ->setVerification($verification);
        $this->em->persist($o);

        return $o;
    }

    protected function loginAdmin(): void
    {
        $this->client->loginUser($this->ref['admin']);
    }

    /** Valeurs anti-spam valides (formulaire rempli il y a plus de 3 secondes). */
    protected function antiSpam(string $form): array
    {
        return [$form.'[rendu_le]' => (string) (time() - 30), $form.'[site_internet]' => ''];
    }

    /** Ouvre une fiche publique par son adresse courte, en suivant la redirection vers l'adresse canonique. */
    protected function fiche(object $orphelinat): Crawler
    {
        $crawler = $this->client->request('GET', '/orphelinats/'.$orphelinat->getId());

        return $this->client->getResponse()->isRedirect() ? $this->client->followRedirect() : $crawler;
    }

    protected function reload(object $entity): object
    {
        $this->em->clear();

        return $this->em->find($entity::class, $entity->getId());
    }
}
