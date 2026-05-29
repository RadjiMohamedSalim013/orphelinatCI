<?php

namespace App\Controller;

use App\Repository\OrphelinatRepository;
use App\Repository\PropositionOrphelinatRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminDashboardController extends AbstractController
{
    public function __construct(
        private readonly PropositionOrphelinatRepository $propositionRepository,
        private readonly OrphelinatRepository $orphelinatRepository,
        private readonly UserRepository $userRepository
    ) {
    }

    #[Route('/admin', name: 'admin_dashboard')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $propositionsEnAttente = $this->propositionRepository->findPending();
        $totalOrphelinats = $this->orphelinatRepository->count([]);
        $totalUsers = $this->userRepository->count([]);
        $totalPropositions = $this->propositionRepository->count([]);

        return $this->render('admin/dashboard/index.html.twig', [
            'propositions' => $propositionsEnAttente,
            'totalOrphelinats' => $totalOrphelinats,
            'totalUsers' => $totalUsers,
            'totalPropositions' => $totalPropositions,
        ]);
    }
}
