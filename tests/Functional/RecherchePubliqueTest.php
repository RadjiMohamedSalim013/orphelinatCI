<?php

namespace App\Tests\Functional;

class RecherchePubliqueTest extends AppWebTestCase
{
    private function resultats(string $query = ''): array
    {
        $crawler = $this->client->request('GET', '/orphelinats'.($query ? '?'.$query : ''));
        self::assertResponseIsSuccessful();

        return $crawler->filter('article h3 a')->each(static fn ($a) => trim($a->text()));
    }

    public function testAccueilAfficheRechercheEtEtablissementsRecents(): void
    {
        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('form[role="search"] select[name="pays"]'));
        self::assertSelectorTextContains('body', 'Orphelinat Saint-Joseph');
        // Statistiques réelles : 3 actifs, 1 vérifié, archivé et fermé exclus
        self::assertSelectorExists('[data-compteur-cible-value="3"]');
        self::assertSelectorExists('[data-compteur-cible-value="1"]');
        self::assertStringNotContainsString('Orphelinat Archivé', $crawler->filter('main')->text());
    }

    public function testRechercheParPaysPuisVillePuisCommune(): void
    {
        $ci = $this->ref['ci']->getId();
        $abidjan = $this->ref['abidjan']->getId();

        $parPays = $this->resultats('pays='.$ci);
        sort($parPays);
        self::assertSame(['Centre Lumière', 'Foyer Espoir', 'Orphelinat Saint-Joseph'], $parPays);

        $parVille = $this->resultats(sprintf('pays=%d&ville=%d', $ci, $abidjan));
        sort($parVille);
        self::assertSame(['Foyer Espoir', 'Orphelinat Saint-Joseph'], $parVille);

        self::assertSame(['Orphelinat Saint-Joseph'], $this->resultats(sprintf('pays=%d&ville=%d&commune=%d', $ci, $abidjan, $this->ref['cocody']->getId())));
    }

    public function testLaVilleChoisieResteSelectionneeEtLesListesSontFiltrees(): void
    {
        $crawler = $this->client->request('GET', sprintf('/orphelinats?pays=%d&ville=%d', $this->ref['ci']->getId(), $this->ref['abidjan']->getId()));

        $villes = $crawler->filter('select[name="ville"] option')->each(static fn ($o) => $o->text());
        self::assertContains('Abidjan', $villes);
        self::assertNotContains('Accra', $villes);
        self::assertSame('Abidjan', $crawler->filter('select[name="ville"] option[selected]')->text());

        $communes = $crawler->filter('select[name="commune"] option')->each(static fn ($o) => $o->text());
        self::assertContains('Cocody', $communes);
        self::assertNotContains('Bouaké', $communes);
    }

    public function testRechercheParNomSeule(): void
    {
        self::assertSame(['Foyer Espoir'], $this->resultats('q=espoir'));
    }

    public function testRechercheSansResultatAfficheUnMessageUtile(): void
    {
        self::assertSame([], $this->resultats('pays='.$this->ref['gh']->getId()));
        self::assertSelectorTextContains('body', 'Aucun établissement pour ces critères');
        self::assertSelectorExists('a[href="/orphelinats"]');
    }

    public function testParametresInvalidesIgnores(): void
    {
        self::assertCount(3, $this->resultats('pays=abc&ville=-3&commune[]=1&page=-7'));
        // Recherche très longue tronquée sans erreur
        self::assertSame([], $this->resultats('q='.str_repeat('x', 500)));
    }

    public function testFicheDetailleeEtProtectionDesFichesArchivees(): void
    {
        $id = $this->ref['saintJoseph']->getId();
        $this->client->request('GET', '/orphelinats/'.$id);
        self::assertResponseRedirects('/orphelinats/'.$id.'-orphelinat-saint-joseph', 301);
        // Un ancien slug (après renommage) est aussi redirigé vers l'adresse canonique.
        $this->client->request('GET', '/orphelinats/'.$id.'-ancien-nom');
        self::assertResponseRedirects('/orphelinats/'.$id.'-orphelinat-saint-joseph', 301);

        $this->fiche($this->ref['saintJoseph']);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Orphelinat Saint-Joseph');
        self::assertSelectorTextContains('body', 'Vérifié');
        self::assertSelectorExists('a[href^="tel:"]');
        self::assertSelectorExists('link[rel="canonical"][href$="/orphelinats/'.$id.'-orphelinat-saint-joseph"]');
        self::assertSelectorExists('script[type="application/ld+json"]');

        $this->fiche($this->ref['espoir']);
        self::assertSelectorTextContains('body', 'Non vérifié');

        $this->fiche($this->ref['ferme']);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'fermé');

        $this->client->request('GET', '/orphelinats/'.$this->ref['ancien']->getId());
        self::assertResponseStatusCodeSame(404);
    }

    public function testApiDesListesEnCascade(): void
    {
        $this->client->request('GET', '/api/localisations/villes?pays='.$this->ref['ci']->getId());
        self::assertResponseIsSuccessful();
        $villes = array_column(json_decode($this->client->getResponse()->getContent(), true), 'nom');
        self::assertSame(['Abidjan', 'Bouaké'], $villes);

        $this->client->request('GET', '/api/localisations/communes?ville=abc');
        self::assertSame('[]', $this->client->getResponse()->getContent());
    }
}
