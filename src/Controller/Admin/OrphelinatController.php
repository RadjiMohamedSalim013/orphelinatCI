<?php

namespace App\Controller\Admin;

use App\Entity\Commune;
use App\Entity\Orphelinat;
use App\Entity\Pays;
use App\Entity\Quartier;
use App\Entity\Ville;
use App\Form\OrphelinatType;
use App\Repository\OrphelinatRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

#[Route('/', name: 'admin_')]
class OrphelinatController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OrphelinatRepository $orphelinatRepository,
        private readonly SluggerInterface $slugger
    ) {
    }

    #[Route('/orphelinats', name: 'orphelinat_list', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $search = $request->query->get('search', '');
        $page = $request->query->getInt('page', 1);
        $limit = 10;

        $qb = $this->orphelinatRepository->createQueryBuilder('o')
            ->leftJoin('o.quartier', 'q')
            ->leftJoin('q.commune', 'c')
            ->leftJoin('c.ville', 'v')
            ->leftJoin('v.pays', 'p')
            ->addSelect('q, c, v, p')
            ->orderBy('o.nom', 'ASC');

        if ($search) {
            $qb->andWhere(
                $qb->expr()->orX(
                    'o.nom LIKE :search',
                    'o.description LIKE :search',
                    'o.adresse LIKE :search'
                )
            )->setParameter('search', '%' . $search . '%');
        }

        $totalQb = clone $qb;
        $totalQb->select('COUNT(o.id)');
        $totalCount = (int) $totalQb->getQuery()->getSingleScalarResult();

        $qb->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit);

        $orphelinats = $qb->getQuery()->getResult();
        $totalPages = (int) ceil($totalCount / $limit);

        return $this->render('admin/orphelinats/index.html.twig', [
            'orphelinats' => $orphelinats,
            'totalCount' => $totalCount,
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'search' => $search,
        ]);
    }

    #[Route('/orphelinats/create', name: 'orphelinat_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $orphelinat = new Orphelinat();
        $form = $this->createForm(OrphelinatType::class, $orphelinat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $quartier = $this->resolveQuartier($form);
            if (!$quartier) {
                $this->addFlash('error', 'Veuillez sélectionner un quartier existant OU saisir un nouveau pays, ville, commune et quartier.');
                return $this->render('admin/orphelinats/create.html.twig', [
                    'form' => $form->createView(),
                ]);
            }
            $orphelinat->setQuartier($quartier);

            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $newFilename = $this->uploadFile($imageFile, 'uploads_images_directory');
                if ($newFilename) {
                    $orphelinat->setImage($newFilename);
                }
            }

            $documentFile = $form->get('document')->getData();
            if ($documentFile) {
                $newFilename = $this->uploadFile($documentFile, 'uploads_documents_directory');
                if ($newFilename) {
                    $orphelinat->setDocument($newFilename);
                }
            }

            $orphelinat->setClear('');
            $this->entityManager->persist($orphelinat);
            $this->entityManager->flush();

            $this->addFlash('success', 'Orphelinat créé avec succès !');

            return $this->redirectToRoute('admin_orphelinat_list');
        }

        return $this->render('admin/orphelinats/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/orphelinats/{id}/edit', name: 'orphelinat_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Orphelinat $orphelinat): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $form = $this->createForm(OrphelinatType::class, $orphelinat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $quartier = $this->resolveQuartier($form);
            if ($quartier) {
                $orphelinat->setQuartier($quartier);
            }

            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $newFilename = $this->uploadFile($imageFile, 'uploads_images_directory');
                if ($newFilename) {
                    $this->deleteFile($orphelinat->getImage(), 'uploads_images_directory');
                    $orphelinat->setImage($newFilename);
                }
            }

            $documentFile = $form->get('document')->getData();
            if ($documentFile) {
                $newFilename = $this->uploadFile($documentFile, 'uploads_documents_directory');
                if ($newFilename) {
                    $this->deleteFile($orphelinat->getDocument(), 'uploads_documents_directory');
                    $orphelinat->setDocument($newFilename);
                }
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Orphelinat modifié avec succès !');

            return $this->redirectToRoute('admin_orphelinat_list');
        }

        return $this->render('admin/orphelinats/edit.html.twig', [
            'form' => $form->createView(),
            'orphelinat' => $orphelinat,
        ]);
    }

    #[Route('/orphelinats/{id}/delete', name: 'orphelinat_delete', methods: ['POST'])]
    public function delete(Request $request, Orphelinat $orphelinat): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        if ($this->isCsrfTokenValid('delete_orphelinat_' . $orphelinat->getId(), $request->request->get('_token'))) {
            $this->deleteFile($orphelinat->getImage(), 'uploads_images_directory');
            $this->deleteFile($orphelinat->getDocument(), 'uploads_documents_directory');

            $this->entityManager->remove($orphelinat);
            $this->entityManager->flush();

            $this->addFlash('success', 'Orphelinat supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_orphelinat_list');
    }

    private function resolveQuartier(\Symfony\Component\Form\FormInterface $form): ?Quartier
    {
        $quartierSelectionne = $form->get('quartier')->getData();

        if ($quartierSelectionne instanceof Quartier) {
            return $quartierSelectionne;
        }

        $nouveauPays = trim($form->get('nouveau_pays')->getData() ?? '');
        $nouvelleVille = trim($form->get('nouvelle_ville')->getData() ?? '');
        $nouvelleCommune = trim($form->get('nouvelle_commune')->getData() ?? '');
        $nouveauQuartier = trim($form->get('nouveau_quartier')->getData() ?? '');

        if (!$nouveauPays || !$nouvelleVille || !$nouvelleCommune || !$nouveauQuartier) {
            return null;
        }

        $paysRepo = $this->entityManager->getRepository(Pays::class);
        $villeRepo = $this->entityManager->getRepository(Ville::class);
        $communeRepo = $this->entityManager->getRepository(Commune::class);
        $quartierRepo = $this->entityManager->getRepository(Quartier::class);

        $pays = $paysRepo->findOneBy(['nom' => $nouveauPays]);
        if (!$pays) {
            $pays = new Pays();
            $pays->setNom($nouveauPays);
            $this->entityManager->persist($pays);
        }

        $ville = $villeRepo->findOneBy(['nom' => $nouvelleVille, 'pays' => $pays]);
        if (!$ville) {
            $ville = new Ville();
            $ville->setNom($nouvelleVille);
            $ville->setPays($pays);
            $this->entityManager->persist($ville);
        }

        $commune = $communeRepo->findOneBy(['nom' => $nouvelleCommune, 'ville' => $ville]);
        if (!$commune) {
            $commune = new Commune();
            $commune->setNom($nouvelleCommune);
            $commune->setVille($ville);
            $this->entityManager->persist($commune);
        }

        $quartier = $quartierRepo->findOneBy(['nom' => $nouveauQuartier, 'commune' => $commune]);
        if (!$quartier) {
            $quartier = new Quartier();
            $quartier->setNom($nouveauQuartier);
            $quartier->setCommune($commune);
            $this->entityManager->persist($quartier);
        }

        return $quartier;
    }

    private function uploadFile(\Symfony\Component\HttpFoundation\File\UploadedFile $file, string $parameter): ?string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        try {
            $file->move(
                $this->getParameter($parameter),
                $newFilename
            );
            return $newFilename;
        } catch (FileException $e) {
            $this->addFlash('error', 'Erreur lors de l\'upload du fichier.');
            return null;
        }
    }

    private function deleteFile(?string $filename, string $parameter): void
    {
        if (!$filename) {
            return;
        }

        $filepath = $this->getParameter($parameter) . '/' . $filename;
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }
}
