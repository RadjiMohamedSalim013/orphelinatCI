<?php

namespace App\Enum;

/**
 * Situation de l'établissement. Seuls les établissements actifs apparaissent
 * dans l'annuaire ; une fiche fermée reste consultable, une fiche archivée non.
 */
enum OrphelinatStatut: string
{
    case Actif = 'actif';
    case Ferme = 'ferme';
    case Archive = 'archive';

    public function label(): string
    {
        return match ($this) {
            self::Actif => 'Actif',
            self::Ferme => 'Fermé',
            self::Archive => 'Archivé',
        };
    }
}
