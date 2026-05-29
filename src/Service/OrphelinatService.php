<?php

namespace App\Service;

use App\Entity\Pays;
use App\Entity\Ville;
use App\Entity\Commune;
use App\Entity\Quartier;
use App\Repository\OrphelinatRepository;
use App\Repository\PaysRepository;
use App\Repository\VilleRepository;
use App\Repository\CommuneRepository;
use App\Repository\QuartierRepository;
use Symfony\Component\HttpFoundation\Request;

class OrphelinatService
{
    public function __construct(
        private readonly OrphelinatRepository $orphelinatRepository,
        private readonly PaysRepository $paysRepository,
        private readonly VilleRepository $villeRepository,
        private readonly CommuneRepository $communeRepository,
        private readonly QuartierRepository $quartierRepository
    ) {
    }

    public function getFilteredOrphelinats(array $filters, int $page = 1, int $limit = 10): array
    {
        return $this->orphelinatRepository->findByFilters($filters, $page, $limit);
    }

    public function getFiltersData(): array
    {
        return [
            'pays' => $this->paysRepository->findAll(),
            'villes' => $this->villeRepository->findAll(),
            'communes' => $this->communeRepository->findAll(),
            'quartiers' => $this->quartierRepository->findAll(),
        ];
    }

    public function getCurrentFilters(Request $request): array
    {
        return [
            'search' => $request->query->get('search', ''),
            'pays_id' => $request->query->get('pays_id'),
            'ville_id' => $request->query->get('ville_id'),
            'commune_id' => $request->query->get('commune_id'),
            'quartier_id' => $request->query->get('quartier_id'),
            'page' => $request->query->getInt('page', 1),
        ];
    }

    public function findOrphelinatById(int $id): ?\App\Entity\Orphelinat
    {
        return $this->orphelinatRepository->find($id);
    }
}

