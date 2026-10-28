<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Empreintes du jour servant uniquement à compter les visiteurs uniques.
 *
 * L'empreinte est un HMAC (secret de l'application) de l'adresse IP, du navigateur
 * et de la date : elle change chaque jour, ne permet pas de retrouver l'IP et
 * n'est conservée que deux jours.
 */
#[ORM\Entity]
#[ORM\Table(name: 'stat_visiteur')]
#[ORM\UniqueConstraint(name: 'uniq_stat_visiteur', columns: ['jour', 'empreinte'])]
class StatVisiteur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $jour;

    #[ORM\Column(length: 64)]
    private string $empreinte;

    public function __construct(\DateTimeImmutable $jour, string $empreinte)
    {
        $this->jour = $jour;
        $this->empreinte = $empreinte;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
