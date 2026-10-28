<?php

namespace App\Controller\Public;

use App\Entity\Signalement;
use App\Enum\OrphelinatStatut;
use App\Form\SignalementType;
use App\Repository\OrphelinatRepository;
use App\Service\SubmissionGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SignalementController extends AbstractController
{
    #[Route('/orphelinats/{id}/signaler', name: 'orphelinat_signaler', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function new(
        int $id,
        Request $request,
        OrphelinatRepository $orphelinatRepository,
        SubmissionGuard $guard,
        EntityManagerInterface $entityManager,
    ): Response {
        $orphelinat = $orphelinatRepository->findPublicById($id)
            ?? throw $this->createNotFoundException('Établissement introuvable.');

        $signalement = (new Signalement())->setOrphelinat($orphelinat);
        if (OrphelinatStatut::Ferme === $orphelinat->getStatut()) {
            $this->addFlash('info', 'Cet établissement est déjà indiqué comme fermé.');
        }

        $form = $this->createForm(SignalementType::class, $signalement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($erreur = $guard->check($request, $form)) {
                $this->addFlash('error', $erreur);
            } else {
                $entityManager->persist($signalement);
                $entityManager->flush();

                $this->addFlash('success', 'Merci ! Votre signalement a été transmis. La fiche sera corrigée après vérification.');

                return $this->redirectToRoute('orphelinat_show', ['id' => $orphelinat->getId(), 'slug' => $orphelinat->getSlug()]);
            }
        }

        return $this->render('public/signalement/new.html.twig', [
            'orphelinat' => $orphelinat,
            'form' => $form,
        ]);
    }
}
