<?php

namespace App\Security;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Nonce de la politique de sécurité du contenu (CSP), propre à chaque requête :
 * seuls les <script> qui le portent (importmap, scripts du gabarit) s'exécutent.
 */
class NonceCsp implements ResetInterface
{
    private ?string $valeur = null;

    public function valeur(): string
    {
        return $this->valeur ??= base64_encode(random_bytes(18));
    }

    public function reset(): void
    {
        $this->valeur = null;
    }
}
