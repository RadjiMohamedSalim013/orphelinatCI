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
        $totalCount = is_array($propositions) ? count($propositions) : iterator_count($propositions);

        return $this->render('admin/propositions/index.html.twig', [
            'propositions' => $propositions,
            'totalCount' => $totalCount,
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

            // IMPORTANT: le formulaire de validation POST ne contient peut-être pas quartier_id.
            // On fallback sur le quartier déjà associé à la proposition.
            $quartier = null;

            $quartierId = $request->request->getInt('quartier_id');
            if ($quartierId > 0) {
                $quartier = $this->entityManager->getRepository(Quartier::class)->find($quartierId);
            }

            if (!$quartier) {
                // Si la proposition a déjà un quartier, on l’utilise.
                // Attention : getQuartier() peut renvoyer une chaîne (ex: "Port Bouet")
                // selon comment le champ est stocké dans PropositionOrphelinat.
                $quartierFromProposition = $proposition->getQuartier();

                if ($quartierFromProposition instanceof \App\Entity\Quartier) {
                    $quartier = $quartierFromProposition;
                } elseif (is_string($quartierFromProposition) && trim($quartierFromProposition) !== '') {
                    $quartier = $this->entityManager->getRepository(Quartier::class)
                        ->findOneBy(['nom' => $quartierFromProposition]);
                }
            }

            if (!$quartier) {
                // Si le quartier n’existe pas (ou que getQuartier renvoie un libellé),
                // on le recrée en s’appuyant sur les libellés disponibles.
                $quartierNom = $proposition->getQuartier();

                if (is_string($quartierNom)) {
                    $quartierNom = trim($quartierNom);
                } else {
                    $quartierNom = null;
                }

                $paysNom = method_exists($proposition, 'getPays') ? $proposition->getPays() : null;
                $villeNom = method_exists($proposition, 'getVille') ? $proposition->getVille() : null;

                // Dans ton formulaire, l’utilisateur saisit seulement pays/ville/quartier (pas commune).
                // Donc on recrée une commune “fallback” pour pouvoir rattacher le quartier.
                // (Tu peux remplacer le libellé si tu as un champ commune ailleurs.)
                $communeNom = 'Commune (auto)';


                // On crée uniquement si on a au moins le nom du quartier et de la ville/commune.
                if ($quartierNom) {
                    $pays = null;
                    $ville = null;
                    $commune = null;

                    if (is_string($paysNom) && $paysNom !== '') {
                        $pays = $this->entityManager->getRepository(Pays::class)->findOneBy(['nom' => $paysNom]);
                        if (!$pays) {
                            $pays = new Pays();
                            $pays->setNom($paysNom);
                            $this->entityManager->persist($pays);
                        }
                    }

                    if (is_string($villeNom) && $villeNom !== '') {
                        $villeRepo = $this->entityManager->getRepository(Ville::class);
                        $ville = $pays ? $villeRepo->findOneBy(['nom' => $villeNom, 'pays' => $pays]) : $villeRepo->findOneBy(['nom' => $villeNom]);
                        if (!$ville) {
                            $ville = new Ville();
                            $ville->setNom($villeNom);
                            if ($pays) {
                                $ville->setPays($pays);
                            }
                            $this->entityManager->persist($ville);
                        }
                    }

                    if (is_string($communeNom) && $communeNom !== '') {
                        $communeRepo = $this->entityManager->getRepository(Commune::class);
                        $commune = $ville ? $communeRepo->findOneBy(['nom' => $communeNom, 'ville' => $ville]) : $communeRepo->findOneBy(['nom' => $communeNom]);
                        if (!$commune) {
                            $commune = new Commune();
                            $commune->setNom($communeNom);
                            if ($ville) {
                                $commune->setVille($ville);
                            }
                            $this->entityManager->persist($commune);
                        }
                    }

                    // On ne peut créer Quartier que s’il y a une commune.
                    if ($commune) {
                        $quartier = new \App\Entity\Quartier();
                        $quartier->setNom($quartierNom);
                        $quartier->setCommune($commune);
                        $this->entityManager->persist($quartier);
                    }
                }
            }

            // Dernier garde-fou : ne pas tenter de setQuartier si toujours null.
            if (!$quartier) {
                $this->addFlash('error', 'Validation impossible : quartier introuvable et non recréable.');
                return $this->redirectToRoute('admin_propositions_index');
            }

            $orphelinat->setQuartier($quartier);




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
