<?php

namespace App\Tests\Functional;

use App\Entity\Commune;
use App\Entity\Orphelinat;
use App\Enum\OrphelinatStatut;
use PHPUnit\Framework\Attributes\DataProvider;

class AdministrationTest extends AppWebTestCase
{
    /**
     * @return iterable<array{string}>
     */
    public static function pagesAdmin(): iterable
    {
        foreach (['/admin', '/admin/orphelinats', '/admin/orphelinats/nouveau', '/admin/propositions', '/admin/signalements', '/admin/localisations', '/admin/backups/', '/admin/security/'] as $url) {
            yield $url => [$url];
        }
    }

    #[DataProvider('pagesAdmin')]
    public function testVisiteurRedirigeVersLaConnexion(string $url): void
    {
        $this->client->request('GET', $url);
        self::assertResponseRedirects('/login');
    }

    #[DataProvider('pagesAdmin')]
    public function testUtilisateurSansRoleAdminRefuse(string $url): void
    {
        $this->client->loginUser($this->ref['user']);
        $this->client->request('GET', $url);
        self::assertResponseStatusCodeSame(403);
    }

    #[DataProvider('pagesAdmin')]
    public function testPagesAdminAccessiblesAuxAdministrateurs(string $url): void
    {
        $this->loginAdmin();
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
    }

    public function testActionsSensiblesRefuseesAuxVisiteurs(): void
    {
        $id = $this->ref['espoir']->getId();
        $this->client->request('POST', "/admin/orphelinats/$id/statut", ['statut' => 'archive']);
        self::assertResponseRedirects('/login');
        self::assertSame(OrphelinatStatut::Actif, $this->reload($this->ref['espoir'])->getStatut());
    }

    public function testConnexionParFormulaire(): void
    {
        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Se connecter')->form(['email' => 'admin@test.local', 'password' => 'mauvais']));
        $this->client->followRedirect();
        self::assertSelectorExists('[role="alert"]');

        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Se connecter')->form(['email' => 'admin@test.local', 'password' => 'motdepasse-admin']));
        self::assertResponseRedirects('/admin');
    }

    public function testTableauDeBordCalculeLesIndicateurs(): void
    {
        $this->loginAdmin();
        $crawler = $this->client->request('GET', '/admin');
        $texte = $crawler->filter('main')->text();

        // 3 actifs (archivé et fermé exclus), 1 vérifié
        self::assertMatchesRegularExpression('/Établissements dans l’annuaire\s*3/u', $texte);
        self::assertMatchesRegularExpression('/Fiches vérifiées\s*1/u', $texte);
        self::assertStringContainsString('Cocody', $texte);
    }

    public function testCreationEtModificationDUnEtablissement(): void
    {
        $this->loginAdmin();
        $crawler = $this->client->request('GET', '/admin/orphelinats/nouveau');
        $this->client->submit($crawler->selectButton('Enregistrer')->form([
            'orphelinat[nom]' => 'Village d’enfants Bingerville',
            'orphelinat[commune]' => (string) $this->ref['cocody']->getId(),
            'orphelinat[nouveauQuartier]' => 'Angré',
            'orphelinat[adresse]' => 'Route d’Angré',
            'orphelinat[latitude]' => '5.3911',
            'orphelinat[longitude]' => '-3.9812',
            'orphelinat[siteWeb]' => 'exemple.org',
            'orphelinat[verification]' => 'verifie',
            'orphelinat[statut]' => 'actif',
        ]));
        self::assertResponseRedirects();

        $orphelinat = $this->em->getRepository(Orphelinat::class)->findOneBy(['nom' => 'Village d’enfants Bingerville']);
        self::assertSame('Angré', $orphelinat->getQuartier()->getNom());
        self::assertSame('https://exemple.org', $orphelinat->getSiteWeb());
        self::assertNotNull($orphelinat->getVerifiedAt());
        self::assertTrue($orphelinat->hasCoordinates());

        // La fiche publique affiche la carte
        $this->fiche($orphelinat);
        self::assertSelectorExists('[data-controller="map"]');
    }

    public function testValidationDuFormulaireAdmin(): void
    {
        $this->loginAdmin();
        $crawler = $this->client->request('GET', '/admin/orphelinats/nouveau');
        $this->client->submit($crawler->selectButton('Enregistrer')->form([
            'orphelinat[nom]' => '',
            'orphelinat[adresse]' => '',
            'orphelinat[latitude]' => '120',
            'orphelinat[email]' => 'invalide',
        ]));

        self::assertResponseStatusCodeSame(422);
        $texte = $this->client->getCrawler()->filter('main')->text();
        self::assertStringContainsString('Le nom est requis', $texte);
        self::assertStringContainsString('La commune est requise', $texte);
        self::assertStringContainsString('latitude doit être comprise', $texte);
        self::assertStringContainsString('à la fois la latitude et la longitude', $texte);
    }

    public function testArchivagePuisSuppressionDefinitive(): void
    {
        $this->loginAdmin();
        $id = $this->ref['espoir']->getId();

        // Suppression refusée tant que la fiche n'est pas archivée
        $crawler = $this->client->request('GET', "/admin/orphelinats/$id");
        self::assertCount(0, $crawler->selectButton('Supprimer définitivement'));

        $this->client->submit($crawler->selectButton('Archiver')->form());
        self::assertSame(OrphelinatStatut::Archive, $this->reload($this->ref['espoir'])->getStatut());

        $this->client->request('GET', "/orphelinats/$id");
        self::assertResponseStatusCodeSame(404);

        $crawler = $this->client->request('GET', "/admin/orphelinats/$id");
        $this->client->submit($crawler->selectButton('Supprimer définitivement')->form());
        $this->em->clear();
        self::assertNull($this->em->getRepository(Orphelinat::class)->find($id));
    }

    public function testReferentielGeographique(): void
    {
        $this->loginAdmin();
        $crawler = $this->client->request('GET', '/admin/localisations');
        $form = $crawler->filter(sprintf('form[action="/admin/localisations/ajouter/commune"] input[name="parent"][value="%d"]', $this->ref['abidjan']->getId()))->closest('form')->form();
        $this->client->submit($form, ['nom' => 'Marcory']);
        self::assertNotNull($this->em->getRepository(Commune::class)->findOneBy(['nom' => 'Marcory']));

        // Doublon refusé
        $crawler = $this->client->request('GET', '/admin/localisations');
        $form = $crawler->filter(sprintf('form[action="/admin/localisations/ajouter/commune"] input[name="parent"][value="%d"]', $this->ref['abidjan']->getId()))->closest('form')->form();
        $this->client->submit($form, ['nom' => 'Cocody']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'existe déjà');

        // Une commune utilisée ne peut pas être supprimée
        $cocody = $this->ref['cocody']->getId();
        $crawler = $this->client->request('GET', '/admin/localisations');
        $this->client->submit($crawler->filter("form[action=\"/admin/localisations/commune/$cocody/supprimer\"]")->form());
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'encore utilisé');
        self::assertNotNull($this->em->getRepository(Commune::class)->find($cocody));
    }
}
