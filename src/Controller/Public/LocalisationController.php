<?php

namespace App\Controller\Public;

use App\Repository\CommuneRepository;
use App\Repository\QuartierRepository;
use App\Repository\VilleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Listes en cascade pays > ville > commune > quartier (lecture seule).
 */
#[Route('/api/localisations', name: 'api_localisations_')]
class LocalisationController extends AbstractController
{
    #[Route('/villes', name: 'villes', methods: ['GET'])]
    public function villes(Request $request, VilleRepository $repository): JsonResponse
    {
        return $this->options($repository->findByPaysId($this->id($request, 'pays')));
    }

    #[Route('/communes', name: 'communes', methods: ['GET'])]
    public function communes(Request $request, CommuneRepository $repository): JsonResponse
    {
        return $this->options($repository->findByVilleId($this->id($request, 'ville')));
    }

    #[Route('/quartiers', name: 'quartiers', methods: ['GET'])]
    public function quartiers(Request $request, QuartierRepository $repository): JsonResponse
    {
        return $this->options($repository->findByCommuneId($this->id($request, 'commune')));
    }

    private function id(Request $request, string $key): ?int
    {
        $value = $request->query->get($key);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /**
     * @param iterable<object{getId(): ?int, getNom(): ?string}> $entities
     */
    private function options(iterable $entities): JsonResponse
    {
        $data = [];
        foreach ($entities as $entity) {
            $data[] = ['id' => $entity->getId(), 'nom' => $entity->getNom()];
        }

        $response = new JsonResponse($data);
        $response->setPublic()->setMaxAge(300);

        return $response;
    }
}
