<?php

namespace App\Controller\Public;

use App\Entity\PropositionOrphelinat;
use App\Form\PropositionOrphelinatType;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class PropositionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerInterface $slugger
    ) {
    }

    #[Route('/proposer-orphelinat', name: 'proposer_orphelinat', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
$proposition = new PropositionOrphelinat();
        $proposition->setStatut('en_attente');
        $proposition->setCreatedAt(new \DateTimeImmutable());

        $form = $this->createForm(PropositionOrphelinatType::class, $proposition);
$form->handleRequest($request);

        // Debug dumps removed to fix DateTime crash and clean logs\n        // dump('SUBMITTED: ' . $form->isSubmitted());\n        // dump('VALID: ' . $form->isValid());\n        // dump('ENTITY STATUS: ' . $proposition->getStatut());\n        // dump('CREATED AT:', $proposition->getCreatedAt());

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Formulaire invalide. Vérifiez les champs obligatoires.');
            // Erreurs affichées via flashes
            return $this->render('public/proposition/new.html.twig', [
                'form' => $form->createView(),
            ]);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle file uploads (defense-in-depth)
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $mime = $imageFile->getMimeType();
                $allowedImageMimes = ['image/jpeg', 'image/png', 'image/webp'];
                $allowedExtensions = ['jpeg', 'jpg', 'png', 'webp'];

                if (!in_array($mime, $allowedImageMimes, true)) {
                    $this->addFlash('error', 'Le fichier image doit être un JPG, PNG ou WEBP.');
                    return $this->render('public/proposition/new.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }

                $ext = strtolower((string) $imageFile->guessExtension());
                if (!$ext || !in_array($ext, $allowedExtensions, true)) {
                    $this->addFlash('error', 'Extension image invalide.');
                    return $this->render('public/proposition/new.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }

                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $this->slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$ext;

                try {
                    $imageFile->move(
                        $this->getParameter('uploads_images_directory'),
                        $newFilename
                    );
                    $proposition->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image.');
                    return $this->render('public/proposition/new.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }
            }

            $documentFile = $form->get('document')->getData();
            if ($documentFile) {
                $mime = $documentFile->getMimeType();
                $allowedDocumentMimes = ['application/pdf'];

                if (!in_array($mime, $allowedDocumentMimes, true)) {
                    $this->addFlash('error', 'Le document doit être un PDF.');
                    return $this->render('public/proposition/new.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }

                $originalFilename = pathinfo($documentFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $this->slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.pdf';

                try {
                    $documentFile->move(
                        $this->getParameter('uploads_documents_directory'),
                        $newFilename
                    );
                    $proposition->setDocument($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload du document.');
                    return $this->render('public/proposition/new.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }
            }


            $this->entityManager->persist($proposition);
            $this->entityManager->flush();

            // ID: $proposition->getId()

            $this->addFlash('success', 'Votre proposition a été envoyée et sera vérifiée par un administrateur. ID: ' . $proposition->getId());

            return $this->redirectToRoute('proposer_orphelinat'); // Redirect to form or home
        }

        return $this->render('public/proposition/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}

