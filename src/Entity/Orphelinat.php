<?php

namespace App\Entity;

use App\Enum\OrphelinatStatut;
use App\Enum\VerificationStatut;
use App\Repository\OrphelinatRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OrphelinatRepository::class)]
#[ORM\Index(name: 'idx_orphelinat_statut', columns: ['statut'])]
#[ORM\HasLifecycleCallbacks]
class Orphelinat
{
    use TraitCreatedAt;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom est requis.')]
    #[Assert\Length(min: 3, max: 255)]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 5000)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'L\'adresse ou une indication de localisation est requise.')]
    #[Assert\Length(max: 255)]
    private ?string $adresse = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\NotBlank(message: 'Le numéro de téléphone de l\'établissement est requis.')]
    #[Assert\Length(max: 50)]
    #[Assert\Regex(pattern: '/^[0-9+\s().-]{6,50}$/', message: 'Numéro de téléphone invalide.')]
    private ?string $telephone = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email(message: 'Adresse e-mail invalide.')]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(message: 'Adresse de site invalide.', requireTld: true)]
    #[Assert\Length(max: 255)]
    private ?string $siteWeb = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(message: 'Lien de réseau social invalide.', requireTld: true)]
    #[Assert\Length(max: 255)]
    private ?string $reseauSocial = null;

    /** Services et accompagnement proposés (informations vérifiées uniquement). */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 3000)]
    private ?string $services = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $document = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    #[Assert\Range(min: -90, max: 90, notInRangeMessage: 'La latitude doit être comprise entre -90 et 90.')]
    private ?string $latitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    #[Assert\Range(min: -180, max: 180, notInRangeMessage: 'La longitude doit être comprise entre -180 et 180.')]
    private ?string $longitude = null;

    #[ORM\ManyToOne(inversedBy: 'orphelinats')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'La commune est requise.')]
    private ?Commune $commune = null;

    #[ORM\ManyToOne(inversedBy: 'orphelinats')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Quartier $quartier = null;

    #[ORM\Column(length: 20, enumType: OrphelinatStatut::class)]
    private OrphelinatStatut $statut = OrphelinatStatut::Actif;

    #[ORM\Column(length: 20, enumType: VerificationStatut::class)]
    private VerificationStatut $verification = VerificationStatut::NonVerifie;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $verifiedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, OrphelinatPhoto>
     */
    #[ORM\OneToMany(targetEntity: OrphelinatPhoto::class, mappedBy: 'orphelinat', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $photos;

    /**
     * @var Collection<int, Signalement>
     */
    #[ORM\OneToMany(targetEntity: Signalement::class, mappedBy: 'orphelinat')]
    private Collection $signalements;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->photos = new ArrayCollection();
        $this->signalements = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getSiteWeb(): ?string
    {
        return $this->siteWeb;
    }

    public function setSiteWeb(?string $siteWeb): static
    {
        $this->siteWeb = $siteWeb;

        return $this;
    }

    public function getReseauSocial(): ?string
    {
        return $this->reseauSocial;
    }

    public function setReseauSocial(?string $reseauSocial): static
    {
        $this->reseauSocial = $reseauSocial;

        return $this;
    }

    public function getServices(): ?string
    {
        return $this->services;
    }

    public function setServices(?string $services): static
    {
        $this->services = $services;

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

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(?string $latitude): static
    {
        $this->latitude = '' === $latitude ? null : $latitude;

        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(?string $longitude): static
    {
        $this->longitude = '' === $longitude ? null : $longitude;

        return $this;
    }

    public function hasCoordinates(): bool
    {
        return null !== $this->latitude && null !== $this->longitude;
    }

    #[Assert\IsTrue(message: 'Renseignez à la fois la latitude et la longitude, ou aucune des deux.')]
    public function isCoordinatesComplete(): bool
    {
        return (null === $this->latitude) === (null === $this->longitude);
    }

    #[Assert\IsTrue(message: 'Le quartier choisi n\'appartient pas à la commune sélectionnée.')]
    public function isQuartierCoherent(): bool
    {
        return null === $this->quartier || null === $this->commune || $this->quartier->getCommune() === $this->commune;
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

    public function getQuartier(): ?Quartier
    {
        return $this->quartier;
    }

    public function setQuartier(?Quartier $quartier): static
    {
        $this->quartier = $quartier;

        return $this;
    }

    public function getVille(): ?Ville
    {
        return $this->commune?->getVille();
    }

    public function getPays(): ?Pays
    {
        return $this->commune?->getVille()?->getPays();
    }

    public function getStatut(): OrphelinatStatut
    {
        return $this->statut;
    }

    public function setStatut(OrphelinatStatut $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function isPubliclyVisible(): bool
    {
        return OrphelinatStatut::Archive !== $this->statut;
    }

    public function getVerification(): VerificationStatut
    {
        return $this->verification;
    }

    public function setVerification(VerificationStatut $verification): static
    {
        if (VerificationStatut::Verifie === $verification && VerificationStatut::Verifie !== $this->verification) {
            $this->verifiedAt = new \DateTimeImmutable();
        } elseif (VerificationStatut::NonVerifie === $verification) {
            $this->verifiedAt = null;
        }
        $this->verification = $verification;

        return $this;
    }

    public function isVerifie(): bool
    {
        return VerificationStatut::Verifie === $this->verification;
    }

    public function getVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, OrphelinatPhoto>
     */
    public function getPhotos(): Collection
    {
        return $this->photos;
    }

    public function addPhoto(OrphelinatPhoto $photo): static
    {
        if (!$this->photos->contains($photo)) {
            $this->photos->add($photo);
            $photo->setOrphelinat($this);
        }

        return $this;
    }

    public function removePhoto(OrphelinatPhoto $photo): static
    {
        $this->photos->removeElement($photo);

        return $this;
    }

    /**
     * @return Collection<int, Signalement>
     */
    public function getSignalements(): Collection
    {
        return $this->signalements;
    }

    /** Segment d'URL lisible, dérivé du nom (non stocké : il suit les renommages). */
    public function getSlug(): string
    {
        return self::slugify((string) $this->nom);
    }

    public static function slugify(string $nom): string
    {
        $slug = (new AsciiSlugger('fr'))->slug($nom)->lower()->truncate(80, '', false)->trim('-')->toString();

        return '' !== $slug ? $slug : 'orphelinat';
    }

    public function getLocalisationLabel(): string
    {
        $parts = array_filter([
            $this->quartier?->getNom(),
            $this->commune?->getNom(),
            $this->getVille()?->getNom(),
        ]);

        return implode(', ', array_unique($parts));
    }
}
