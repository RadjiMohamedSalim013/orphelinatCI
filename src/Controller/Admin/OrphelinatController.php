<?php

namespace App\Controller\Admin;

use App\Entity\Orphelinat;
use App\Entity\OrphelinatPhoto;
use App\Entity\Quartier;
use App\Enum\OrphelinatStatut;
use App\Enum\VerificationStatut;
use App\Form\OrphelinatType;
use App\Repository\CommuneRepository;
use App\Repository\OrphelinatRepository;
use App\Repository\QuartierRepository;
use App\Service\FileUploader;
use App\Service\UploadException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/orphelinats', name: 'admin_orphelinat_')]
class OrphelinatController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FileUploader $uploader,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function index(Request $request, OrphelinatRepository $repository, CommuneRepository $communeRepository): Response
    {
        $filtres = [
            'q' => mb_substr(trim((string) $request->query->get('q', '')), 0, 100),
            'statut' => OrphelinatStatut::tryFrom((string) $request->query->get('statut')),
            'verification' => VerificationStatut::tryFrom((string) $request->query->get('verification')),
            'commune' => $request->query->getInt('commune') ?: null,
            'tri' => (string) $request->query->get('tri', 'nom'),
        ];

        return $this->render('admin/orphelinats/index.html.twig', [
            'resultats' => $repository->searchAdmin($filtres, max(1, $request->query->getInt('page', 1))),
            'filtres' => $filtres,
            'communes' => $communeRepository->createOrderedQueryBuilder()->getQuery()->getResult(),
        ]);
    }

    #[Route('/nouveau', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $orphelinat = new Orphelinat();
        $form = $this->createForm(OrphelinatType::class, $orphelinat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->save($orphelinat, $form)) {
            $this->addFlash('success', sprintf('« %s » a été ajouté à l\'annuaire.', $orphelinat->getNom()));

            return $this->redirectToRoute('admin_orphelinat_show', ['id' => $orphelinat->getId()]);
        }

        return $this->render('admin/orphelinats/form.html.twig', [
            'form' => $form,
            'orphelinat' => null,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Orphelinat $orphelinat): Response
    {
        return $this->render('admin/orphelinats/show.html.twig', [
            'orphelinat' => $orphelinat,
        ]);
    }

    #[Route('/{id}/modifier', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Orphelinat $orphelinat): Response
    {
        $form = $this->createForm(OrphelinatType::class, $orphelinat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->save($orphelinat, $form)) {
            $this->addFlash('success', 'Fiche mise à jour.');

            return $this->redirectToRoute('admin_orphelinat_show', ['id' => $orphelinat->getId()]);
        }

        return $this->render('admin/orphelinats/form.html.twig', [
            'form' => $form,
            'orphelinat' => $orphelinat,
        ]);
    }

    #[Route('/{id}/statut', name: 'statut', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function statut(Request $request, Orphelinat $orphelinat): Response
    {
        $statut = OrphelinatStatut::tryFrom((string) $request->request->get('statut'));

        if (null === $statut || !$this->isCsrfTokenValid('statut_orphelinat_'.$orphelinat->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Action invalide ou expirée. Réessayez.');
        } else {
            $orphelinat->setStatut($statut);
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Situation mise à jour : %s.', mb_strtolower($statut->label())));
        }

        return $this->redirectToRoute('admin_orphelinat_show', ['id' => $orphelinat->getId()]);
    }

    #[Route('/{id}/photos/{photo}/supprimer', name: 'photo_delete', methods: ['POST'], requirements: ['id' => '\d+', 'photo' => '\d+'])]
    public function deletePhoto(Request $request, Orphelinat $orphelinat, int $photo): Response
    {
        $entity = $this->entityManager->getRepository(OrphelinatPhoto::class)->findOneBy(['id' => $photo, 'orphelinat' => $orphelinat]);

        if ($entity && $this->isCsrfTokenValid('photo_'.$photo, (string) $request->request->get('_token'))) {
            $filename = $entity->getFilename();
            $orphelinat->removePhoto($entity);
            $this->entityManager->flush();
            $this->uploader->delete($filename, FileUploader::IMAGE);
            $this->addFlash('success', 'Photo supprimée.');
        } else {
            $this->addFlash('error', 'Photo introuvable ou action expirée.');
        }

        return $this->redirectToRoute('admin_orphelinat_edit', ['id' => $orphelinat->getId()]);
    }

    /**
     * Suppression définitive réservée aux fiches déjà archivées :
     * l'archivage reste la voie normale pour retirer une fiche.
     */
    #[Route('/{id}/supprimer', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Orphelinat $orphelinat): Response
    {
        if (OrphelinatStatut::Archive !== $orphelinat->getStatut()) {
            $this->addFlash('error', 'Archivez la fiche avant de la supprimer définitivement.');

            return $this->redirectToRoute('admin_orphelinat_show', ['id' => $orphelinat->getId()]);
        }

        if (!$this->isCsrfTokenValid('delete_orphelinat_'.$orphelinat->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Action expirée. Réessayez.');

            return $this->redirectToRoute('admin_orphelinat_show', ['id' => $orphelinat->getId()]);
        }

        $fichiers = [[$orphelinat->getDocument(), FileUploader::DOCUMENT]];
        foreach ($orphelinat->getPhotos() as $photo) {
            $fichiers[] = [$photo->getFilename(), FileUploader::IMAGE];
        }
        $nom = $orphelinat->getNom();

        $this->entityManager->remove($orphelinat);
        $this->entityManager->flush();

        // La photo principale peut être partagée avec la proposition d'origine : on la conserve.
        foreach ($fichiers as [$filename, $kind]) {
            $this->uploader->delete($filename, $kind);
        }

        $this->addFlash('success', sprintf('« %s » a été supprimé définitivement.', $nom));

        return $this->redirectToRoute('admin_orphelinat_list');
    }

    /**
     * Applique les champs non mappés (quartier libre, fichiers) puis enregistre.
     * Les anciens fichiers ne sont supprimés qu'une fois l'enregistrement réussi.
     */
    private function save(Orphelinat $orphelinat, FormInterface $form): bool
    {
        $nouveauQuartier = trim((string) $form->get('nouveauQuartier')->getData());
        if ('' !== $nouveauQuartier && $orphelinat->getCommune()) {
            $orphelinat->setQuartier($this->findOrCreateQuartier($orphelinat, $nouveauQuartier));
        }

        $nouveaux = [];
        $ancienDocument = null;
        try {
            // L'ancienne photo principale est conservée : elle peut être référencée par la proposition d'origine.
            if ($image = $form->get('image')->getData()) {
                $orphelinat->setImage($nouveaux[] = $this->uploader->upload($image, FileUploader::IMAGE));
            }
            if ($document = $form->get('document')->getData()) {
                $ancienDocument = $orphelinat->getDocument();
                $orphelinat->setDocument($nouveaux[] = $this->uploader->upload($document, FileUploader::DOCUMENT));
            }
            foreach ($form->get('photos')->getData() ?? [] as $photo) {
                $orphelinat->addPhoto(new OrphelinatPhoto($nouveaux[] = $this->uploader->upload($photo, FileUploader::IMAGE)));
            }
        } catch (UploadException $e) {
            foreach ($nouveaux as $filename) {
                $this->uploader->delete($filename, FileUploader::IMAGE);
                $this->uploader->delete($filename, FileUploader::DOCUMENT);
            }
            $this->addFlash('error', $e->getMessage());

            return false;
        }

        $this->entityManager->persist($orphelinat);
        $this->entityManager->flush();
        $this->uploader->delete($ancienDocument, FileUploader::DOCUMENT);

        return true;
    }

    private function findOrCreateQuartier(Orphelinat $orphelinat, string $nom): Quartier
    {
        /** @var QuartierRepository $repository */
        $repository = $this->entityManager->getRepository(Quartier::class);
        foreach ($repository->findByCommuneId($orphelinat->getCommune()->getId()) as $quartier) {
            if (0 === strcasecmp($quartier->getNom(), $nom)) {
                return $quartier;
            }
        }

        $quartier = (new Quartier())->setNom($nom)->setCommune($orphelinat->getCommune());
        $this->entityManager->persist($quartier);

        return $quartier;
    }
}
