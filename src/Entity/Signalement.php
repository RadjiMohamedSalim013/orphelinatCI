<?php

namespace App\Entity;

use App\Enum\SignalementStatut;
use App\Enum\SignalementType;
use App\Repository\SignalementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Signalement public d'une information erronée. Il ne modifie jamais la fiche :
 * l'administration décide et applique elle-même les corrections.
 */
#[ORM\Entity(repositoryClass: SignalementRepository::class)]
#[ORM\Index(name: 'idx_signalement_statut', columns: ['statut'])]
class Signalement
{
    use TraitCreatedAt;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'signalements')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Orphelinat $orphelinat = null;

    #[ORM\Column(length: 20, enumType: SignalementType::class)]
    #[Assert\NotNull(message: 'Choisissez le type de problème.')]
    private ?SignalementType $type = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Décrivez l\'information à corriger.')]
    #[Assert\Length(min: 10, max: 2000, minMessage: 'Merci de donner un peu plus de détails (10 caractères minimum).')]
    private ?string $message = null;

    /** Moyen de recontacter l'auteur, facultatif. */
    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $contact = null;

    #[ORM\Column(length: 20, enumType: SignalementStatut::class)]
    private SignalementStatut $statut = SignalementStatut::Nouveau;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $decision = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $traiteAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrphelinat(): ?Orphelinat
    {
        return $this->orphelinat;
    }

    public function setOrphelinat(?Orphelinat $orphelinat): static
    {
        $this->orphelinat = $orphelinat;

        return $this;
    }

    public function getType(): ?SignalementType
    {
        return $this->type;
    }

    public function setType(?SignalementType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getContact(): ?string
    {
        return $this->contact;
    }

    public function setContact(?string $contact): static
    {
        $this->contact = $contact;

        return $this;
    }

    public function getStatut(): SignalementStatut
    {
        return $this->statut;
    }

    public function setStatut(SignalementStatut $statut): static
    {
        $this->statut = $statut;
        $this->traiteAt = $statut->isOpen() ? null : new \DateTimeImmutable();

        return $this;
    }

    public function getDecision(): ?string
    {
        return $this->decision;
    }

    public function setDecision(?string $decision): static
    {
        $this->decision = $decision;

        return $this;
    }

    public function getTraiteAt(): ?\DateTimeImmutable
    {
        return $this->traiteAt;
    }
}
