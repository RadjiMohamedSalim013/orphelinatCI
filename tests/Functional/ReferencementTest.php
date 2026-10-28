<?php

namespace App\Tests\Functional;

class ReferencementTest extends AppWebTestCase
{
    public function testSitemapListeLesPagesUtilesUniquement(): void
    {
        $this->client->request('GET', '/sitemap.xml');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/xml; charset=UTF-8');

        $tout = $this->client->getResponse()->getContent();
        self::assertNotFalse(simplexml_load_string($tout), 'Le sitemap doit être un XML valide.');

        self::assertStringContainsString('/orphelinats/'.$this->ref['saintJoseph']->getId().'-orphelinat-saint-joseph', $tout);
        self::assertStringContainsString('commune='.$this->ref['cocody']->getId(), $tout);
        self::assertStringNotContainsString('orphelinat-archive', $tout, 'Les fiches archivées ne sont pas référencées.');
    }

    public function testRobotsProtegeLesPagesPrivees(): void
    {
        $this->client->request('GET', '/robots.txt');
        $contenu = $this->client->getResponse()->getContent();

        self::assertStringContainsString('Disallow: /admin', $contenu);
        self::assertStringContainsString('Disallow: /proposition/suivi/', $contenu);
        self::assertMatchesRegularExpression('#Sitemap: https?://.+/sitemap\.xml#', $contenu);
    }

    public function testPageCommuneOptimisee(): void
    {
        $crawler = $this->client->request('GET', sprintf('/orphelinats?pays=%d&ville=%d&commune=%d', $this->ref['ci']->getId(), $this->ref['abidjan']->getId(), $this->ref['cocody']->getId()));

        self::assertSelectorTextContains('title', 'Orphelinats à Cocody, Abidjan');
        self::assertSelectorTextContains('h1', 'Orphelinats à Cocody, Abidjan');
        self::assertSelectorNotExists('meta[name="robots"]');
        self::assertStringContainsString('commune=', $crawler->filter('link[rel="canonical"]')->attr('href'));
    }

    public function testRechercheTextuelleNonIndexee(): void
    {
        $this->client->request('GET', '/orphelinats?q=espoir');
        self::assertSelectorExists('meta[name="robots"][content^="noindex"]');
    }

    public function testBalisesDePartageEtCachePublic(): void
    {
        $crawler = $this->client->request('GET', '/');

        self::assertSelectorExists('meta[property="og:image"]');
        self::assertStringContainsString('SearchAction', $crawler->filter('script[type="application/ld+json"]')->text());
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('public'));
    }
}
