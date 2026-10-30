<?php

namespace App\Tests\Functional;

use App\Entity\Commune;
use App\Entity\Orphelinat;
use App\Entity\SecurityAudit;
use App\Enum\OrphelinatStatut;
use PHPUnit\Framework\Attributes\DataProvider;

class AdministrationTest extends AppWebTestCase
{
    private const CONNEXION = '/espace-equipe-test';

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
    public function testAdministrationInvisibleAuxVisiteurs(string $url): void
    {
        // Même réponse qu'une adresse inexistante : ni redirection, ni cookie de session,
        // ni en-tête particulier qui trahirait l'existence de l'administration.
        $this->client->request('GET', '/adresse-inexistante-'.md5($url));
        $reference = $this->client->getResponse();

        $this->client->request('GET', $url);
        $reponse = $this->client->getResponse();
        self::assertResponseStatusCodeSame(404);
        self::assertSame([], $reponse->headers->getCookies());
        foreach (['Cache-Control', 'X-Robots-Tag', 'Location'] as $entete) {
            self::assertSame($reference->headers->get($entete), $reponse->headers->get($entete), $entete);
        }
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
        self::assertResponseStatusCodeSame(404);
        self::assertSame(OrphelinatStatut::Actif, $this->reload($this->ref['espoir'])->getStatut());
    }

    public function testConnexionParFormulaire(): void
    {
        $crawler = $this->client->request('GET', self::CONNEXION);
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        $this->client->submit($crawler->selectButton('Se connecter')->form(['email' => 'admin@test.local', 'password' => 'mauvais']));
        $this->client->followRedirect();
        self::assertSelectorExists('[role="alert"]');

        $crawler = $this->client->request('GET', self::CONNEXION);
        $this->client->submit($crawler->selectButton('Se connecter')->form(['email' => 'admin@test.local', 'password' => 'motdepasse-admin']));
        self::assertResponseRedirects('/admin');
    }

    public function testPageDeConnexionAUneAdresseConfigurable(): void
    {
        // ADMIN_LOGIN_PATH vaut /espace-equipe-test dans .env.test : /login n'existe plus.
        $this->client->request('GET', '/login');
        self::assertResponseStatusCodeSame(404);
    }

    public function testDeconnexionExigeUnJeton(): void
    {
        $this->loginAdmin();

        // Simple lien (ou requête forgée par un autre site) : refusé, la session reste ouverte.
        $this->client->request('POST', '/logout');
        $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();

        // Bouton « Quitter » du menu : formulaire avec jeton, session fermée.
        $crawler = $this->client->request('GET', '/admin');
        $this->client->submit($crawler->selectButton('Quitter')->form());
        self::assertResponseRedirects('/');
        $this->client->request('GET', '/admin');
        self::assertResponseStatusCodeSame(404);
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
            'orphelinat[telephone]' => '+225 07 08 09 10 11',
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
        self::assertStringContainsString('Le numéro de téléphone de l’établissement est requis', str_replace("'", '’', $texte));
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

    public function testResolutionDUnPointDAuditParFormulaireProtege(): void
    {
        $audit = (new SecurityAudit())->setType('upload')->setSeverity('critical')->setMessage('Fichier suspect dans les dépôts');
        $this->em->persist($audit);
        $this->em->flush();
        $this->loginAdmin();

        // Sans jeton CSRF : refusé, le point reste ouvert.
        $this->client->request('POST', '/admin/security/issue/'.$audit->getId().'/resolve');
        self::assertResponseRedirects('/admin/security/');
        self::assertFalse($this->reload($audit)->isResolved());

        // Depuis la page : la gravité s'affiche et le formulaire marque le point comme résolu.
        $crawler = $this->client->request('GET', '/admin/security/');
        self::assertSelectorTextContains('#titre-a-traiter + ul', 'Critique');
        $this->client->submit($crawler->selectButton('Résolu')->form());
        self::assertResponseRedirects('/admin/security/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Problème marqué comme résolu');
        self::assertTrue($this->reload($audit)->isResolved());
    }

    public function testActionsDeSauvegardeExigentUnJetonCsrf(): void
    {
        $this->loginAdmin();
        foreach (['/admin/backups/create', '/admin/backups/clean'] as $url) {
            $this->client->request('POST', $url);
            self::assertResponseRedirects('/admin/backups/');
            $this->client->followRedirect();
            self::assertSelectorTextContains('body', 'Le formulaire a expiré');
        }
    }
}
