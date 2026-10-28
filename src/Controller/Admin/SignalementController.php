<?php

namespace App\Controller\Admin;

use App\Entity\Signalement;
use App\Enum\OrphelinatStatut;
use App\Enum\SignalementStatut;
use App\Enum\SignalementType;
use App\Repository\SignalementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/signalements', name: 'admin_signalements_')]
class SignalementController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, SignalementRepository $repository): Response
    {
        $filtre = (string) $request->query->get('statut', 'ouverts');
        $statuts = match (true) {
            'tous' === $filtre => [],
            null !== SignalementStatut::tryFrom($filtre) => [SignalementStatut::from($filtre)],
            default => [SignalementStatut::Nouveau, SignalementStatut::EnCours],
        };

        return $this->render('admin/signalements/index.html.twig', [
            'signalements' => $repository->findByStatuts($statuts),
            'filtre' => $statuts ? $filtre : 'tous',
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Signalement $signalement): Response
    {
        return $this->render('admin/signalements/show.html.twig', [
            'signalement' => $signalement,
        ]);
    }

    /**
     * Enregistre la décision. Les corrections de la fiche se font dans son
     * formulaire d'édition ; seule la fermeture peut être appliquée d'ici.
     */
    #[Route('/{id}/traiter', name: 'traiter', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function traiter(Request $request, Signalement $signalement, EntityManagerInterface $entityManager): Response
    {
        $statut = SignalementStatut::tryFrom((string) $request->request->get('statut'));
        $decision = trim((string) $request->request->get('decision'));

        if (null === $statut || !$this->isCsrfTokenValid('traiter_signalement_'.$signalement->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Action invalide ou expirée. Réessayez.');

            return $this->redirectToRoute('admin_signalements_show', ['id' => $signalement->getId()]);
        }

        if (SignalementStatut::Rejete === $statut && '' === $decision) {
            $this->addFlash('error', 'Indiquez pourquoi le signalement est rejeté.');

            return $this->redirectToRoute('admin_signalements_show', ['id' => $signalement->getId()]);
        }

        $signalement->setStatut($statut);
        if ('' !== $decision) {
            $signalement->setDecision($decision);
        }

        $orphelinat = $signalement->getOrphelinat();
        if (SignalementStatut::Traite === $statut
            && SignalementType::Ferme === $signalement->getType()
            && $request->request->getBoolean('marquer_ferme')
            && OrphelinatStatut::Actif === $orphelinat->getStatut()) {
            $orphelinat->setStatut(OrphelinatStatut::Ferme);
            $this->addFlash('info', sprintf('« %s » est désormais indiqué comme fermé.', $orphelinat->getNom()));
        }

        $entityManager->flush();
        $this->addFlash('success', sprintf('Signalement : %s.', mb_strtolower($statut->label())));

        return $this->redirectToRoute($statut->isOpen() ? 'admin_signalements_show' : 'admin_signalements_index', $statut->isOpen() ? ['id' => $signalement->getId()] : []);
    }
}
