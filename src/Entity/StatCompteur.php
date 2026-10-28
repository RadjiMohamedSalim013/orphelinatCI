<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Compteur quotidien générique : pages vues (« page:home », « fiche:12 »,
 * « commune:5 ») et sources de trafic (« source:google.com »).
 */
#[ORM\Entity]
#[ORM\Table(name: 'stat_compteur')]
#[ORM\UniqueConstraint(name: 'uniq_stat_compteur', columns: ['jour', 'cle'])]
#[ORM\Index(name: 'idx_stat_compteur_cle', columns: ['cle'])]
class StatCompteur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $jour;

    #[ORM\Column(length: 150)]
    private string $cle;

    #[ORM\Column(options: ['default' => 0])]
    private int $total = 0;

    public function __construct(\DateTimeImmutable $jour, string $cle)
    {
        $this->jour = $jour;
        $this->cle = $cle;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
