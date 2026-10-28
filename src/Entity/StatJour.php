<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Audience agrégée par jour. Alimentée par App\Service\MesureAudience (requêtes
 * DBAL atomiques) ; l'entité sert au schéma et aux migrations.
 */
#[ORM\Entity]
#[ORM\Table(name: 'stat_jour')]
#[ORM\UniqueConstraint(name: 'uniq_stat_jour', columns: ['jour'])]
class StatJour
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $jour;

    /** Pages consultées. */
    #[ORM\Column(options: ['default' => 0])]
    private int $vues = 0;

    /** Visiteurs uniques du jour. */
    #[ORM\Column(options: ['default' => 0])]
    private int $visiteurs = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $mobile = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $tablette = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $ordinateur = 0;

    public function __construct(\DateTimeImmutable $jour)
    {
        $this->jour = $jour;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
