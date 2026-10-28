<?php

namespace App\Controller\Admin;

use App\Enum\PropositionStatut;
use App\Enum\SignalementStatut;
use App\Repository\OrphelinatRepository;
use App\Repository\PropositionOrphelinatRepository;
use App\Repository\SignalementRepository;
use App\Service\StatistiquesAudience;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    #[Route('', name: 'admin_dashboard', methods: ['GET'])]
    public function index(
        OrphelinatRepository $orphelinatRepository,
        PropositionOrphelinatRepository $propositionRepository,
        SignalementRepository $signalementRepository,
        StatistiquesAudience $statistiques,
    ): Response {
        return $this->render('admin/dashboard/index.html.twig', [
            'orphelinats' => $orphelinatRepository->countByStatus(),
            'propositions' => $propositionRepository->countByStatut(),
            'signalementsOuverts' => $signalementRepository->countOpen(),
            'repartition' => $orphelinatRepository->countActiveByCommune(),
            'dernieresPropositions' => $propositionRepository->findByStatuts([PropositionStatut::EnAttente, PropositionStatut::EnVerification], 5),
            'derniersSignalements' => $signalementRepository->findByStatuts([SignalementStatut::Nouveau, SignalementStatut::EnCours], 5),
            'audience' => $statistiques->periode(7),
        ]);
    }
}
