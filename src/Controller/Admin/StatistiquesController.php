<?php

namespace App\Controller\Admin;

use App\Service\StatistiquesAudience;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class StatistiquesController extends AbstractController
{
    #[Route('/statistiques', name: 'admin_statistiques', methods: ['GET'])]
    public function index(Request $request, StatistiquesAudience $statistiques): Response
    {
        return $this->render('admin/statistiques/index.html.twig', [
            'stats' => $statistiques->periode($request->query->getInt('periode', 30)),
            'periodes' => StatistiquesAudience::PERIODES,
        ]);
    }
}
