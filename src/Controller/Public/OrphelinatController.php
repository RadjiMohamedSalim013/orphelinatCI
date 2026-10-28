<?php

namespace App\Controller\Public;

use App\Model\RechercheCriteres;
use App\Repository\CommuneRepository;
use App\Repository\OrphelinatRepository;
use App\Repository\PaysRepository;
use App\Repository\VilleRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Attribute\Route;

class OrphelinatController extends AbstractController
{
    public function __construct(
        private readonly OrphelinatRepository $orphelinatRepository,
    ) {
    }

    #[Route('/orphelinats', name: 'orphelinat_index', methods: ['GET'])]
    #[Cache(maxage: 120, public: true, mustRevalidate: true)]
    public function index(
        Request $request,
        PaysRepository $paysRepository,
        VilleRepository $villeRepository,
        CommuneRepository $communeRepository,
        LoggerInterface $logger,
    ): Response {
        $criteres = RechercheCriteres::fromRequest($request);
        $resultats = null;
        $points = [];
        $erreur = false;

        try {
            $resultats = $this->orphelinatRepository->searchPublic($criteres);
            $points = $this->orphelinatRepository->findMapPoints($criteres);
        } catch (\Throwable $e) {
            $logger->error('Échec de la recherche dans l\'annuaire.', ['exception' => $e]);
            $erreur = true;
        }

        $pays = $paysRepository->findAllOrdered();
        // Listes pré-filtrées côté serveur : la recherche fonctionne aussi sans JavaScript.
        $villes = $villeRepository->findByPaysId($criteres->paysId);
        $communes = $communeRepository->findByVilleId($criteres->villeId);

        $response = $this->render('public/orphelinat/index.html.twig', [
            'criteres' => $criteres,
            'resultats' => $resultats,
            'points' => $points,
            'erreur' => $erreur,
            'pays' => $pays,
            'villes' => $villes,
            'communes' => $communes,
            'lieu' => $this->libelleLieu($criteres, $pays, $villes, $communes),
        ]);

        return $erreur ? $response->setStatusCode(Response::HTTP_SERVICE_UNAVAILABLE) : $response;
    }

    /**
     * « Cocody, Abidjan », « Abidjan » ou « Côte d’Ivoire » selon les filtres, pour le titre de page.
     *
     * Les listes reçues sont celles déjà chargées pour les menus déroulants (aucune requête de plus).
     */
    private function libelleLieu(RechercheCriteres $criteres, array $pays, array $villes, array $communes): ?string
    {
        $nom = static function (array $liste, ?int $id): ?string {
            foreach ($liste as $element) {
                if ($element->getId() === $id) {
                    return $element->getNom();
                }
            }

            return null;
        };

        $commune = $nom($communes, $criteres->communeId);
        $ville = $nom($villes, $criteres->villeId);

        return match (true) {
            null !== $commune => $commune.($ville && $ville !== $commune ? ', '.$ville : ''),
            null !== $ville => $ville,
            default => $nom($pays, $criteres->paysId),
        };
    }

    /**
     * Adresse canonique : /orphelinats/12-nom-de-l-etablissement.
     * Les anciennes adresses (/orphelinats/12) et les slugs périmés après un
     * renommage sont redirigés en 301 pour conserver le référencement.
     */
    #[Route('/orphelinats/{id}-{slug}', name: 'orphelinat_show', methods: ['GET'], requirements: ['id' => '\d+', 'slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'])]
    #[Route('/orphelinats/{id}', name: 'orphelinat_show_court', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[Cache(maxage: 300, public: true, mustRevalidate: true)]
    public function show(int $id, ?string $slug = null): Response
    {
        $orphelinat = $this->orphelinatRepository->findPublicById($id)
            ?? throw $this->createNotFoundException('Établissement introuvable.');

        if ($slug !== $orphelinat->getSlug()) {
            return $this->redirectToRoute('orphelinat_show', ['id' => $id, 'slug' => $orphelinat->getSlug()], Response::HTTP_MOVED_PERMANENTLY);
        }

        return $this->render('public/orphelinat/show.html.twig', [
            'orphelinat' => $orphelinat,
        ]);
    }
}
