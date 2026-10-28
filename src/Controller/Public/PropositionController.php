<?php

namespace App\Controller\Public;

use App\Entity\PropositionOrphelinat;
use App\Form\PropositionOrphelinatType;
use App\Repository\PaysRepository;
use App\Repository\PropositionOrphelinatRepository;
use App\Service\FileUploader;
use App\Service\SubmissionGuard;
use App\Service\UploadException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PropositionController extends AbstractController
{
    #[Route('/proposer-orphelinat', name: 'proposer_orphelinat', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        SubmissionGuard $guard,
        FileUploader $uploader,
        EntityManagerInterface $entityManager,
        PaysRepository $paysRepository,
    ): Response {
        $proposition = new PropositionOrphelinat();
        $form = $this->createForm(PropositionOrphelinatType::class, $proposition);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($erreur = $guard->check($request, $form)) {
                $this->addFlash('error', $erreur);
            } else {
                $fichiers = [];
                try {
                    if ($image = $form->get('image')->getData()) {
                        $proposition->setImage($fichiers[FileUploader::IMAGE] = $uploader->upload($image, FileUploader::IMAGE));
                    }
                    if ($document = $form->get('document')->getData()) {
                        $proposition->setDocument($fichiers[FileUploader::PRIVATE] = $uploader->upload($document, FileUploader::PRIVATE));
                    }

                    $entityManager->persist($proposition);
                    $entityManager->flush();

                    $this->addFlash('success', 'Merci ! Votre proposition a bien été reçue. Elle sera publiée seulement après vérification.');

                    return $this->redirectToRoute('proposition_suivi', ['token' => $proposition->getSuiviToken()]);
                } catch (UploadException $e) {
                    foreach ($fichiers as $kind => $filename) {
                        $uploader->delete($filename, $kind);
                    }
                    $this->addFlash('error', $e->getMessage());
                }
            }
        }

        return $this->render('public/proposition/new.html.twig', [
            'form' => $form,
            'pays' => $paysRepository->findAllOrdered(),
        ]);
    }

    #[Route('/proposition/suivi/{token}', name: 'proposition_suivi', methods: ['GET'], requirements: ['token' => '[a-f0-9]{32,64}'])]
    public function suivi(string $token, PropositionOrphelinatRepository $repository): Response
    {
        $proposition = $repository->findOneBy(['suiviToken' => $token])
            ?? throw $this->createNotFoundException('Demande introuvable.');

        $response = $this->render('public/proposition/suivi.html.twig', [
            'proposition' => $proposition,
        ]);
        // Lien privé : ne pas indexer ni transmettre l'URL à des tiers.
        $response->headers->set('X-Robots-Tag', 'noindex');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
