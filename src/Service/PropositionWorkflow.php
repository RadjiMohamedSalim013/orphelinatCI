<?php

namespace App\Service;

use App\Entity\Commune;
use App\Entity\Orphelinat;
use App\Entity\PropositionOrphelinat;
use App\Entity\Quartier;
use App\Enum\PropositionStatut;
use App\Enum\VerificationStatut;
use App\Repository\QuartierRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Transitions d'une proposition : vérification, acceptation, refus.
 *
 * L'acceptation crée la fiche dans une transaction, après avoir verrouillé la
 * proposition : deux validations simultanées ne peuvent pas créer deux fiches.
 */
class PropositionWorkflow
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly QuartierRepository $quartierRepository,
        private readonly PropositionNotifier $notifier,
    ) {
    }

    public function startVerification(PropositionOrphelinat $proposition, ?string $note): void
    {
        $this->assertOpen($proposition);
        $proposition->setStatut(PropositionStatut::EnVerification);
        $this->appendNote($proposition, $note);
        $this->entityManager->flush();
    }

    public function saveNote(PropositionOrphelinat $proposition, ?string $note): void
    {
        $this->appendNote($proposition, $note);
        $this->entityManager->flush();
    }

    public function accept(PropositionOrphelinat $proposition, Commune $commune, ?string $quartierNom, VerificationStatut $verification): Orphelinat
    {
        $orphelinat = $this->entityManager->wrapInTransaction(function (EntityManagerInterface $em) use ($proposition, $commune, $quartierNom, $verification): Orphelinat {
            $em->refresh($proposition, LockMode::PESSIMISTIC_WRITE);
            $this->assertOpen($proposition);
            if (null !== $proposition->getOrphelinat()) {
                throw new WorkflowException('Une fiche a déjà été créée pour cette proposition.');
            }

            $orphelinat = (new Orphelinat())
                ->setNom($proposition->getNom())
                ->setDescription($proposition->getDescription())
                ->setAdresse($proposition->getAdresse())
                ->setTelephone($proposition->getTelephone())
                ->setEmail($proposition->getEmail())
                ->setImage($proposition->getImage())
                ->setCommune($commune)
                ->setQuartier($this->resolveQuartier($commune, $quartierNom))
                ->setVerification($verification);

            $em->persist($orphelinat);
            $proposition->setOrphelinat($orphelinat)->setStatut(PropositionStatut::Acceptee);

            return $orphelinat;
        });

        $this->notifier->notifyDecision($proposition);

        return $orphelinat;
    }

    public function refuse(PropositionOrphelinat $proposition, string $motif): void
    {
        $motif = trim($motif);
        if ('' === $motif) {
            throw new WorkflowException('Le motif du refus est obligatoire.');
        }

        $this->assertOpen($proposition);
        $proposition->setMotifRefus($motif)->setStatut(PropositionStatut::Refusee);
        $this->entityManager->flush();

        $this->notifier->notifyDecision($proposition);
    }

    private function resolveQuartier(Commune $commune, ?string $nom): ?Quartier
    {
        $nom = trim((string) $nom);
        if ('' === $nom) {
            return null;
        }

        foreach ($this->quartierRepository->findByCommuneId($commune->getId()) as $quartier) {
            if (0 === strcasecmp($quartier->getNom(), $nom)) {
                return $quartier;
            }
        }

        $quartier = (new Quartier())->setNom($nom)->setCommune($commune);
        $this->entityManager->persist($quartier);

        return $quartier;
    }

    private function assertOpen(PropositionOrphelinat $proposition): void
    {
        if (!$proposition->getStatut()->isOpen()) {
            throw new WorkflowException(sprintf('Cette proposition est déjà traitée (%s).', mb_strtolower($proposition->getStatut()->label())));
        }
    }

    private function appendNote(PropositionOrphelinat $proposition, ?string $note): void
    {
        $note = trim((string) $note);
        if ('' === $note) {
            return;
        }

        $entry = sprintf('[%s] %s', (new \DateTimeImmutable())->format('d/m/Y H:i'), $note);
        $proposition->setNoteAdmin(trim(($proposition->getNoteAdmin() ?? '')."\n".$entry));
    }
}
