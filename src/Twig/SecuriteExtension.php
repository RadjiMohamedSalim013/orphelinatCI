<?php

namespace App\Twig;

use App\Security\NonceCsp;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * csp_nonce() : nonce à placer sur chaque <script> du gabarit (voir SecurityHeadersSubscriber).
 */
class SecuriteExtension extends AbstractExtension
{
    public function __construct(private readonly NonceCsp $nonce)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('csp_nonce', $this->nonce->valeur(...))];
    }
}
