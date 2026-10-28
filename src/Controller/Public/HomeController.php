<?php

namespace App\Controller\Public;

use App\Repository\OrphelinatRepository;
use App\Repository\PaysRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home', methods: ['GET'])]
    #[Cache(maxage: 300, public: true, mustRevalidate: true)]
    public function index(OrphelinatRepository $orphelinatRepository, PaysRepository $paysRepository): Response
    {
        $stats = $orphelinatRepository->countByStatus();

        return $this->render('public/home.html.twig', [
            'pays' => $paysRepository->findAllOrdered(),
            'recents' => $orphelinatRepository->findRecent(6),
            'verifies' => $orphelinatRepository->findRecentlyVerified(3),
            'communes' => $orphelinatRepository->countActiveByCommune(8),
            'stats' => [
                'actifs' => $stats['actifs'],
                'verifies' => $stats['verifies'],
                'communes' => $orphelinatRepository->countActiveCommunes(),
            ],
        ]);
    }
}
