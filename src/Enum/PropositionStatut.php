<?php

namespace App\Enum;

enum PropositionStatut: string
{
    case EnAttente = 'en_attente';
    case EnVerification = 'en_verification';
    case Acceptee = 'acceptee';
    case Refusee = 'refusee';

    public function label(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente',
            self::EnVerification => 'En cours de vérification',
            self::Acceptee => 'Acceptée',
            self::Refusee => 'Refusée',
        };
    }

    public function isOpen(): bool
    {
        return self::EnAttente === $this || self::EnVerification === $this;
    }
}
