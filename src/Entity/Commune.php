<?php

namespace App\Entity;

use App\Repository\CommuneRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CommuneRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_commune_ville_nom', columns: ['ville_id', 'nom'])]
#[UniqueEntity(fields: ['ville', 'nom'], message: 'Cette commune existe déjà pour cette ville.', errorPath: 'nom')]
class Commune
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $nom = null;

    #[ORM\ManyToOne(inversedBy: 'communes')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Ville $ville = null;

    /**
     * @var Collection<int, Quartier>
     */
    #[ORM\OneToMany(targetEntity: Quartier::class, mappedBy: 'commune')]
    #[ORM\OrderBy(['nom' => 'ASC'])]
    private Collection $quartiers;

    /**
     * @var Collection<int, Orphelinat>
     */
    #[ORM\OneToMany(targetEntity: Orphelinat::class, mappedBy: 'commune')]
    private Collection $orphelinats;

    /**
     * @var Collection<int, PropositionOrphelinat>
     */
    #[ORM\OneToMany(targetEntity: PropositionOrphelinat::class, mappedBy: 'commune')]
    private Collection $propositionsOrphelinat;

    public function __construct()
    {
        $this->quartiers = new ArrayCollection();
        $this->orphelinats = new ArrayCollection();
        $this->propositionsOrphelinat = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string) $this->nom;
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

    public function getVille(): ?Ville
    {
        return $this->ville;
    }

    public function setVille(?Ville $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    /** Libellé complet, utile dans les listes déroulantes de l'administration. */
    public function getLibelleComplet(): string
    {
        return sprintf('%s — %s (%s)', $this->nom, $this->ville?->getNom(), $this->ville?->getPays()?->getNom());
    }

    /**
     * @return Collection<int, Quartier>
     */
    public function getQuartiers(): Collection
    {
        return $this->quartiers;
    }

    public function addQuartier(Quartier $quartier): static
    {
        if (!$this->quartiers->contains($quartier)) {
            $this->quartiers->add($quartier);
            $quartier->setCommune($this);
        }

        return $this;
    }

    public function removeQuartier(Quartier $quartier): static
    {
        if ($this->quartiers->removeElement($quartier)) {
            // set the owning side to null (unless already changed)
            if ($quartier->getCommune() === $this) {
                $quartier->setCommune(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Orphelinat>
     */
    public function getOrphelinats(): Collection
    {
        return $this->orphelinats;
    }

    /**
     * @return Collection<int, PropositionOrphelinat>
     */
    public function getPropositionsOrphelinat(): Collection
    {
        return $this->propositionsOrphelinat;
    }
}
