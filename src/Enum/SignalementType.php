<?php

namespace App\Enum;

enum SignalementType: string
{
    case Adresse = 'adresse';
    case Contact = 'contact';
    case Ferme = 'ferme';
    case Doublon = 'doublon';
    case Autre = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Adresse => 'Adresse ou localisation incorrecte',
            self::Contact => 'Contact obsolète ou incorrect',
            self::Ferme => 'Établissement fermé',
            self::Doublon => 'Fiche en double',
            self::Autre => 'Autre erreur',
        };
    }
}
