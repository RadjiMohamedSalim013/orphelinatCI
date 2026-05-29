<?php

namespace App\Entity;

use App\Repository\QuartierRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuartierRepository::class)]
class Quartier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\ManyToOne(inversedBy: 'quartiers')]
    #[ORM\JoinColumn(nullable: false)]
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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

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

    public function addOrphelinat(Orphelinat $orphelinat): static
    {
        if (!$this->orphelinats->contains($orphelinat)) {
            $this->orphelinats->add($orphelinat);
            $orphelinat->setQuartier($this);
        }

        return $this;
    }

    public function removeOrphelinat(Orphelinat $orphelinat): static
    {
        if ($this->orphelinats->removeElement($orphelinat)) {
            // set the owning side to null (unless already changed)
            if ($orphelinat->getQuartier() === $this) {
                $orphelinat->setQuartier(null);
            }
        }

        return $this;
    }


}
