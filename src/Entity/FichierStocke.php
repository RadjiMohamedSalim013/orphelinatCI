<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Copie en base d'un fichier envoyé (photo, document, justificatif).
 *
 * Utile sur les hébergements au disque éphémère (Render gratuit) : le fichier
 * est restauré sur le disque à la première demande après un redémarrage.
 */
#[ORM\Entity]
#[ORM\Table(name: 'fichier_stocke')]
#[ORM\UniqueConstraint(name: 'uniq_fichier_chemin', columns: ['chemin'])]
class FichierStocke
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Chemin relatif, ex. « images/photo-ab12-640.webp » ou « private/justif.pdf ». */
    #[ORM\Column(length: 255)]
    private string $chemin;

    #[ORM\Column(length: 100)]
    private string $typeMime;

    /** @var resource|string */
    #[ORM\Column(type: Types::BLOB)]
    private $contenu;

    #[ORM\Column]
    private int $taille;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $chemin, string $typeMime, string $contenu)
    {
        $this->chemin = $chemin;
        $this->typeMime = $typeMime;
        $this->contenu = $contenu;
        $this->taille = strlen($contenu);
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getChemin(): string
    {
        return $this->chemin;
    }

    public function getTypeMime(): string
    {
        return $this->typeMime;
    }

    public function getContenu(): string
    {
        return is_resource($this->contenu) ? (string) stream_get_contents($this->contenu, -1, 0) : (string) $this->contenu;
    }
}
