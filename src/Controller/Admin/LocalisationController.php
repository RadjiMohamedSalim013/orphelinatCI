<?php

namespace App\Controller\Admin;

use App\Entity\Commune;
use App\Entity\Pays;
use App\Entity\Quartier;
use App\Entity\Ville;
use App\Repository\PaysRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Référentiel géographique : pays > villes > communes > quartiers.
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/localisations', name: 'admin_localisations_')]
class LocalisationController extends AbstractController
{
    /** type => [classe, classe du parent, méthode d'affectation du parent] */
    private const TYPES = [
        'pays' => [Pays::class, null, null],
        'ville' => [Ville::class, Pays::class, 'setPays'],
        'commune' => [Commune::class, Ville::class, 'setVille'],
        'quartier' => [Quartier::class, Commune::class, 'setCommune'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(PaysRepository $paysRepository): Response
    {
        return $this->render('admin/localisations/index.html.twig', [
            'arborescence' => $paysRepository->findArborescence(),
        ]);
    }

    #[Route('/ajouter/{type}', name: 'add', methods: ['POST'], requirements: ['type' => 'pays|ville|commune|quartier'])]
    public function add(Request $request, string $type): Response
    {
        if (!$this->isCsrfTokenValid('localisation', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Action expirée. Réessayez.');

            return $this->redirectToRoute('admin_localisations_index');
        }

        [$class, $parentClass, $setter] = self::TYPES[$type];
        $entity = (new $class())->setNom((string) $request->request->get('nom'));

        if (null !== $parentClass) {
            $parent = $this->entityManager->find($parentClass, $request->request->getInt('parent'));
            if (null === $parent) {
                $this->addFlash('error', 'Élément parent introuvable.');

                return $this->redirectToRoute('admin_localisations_index');
            }
            $entity->$setter($parent);
        }

        if ($this->persistIfValid($entity)) {
            $this->addFlash('success', sprintf('« %s » ajouté.', $entity->getNom()));
        }

        return $this->redirectToRoute('admin_localisations_index', ['_fragment' => $type.'-'.$entity->getId()]);
    }

    #[Route('/{type}/{id}/renommer', name: 'rename', methods: ['POST'], requirements: ['type' => 'pays|ville|commune|quartier', 'id' => '\d+'])]
    public function rename(Request $request, string $type, int $id): Response
    {
        $entity = $this->entityManager->find(self::TYPES[$type][0], $id) ?? throw $this->createNotFoundException();

        if (!$this->isCsrfTokenValid('localisation', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Action expirée. Réessayez.');
        } else {
            $ancien = $entity->getNom();
            $entity->setNom((string) $request->request->get('nom'));
            if ($this->persistIfValid($entity)) {
                $this->addFlash('success', sprintf('« %s » renommé en « %s ».', $ancien, $entity->getNom()));
            } else {
                $this->entityManager->refresh($entity);
            }
        }

        return $this->redirectToRoute('admin_localisations_index', ['_fragment' => $type.'-'.$id]);
    }

    #[Route('/{type}/{id}/supprimer', name: 'delete', methods: ['POST'], requirements: ['type' => 'pays|ville|commune|quartier', 'id' => '\d+'])]
    public function delete(Request $request, string $type, int $id): Response
    {
        $entity = $this->entityManager->find(self::TYPES[$type][0], $id) ?? throw $this->createNotFoundException();

        if (!$this->isCsrfTokenValid('localisation', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Action expirée. Réessayez.');

            return $this->redirectToRoute('admin_localisations_index');
        }

        try {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('« %s » supprimé.', $entity->getNom()));
        } catch (ForeignKeyConstraintViolationException) {
            // Les clés étrangères garantissent qu'aucun élément utilisé ne disparaît.
            $this->addFlash('error', sprintf('« %s » est encore utilisé (sous-éléments ou établissements) : suppression impossible.', $entity->getNom()));
        }

        return $this->redirectToRoute('admin_localisations_index');
    }

    private function persistIfValid(object $entity): bool
    {
        $violations = $this->validator->validate($entity);
        if (count($violations) > 0) {
            foreach ($violations as $violation) {
                $this->addFlash('error', $violation->getMessage());
            }

            return false;
        }

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return true;
    }
}
