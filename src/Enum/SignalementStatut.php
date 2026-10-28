<?php

namespace App\Enum;

enum SignalementStatut: string
{
    case Nouveau = 'nouveau';
    case EnCours = 'en_cours';
    case Traite = 'traite';
    case Rejete = 'rejete';

    public function label(): string
    {
        return match ($this) {
            self::Nouveau => 'Nouveau',
            self::EnCours => 'En cours',
            self::Traite => 'Traité',
            self::Rejete => 'Rejeté',
        };
    }

    public function isOpen(): bool
    {
        return self::Nouveau === $this || self::EnCours === $this;
    }
}
