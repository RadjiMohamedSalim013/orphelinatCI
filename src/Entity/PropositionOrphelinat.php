<?php

namespace App\Entity;

use App\Enum\PropositionStatut;
use App\Repository\PropositionOrphelinatRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Proposition d'établissement envoyée par le public. Elle n'est jamais publiée
 * telle quelle : un administrateur l'examine puis crée (ou non) la fiche.
 */
#[ORM\Entity(repositoryClass: PropositionOrphelinatRepository::class)]
#[ORM\Index(name: 'idx_proposition_statut', columns: ['statut'])]
class PropositionOrphelinat
{
    use TraitCreatedAt;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom de l\'établissement est requis.')]
    #[Assert\Length(min: 3, max: 255, minMessage: 'Le nom doit contenir au moins 3 caractères.')]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Indiquez une adresse ou des repères pour trouver l\'établissement.')]
    #[Assert\Length(max: 255)]
    private ?string $adresse = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    #[Assert\Regex(pattern: '/^[0-9+\s().-]{6,50}$/', message: 'Numéro de téléphone invalide.')]
    private ?string $telephone = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email(message: 'Adresse e-mail invalide.')]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    /** Justificatif, stocké hors du dossier public. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $document = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Indiquez comment vous connaissez cet établissement ou une source vérifiable.')]
    #[Assert\Length(min: 10, max: 1000, minMessage: 'Merci de préciser davantage la source (10 caractères minimum).')]
    private ?string $source = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Votre nom est requis.')]
    #[Assert\Length(max: 255)]
    private ?string $nomProposant = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Un moyen de vous recontacter est requis.')]
    #[Assert\Length(max: 255)]
    private ?string $contactProposant = null;

    /** Libellés tels que saisis ou déduits de la commune choisie. */
    #[ORM\Column(length: 255)]
    private ?string $pays = null;

    #[ORM\Column(length: 255)]
    private ?string $ville = null;

    #[ORM\ManyToOne(inversedBy: 'propositionsOrphelinat')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Commune $commune = null;

    /** Commune saisie librement lorsqu'elle n'existe pas encore dans le référentiel. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $communeSaisie = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $quartier = null;

    #[ORM\Column(length: 20, enumType: PropositionStatut::class)]
    private PropositionStatut $statut = PropositionStatut::EnAttente;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $motifRefus = null;

    /** Notes internes, jamais montrées au proposant. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $noteAdmin = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $traiteeAt = null;

    /** Fiche créée à partir de cette proposition (empêche une double création). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Orphelinat $orphelinat = null;

    /** Jeton aléatoire permettant au proposant de suivre sa demande. */
    #[ORM\Column(length: 64, unique: true)]
    private string $suiviToken;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->suiviToken = bin2hex(random_bytes(16));
    }

    #[Assert\Callback]
    public function validateLocalisation(ExecutionContextInterface $context): void
    {
        if (null !== $this->commune) {
            return;
        }

        if ('' === trim((string) $this->communeSaisie)) {
            $context->buildViolation('Choisissez une commune dans la liste ou saisissez-la.')
                ->atPath('commune')
                ->addViolation();
        }
        if ('' === trim((string) $this->ville)) {
            $context->buildViolation('Indiquez la ville.')->atPath('ville')->addViolation();
        }
        if ('' === trim((string) $this->pays)) {
            $context->buildViolation('Indiquez le pays.')->atPath('pays')->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = null === $nom ? null : trim($nom);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getDocument(): ?string
    {
        return $this->document;
    }

    public function setDocument(?string $document): static
    {
        $this->document = $document;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getNomProposant(): ?string
    {
        return $this->nomProposant;
    }

    public function setNomProposant(?string $nomProposant): static
    {
        $this->nomProposant = $nomProposant;

        return $this;
    }

    public function getContactProposant(): ?string
    {
        return $this->contactProposant;
    }

    public function setContactProposant(?string $contactProposant): static
    {
        $this->contactProposant = $contactProposant;

        return $this;
    }

    public function getContactProposantEmail(): ?string
    {
        $contact = trim((string) $this->contactProposant);

        return filter_var($contact, FILTER_VALIDATE_EMAIL) ? $contact : null;
    }

    public function getPays(): ?string
    {
        return $this->pays;
    }

    public function setPays(?string $pays): static
    {
        $this->pays = $pays;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    public function getCommune(): ?Commune
    {
        return $this->commune;
    }

    public function setCommune(?Commune $commune): static
    {
        $this->commune = $commune;

        return $this;
    }

    public function getCommuneSaisie(): ?string
    {
        return $this->communeSaisie;
    }

    public function setCommuneSaisie(?string $communeSaisie): static
    {
        $this->communeSaisie = $communeSaisie;

        return $this;
    }

    public function getCommuneLabel(): string
    {
        return $this->commune?->getNom() ?? (string) $this->communeSaisie;
    }

    public function getQuartier(): ?string
    {
        return $this->quartier;
    }

    public function setQuartier(?string $quartier): static
    {
        $this->quartier = $quartier;

        return $this;
    }

    public function getStatut(): PropositionStatut
    {
        return $this->statut;
    }

    public function setStatut(PropositionStatut $statut): static
    {
        $this->statut = $statut;
        $this->traiteeAt = $statut->isOpen() ? null : new \DateTimeImmutable();

        return $this;
    }

    public function getMotifRefus(): ?string
    {
        return $this->motifRefus;
    }

    public function setMotifRefus(?string $motifRefus): static
    {
        $this->motifRefus = $motifRefus;

        return $this;
    }

    public function getNoteAdmin(): ?string
    {
        return $this->noteAdmin;
    }

    public function setNoteAdmin(?string $noteAdmin): static
    {
        $this->noteAdmin = $noteAdmin;

        return $this;
    }

    public function getTraiteeAt(): ?\DateTimeImmutable
    {
        return $this->traiteeAt;
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

    public function getSuiviToken(): string
    {
        return $this->suiviToken;
    }

    public function getReference(): string
    {
        return sprintf('P-%05d', $this->id ?? 0);
    }
}
