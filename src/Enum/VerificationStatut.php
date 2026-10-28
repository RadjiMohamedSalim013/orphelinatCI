<?php

namespace App\Enum;

enum VerificationStatut: string
{
    case NonVerifie = 'non_verifie';
    case Verifie = 'verifie';

    public function label(): string
    {
        return match ($this) {
            self::NonVerifie => 'Non vérifié',
            self::Verifie => 'Vérifié',
        };
    }
}
