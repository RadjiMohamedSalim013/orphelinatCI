<?php

namespace App\Controller\Public;

use App\Service\OrphelinatService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class OrphelinatController extends AbstractController
{
    public function __construct(
        private readonly OrphelinatService $orphelinatService
    ) {
    }

    #[Route('/', name: 'orphelinat_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $currentFilters = $this->orphelinatService->getCurrentFilters($request);
$result = $this->orphelinatService->getFilteredOrphelinats($currentFilters);
        $filtersData = $this->orphelinatService->getFiltersData();

        return $this->render('public/orphelinat/index.html.twig', [
            'orphelinats' => $result['orphelinats'],
            'totalPages' => $result['totalPages'],
            'currentPage' => $result['currentPage'],
            'totalCount' => $result['totalCount'],
            'filtersData' => $filtersData,
            'currentFilters' => $currentFilters,
        ]);
    }

#[Route('/orphelinats/{id}', name: 'orphelinat_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $orphelinat = $this->orphelinatService->findOrphelinatById($id);

        if (!$orphelinat) {
            throw $this->createNotFoundException('Orphelinat non trouvé.');
        }

        return $this->render('public/orphelinat/show.html.twig', [
            'orphelinat' => $orphelinat,
        ]);
    }
}

