<?php

namespace App\Tests\Functional;

class EnTetesSecuriteTest extends AppWebTestCase
{
    public function testPolitiqueDeContenuAutoriseSeulementLesScriptsDuSite(): void
    {
        $crawler = $this->client->request('GET', '/');
        $reponse = $this->client->getResponse();

        // En debug (tests, dev) la politique est en « Report-Only », appliquée en production.
        $csp = $reponse->headers->get('Content-Security-Policy') ?? $reponse->headers->get('Content-Security-Policy-Report-Only');
        self::assertNotNull($csp);
        self::assertMatchesRegularExpression("#script-src 'self' 'nonce-([A-Za-z0-9+/=]+)'#", $csp);
        self::assertStringContainsString("frame-ancestors 'none'", $csp);
        self::assertStringContainsString("object-src 'none'", $csp);
        self::assertStringContainsString('https://tile.openstreetmap.org', $csp);
        preg_match("#'nonce-([^']+)'#", $csp, $m);

        // Chaque script exécutable de la page porte le nonce de la requête.
        $scripts = $crawler->filter('script:not([type="application/ld+json"])');
        self::assertGreaterThan(0, $scripts->count());
        foreach ($scripts as $script) {
            self::assertSame($m[1], $script->getAttribute('nonce'), 'Script sans nonce : '.substr($script->textContent, 0, 60));
        }

        // Aucun script tiers (polyfill d'importmap, CDN…).
        self::assertStringNotContainsString('jspm.io', $reponse->getContent());
        // Une feuille de style importée en JavaScript devient un module « data: » que la
        // CSP bloque, ce qui casse tous les scripts : les styles passent par <link>.
        self::assertStringNotContainsString('"data:application/javascript,"', $reponse->getContent());
        self::assertSelectorExists('link[rel="stylesheet"][href*="/assets/styles/app"]');
        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
    }

    public function testLeNonceChangeAChaqueRequete(): void
    {
        $this->client->request('GET', '/');
        $premier = $this->client->getResponse()->headers->get('Content-Security-Policy-Report-Only');
        $this->client->request('GET', '/');
        self::assertNotSame($premier, $this->client->getResponse()->headers->get('Content-Security-Policy-Report-Only'));
    }
}
