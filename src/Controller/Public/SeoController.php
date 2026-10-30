<?php

namespace App\Controller\Public;

use App\Enum\OrphelinatStatut;
use App\Repository\OrphelinatRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Fichiers destinés aux moteurs de recherche.
 */
class SeoController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'seo_sitemap', methods: ['GET'], format: 'xml')]
    #[Cache(maxage: 3600, public: true)]
    public function sitemap(OrphelinatRepository $repository): Response
    {
        $urls = [
            ['loc' => $this->generateUrl('home', [], UrlGeneratorInterface::ABSOLUTE_URL), 'priority' => '1.0', 'changefreq' => 'daily'],
            ['loc' => $this->generateUrl('orphelinat_index', [], UrlGeneratorInterface::ABSOLUTE_URL), 'priority' => '0.9', 'changefreq' => 'daily'],
            ['loc' => $this->generateUrl('proposer_orphelinat', [], UrlGeneratorInterface::ABSOLUTE_URL), 'priority' => '0.5', 'changefreq' => 'monthly'],
        ];

        // Une page par commune ayant au moins un établissement actif.
        foreach ($repository->countActiveByCommune(500) as $c) {
            $urls[] = [
                'loc' => $this->generateUrl('orphelinat_index', ['pays' => $c['paysId'], 'ville' => $c['villeId'], 'commune' => $c['communeId']], UrlGeneratorInterface::ABSOLUTE_URL),
                'priority' => '0.8',
                'changefreq' => 'weekly',
            ];
        }

        foreach ($repository->findBy(['statut' => [OrphelinatStatut::Actif, OrphelinatStatut::Ferme]]) as $o) {
            $urls[] = [
                'loc' => $this->generateUrl('orphelinat_show', ['id' => $o->getId(), 'slug' => $o->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'lastmod' => ($o->getUpdatedAt() ?? $o->getCreatedAt())->format('Y-m-d'),
                'priority' => OrphelinatStatut::Actif === $o->getStatut() ? '0.7' : '0.3',
                'changefreq' => 'monthly',
            ];
        }

        $response = $this->render('seo/sitemap.xml.twig', ['urls' => $urls]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response;
    }

    #[Route('/robots.txt', name: 'seo_robots', methods: ['GET'], format: 'txt')]
    #[Cache(maxage: 86400, public: true)]
    public function robots(): Response
    {
        $lignes = [
            // L'administration et la page de connexion n'y figurent volontairement pas :
            // robots.txt est public et servirait de plan aux attaquants. Elles sont
            // exclues de l'indexation par l'en-tête X-Robots-Tag (SecurityHeadersSubscriber).
            'User-agent: *',
            'Disallow: /api/',
            'Disallow: /proposition/suivi/',
            'Disallow: /*/signaler',
            'Disallow: /*?*q=',
            '',
            'Sitemap: '.$this->generateUrl('seo_sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ];

        return new Response(implode("\n", $lignes)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
