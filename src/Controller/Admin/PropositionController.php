<?php

namespace App\Controller\Admin;

use App\Entity\PropositionOrphelinat;
use App\Enum\PropositionStatut;
use App\Enum\VerificationStatut;
use App\Repository\CommuneRepository;
use App\Repository\PropositionOrphelinatRepository;
use App\Service\DuplicateDetector;
use App\Service\FileUploader;
use App\Service\PropositionWorkflow;
use App\Service\WorkflowException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/propositions', name: 'admin_propositions_')]
class PropositionController extends AbstractController
{
    public function __construct(
        private readonly PropositionWorkflow $workflow,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, PropositionOrphelinatRepository $repository, DuplicateDetector $detector, CommuneRepository $communeRepository): Response
    {
        $filtre = (string) $request->query->get('statut', 'ouvertes');
        $statuts = match (true) {
            'toutes' === $filtre => [],
            null !== PropositionStatut::tryFrom($filtre) => [PropositionStatut::from($filtre)],
            default => [PropositionStatut::EnAttente, PropositionStatut::EnVerification],
        };

        $propositions = $repository->findByStatuts($statuts);

        // Doublons probables des propositions encore à traiter, affichés dans la liste et la fenêtre d'acceptation.
        $doublons = [];
        foreach ($propositions as $proposition) {
            if ($proposition->getStatut()->isOpen()) {
                $doublons[$proposition->getId()] = count($detector->findSimilarOrphelinats($proposition));
            }
        }

        return $this->render('admin/propositions/index.html.twig', [
            'propositions' => $propositions,
            'compteurs' => $repository->countByStatut(),
            'filtre' => $statuts ? $filtre : 'toutes',
            'doublons' => $doublons,
            'communes' => $communeRepository->createOrderedQueryBuilder()->getQuery()->getResult(),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(PropositionOrphelinat $proposition, DuplicateDetector $detector, CommuneRepository $communeRepository): Response
    {
        return $this->render('admin/propositions/show.html.twig', [
            'proposition' => $proposition,
            'doublons' => $detector->findSimilarOrphelinats($proposition),
            'propositionsSimilaires' => $detector->findSimilarOpenPropositions($proposition),
            'communes' => $communeRepository->createOrderedQueryBuilder()->getQuery()->getResult(),
        ]);
    }

    #[Route('/{id}/verification', name: 'verification', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function verification(Request $request, PropositionOrphelinat $proposition): Response
    {
        return $this->transition($request, $proposition, 'verification', function () use ($request, $proposition): string {
            $this->workflow->startVerification($proposition, $request->request->get('note'));

            return 'Proposition passée en cours de vérification.';
        });
    }

    #[Route('/{id}/note', name: 'note', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function note(Request $request, PropositionOrphelinat $proposition): Response
    {
        return $this->transition($request, $proposition, 'note', function () use ($request, $proposition): string {
            $this->workflow->saveNote($proposition, $request->request->get('note'));

            return 'Note enregistrée.';
        });
    }

    #[Route('/{id}/accepter', name: 'accepter', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function accepter(Request $request, PropositionOrphelinat $proposition, CommuneRepository $communeRepository): Response
    {
        if (!$this->isCsrfTokenValid('accepter_'.$proposition->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Action expirée. Réessayez.');

            return $this->redirectToRoute('admin_propositions_show', ['id' => $proposition->getId()]);
        }

        $commune = $communeRepository->find($request->request->getInt('commune'));
        if (null === $commune) {
            $this->addFlash('error', 'Choisissez la commune de l\'établissement (ajoutez-la dans « Localisations » si elle manque).');

            return $this->redirectToRoute('admin_propositions_show', ['id' => $proposition->getId()]);
        }

        $verification = $request->request->getBoolean('verifie') ? VerificationStatut::Verifie : VerificationStatut::NonVerifie;

        try {
            $orphelinat = $this->workflow->accept($proposition, $commune, $request->request->get('quartier'), $verification);
        } catch (WorkflowException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('admin_propositions_show', ['id' => $proposition->getId()]);
        }

        $this->addFlash('success', 'Proposition acceptée : la fiche a été créée. Complétez-la si nécessaire (coordonnées, photos…).');

        return $this->redirectToRoute('admin_orphelinat_edit', ['id' => $orphelinat->getId()]);
    }

    #[Route('/{id}/refuser', name: 'refuser', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function refuser(Request $request, PropositionOrphelinat $proposition): Response
    {
        return $this->transition($request, $proposition, 'refuser', function () use ($request, $proposition): string {
            $this->workflow->refuse($proposition, (string) $request->request->get('motif'));

            return 'Proposition refusée.';
        });
    }

    #[Route('/{id}/justificatif', name: 'justificatif', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function justificatif(PropositionOrphelinat $proposition, FileUploader $uploader): Response
    {
        $filename = $proposition->getDocument() ?? throw $this->createNotFoundException();

        // Les propositions antérieures stockaient le justificatif dans le dossier public.
        $path = $uploader->path($filename, FileUploader::PRIVATE)
            ?? $uploader->path($filename, FileUploader::DOCUMENT)
            ?? throw $this->createNotFoundException('Fichier introuvable sur le serveur.');

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $filename);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setPrivate();

        return $response;
    }

    private function transition(Request $request, PropositionOrphelinat $proposition, string $action, callable $apply): Response
    {
        if (!$this->isCsrfTokenValid($action.'_'.$proposition->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Action expirée. Réessayez.');
        } else {
            try {
                $this->addFlash('success', $apply());
            } catch (WorkflowException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_propositions_show', ['id' => $proposition->getId()]);
    }
}
