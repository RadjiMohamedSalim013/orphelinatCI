<?php

namespace App\Entity;

use App\Repository\QuartierRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: QuartierRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_quartier_commune_nom', columns: ['commune_id', 'nom'])]
#[UniqueEntity(fields: ['commune', 'nom'], message: 'Ce quartier existe déjà pour cette commune.', errorPath: 'nom')]
class Quartier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $nom = null;

    #[ORM\ManyToOne(inversedBy: 'quartiers')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Commune $commune = null;

    /**
     * @var Collection<int, Orphelinat>
     */
    #[ORM\OneToMany(targetEntity: Orphelinat::class, mappedBy: 'quartier')]
    private Collection $orphelinats;

    public function __construct()
    {
        $this->orphelinats = new ArrayCollection();
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

    public function getCommune(): ?Commune
    {
        return $this->commune;
    }

    public function setCommune(?Commune $commune): static
    {
        $this->commune = $commune;

        return $this;
    }

    /**
     * @return Collection<int, Orphelinat>
     */
    public function getOrphelinats(): Collection
    {
        return $this->orphelinats;
    }
}
