<?php

namespace App\Controller\Admin;

use App\Entity\Orphelinat;
use App\Repository\PropositionOrphelinatRepository;
use App\Entity\PropositionOrphelinat;
use App\Entity\Pays;
use App\Entity\Ville;
use App\Entity\Commune;
use App\Entity\Quartier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/propositions')]
class PropositionController extends AbstractController
{
    public function __construct(
        private readonly PropositionOrphelinatRepository $propositionRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/', name: 'admin_propositions_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $propositions = $this->propositionRepository->findPending();

        return $this->render('admin/propositions/index.html.twig', [
            'propositions' => $propositions,
        ]);
    }

    #[Route('/{id}', name: 'admin_propositions_show', methods: ['GET'])]
    public function show(?PropositionOrphelinat $proposition): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        if (!$proposition) {
            $this->addFlash('error', 'Proposition introuvable');
            return $this->redirectToRoute('admin_propositions_index');
        }

        $paysRepo = $this->entityManager->getRepository(Pays::class);
        $villeRepo = $this->entityManager->getRepository(Ville::class);
        $communeRepo = $this->entityManager->getRepository(Commune::class);
        $quartierRepo = $this->entityManager->getRepository(Quartier::class);

        return $this->render('admin/propositions/show.html.twig', [
            'proposition' => $proposition,
            'paysList' => $paysRepo->findAll(),
            'villesList' => $villeRepo->findAll(),
            'communesList' => $communeRepo->findAll(),
            'quartiersList' => $quartierRepo->findAll(),
        ]);
    }

    #[Route('/{id}/valider', name: 'admin_propositions_valider', methods: ['POST'])]
    public function valider(Request $request, PropositionOrphelinat $proposition): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        if ($proposition->getStatut() !== 'en_attente') {
            $this->addFlash('error', 'Cette proposition ne peut pas être validée.');
            return $this->redirectToRoute('admin_propositions_index');
        }

        if ($this->isCsrfTokenValid('valider_proposition_' . $proposition->getId(), $request->request->get('_token')) ) {
            $orphelinat = new Orphelinat();
            $orphelinat->setNom($proposition->getNom());
            $orphelinat->setDescription($proposition->getDescription());
            $orphelinat->setAdresse($proposition->getAdresse());
            $orphelinat->setTelephone($proposition->getTelephone());
            $orphelinat->setEmail($proposition->getEmail());
            $orphelinat->setImage($proposition->getImage());
            $orphelinat->setDocument($proposition->getDocument());
            $orphelinat->setClear('');

            $quartierId = $request->request->getInt('quartier_id');
            $quartier = $this->entityManager->getRepository(Quartier::class)->find($quartierId);
            if ($quartier) {
                $orphelinat->setQuartier($quartier);
            }

            $this->entityManager->persist($orphelinat);
            $proposition->setStatut('valide');
            $this->entityManager->persist($proposition);
            $this->entityManager->flush();

            $this->addFlash('success', 'Proposition validée ! Orphelinat créé.');
        }

        return $this->redirectToRoute('admin_propositions_index');
    }

    #[Route('/{id}/refuser', name: 'admin_propositions_refuser', methods: ['POST'])]
    public function refuser(Request $request, PropositionOrphelinat $proposition): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        if ($this->isCsrfTokenValid('refuser_proposition_' . $proposition->getId(), $request->request->get('_token'))) {
            $proposition->setStatut('refuse');
            $this->entityManager->persist($proposition);
            $this->entityManager->flush();

            $this->addFlash('success', 'Proposition refusée.');
        }

        return $this->redirectToRoute('admin_propositions_index');
    }
}
