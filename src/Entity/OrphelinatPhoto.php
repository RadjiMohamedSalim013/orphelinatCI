<?php

namespace App\Entity;

use App\Repository\OrphelinatPhotoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrphelinatPhotoRepository::class)]
class OrphelinatPhoto
{
    use TraitCreatedAt;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'photos')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Orphelinat $orphelinat = null;

    #[ORM\Column(length: 255)]
    private ?string $filename = null;

    public function __construct(string $filename)
    {
        $this->filename = $filename;
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

    public function getFilename(): ?string
    {
        return $this->filename;
    }
}
