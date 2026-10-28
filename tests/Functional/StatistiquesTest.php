<?php

namespace App\Tests\Functional;

class StatistiquesTest extends AppWebTestCase
{
    private const NAVIGATEUR = ['HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148 Safari/604.1'];

    protected function setUp(): void
    {
        parent::setUp();
        // Le client de test s'annonce « Symfony BrowserKit », exclu comme un robot.
        $this->client->setServerParameter('HTTP_USER_AGENT', self::NAVIGATEUR['HTTP_USER_AGENT']);
    }

    private function jour(): array
    {
        return $this->em->getConnection()->fetchAssociative('SELECT * FROM stat_jour WHERE jour = CURRENT_DATE()') ?: [];
    }

    public function testVisitesComptéesSansDoublerLesVisiteurs(): void
    {
        $this->client->request('GET', '/', server: self::NAVIGATEUR + ['HTTP_REFERER' => 'https://www.google.com/search?q=orphelinat']);
        $this->client->request('GET', sprintf('/orphelinats?commune=%d', $this->ref['cocody']->getId()), server: self::NAVIGATEUR);
        $this->fiche($this->ref['saintJoseph']);

        $jour = $this->jour();
        self::assertSame(1, (int) $jour['visiteurs'], 'Un même visiteur n’est compté qu’une fois par jour.');
        self::assertSame(1, (int) $jour['mobile']);
        self::assertGreaterThanOrEqual(2, (int) $jour['vues']);

        $cles = $this->em->getConnection()->fetchFirstColumn('SELECT cle FROM stat_compteur');
        self::assertContains('page:home', $cles);
        self::assertContains('commune:'.$this->ref['cocody']->getId(), $cles);
        self::assertContains('source:google.com', $cles);

        // Aucune adresse IP n'est conservée : seules des empreintes de 64 caractères.
        $empreinte = $this->em->getConnection()->fetchOne('SELECT empreinte FROM stat_visiteur');
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $empreinte);
    }

    public function testRobotsEtRefusDeSuiviIgnores(): void
    {
        $this->client->request('GET', '/', server: ['HTTP_USER_AGENT' => 'Googlebot/2.1 (+http://www.google.com/bot.html)']);
        $this->client->request('GET', '/', server: self::NAVIGATEUR + ['HTTP_DNT' => '1']);

        self::assertSame([], $this->jour());
    }

    public function testPageStatistiquesAdministration(): void
    {
        $this->client->request('GET', '/', server: self::NAVIGATEUR);
        $this->fiche($this->ref['saintJoseph']);

        $this->loginAdmin();
        $this->client->request('GET', '/admin/statistiques?periode=7');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Visiteurs uniques');
        self::assertSelectorTextContains('main', 'Orphelinat Saint-Joseph');

        // Les visites de l'administrateur ne sont pas comptées.
        $avant = (int) ($this->jour()['vues'] ?? 0);
        $this->client->request('GET', '/');
        self::assertSame($avant, (int) ($this->jour()['vues'] ?? 0));
    }
}
