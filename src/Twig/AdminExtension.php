<?php

namespace App\Twig;

use App\Repository\PropositionOrphelinatRepository;
use App\Repository\SignalementRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Compteurs affichés dans la navigation d'administration.
 */
class AdminExtension extends AbstractExtension
{
    public function __construct(
        private readonly PropositionOrphelinatRepository $propositionRepository,
        private readonly SignalementRepository $signalementRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('admin_compteurs', $this->compteurs(...)),
        ];
    }

    /**
     * @return array{propositions: int, signalements: int}
     */
    public function compteurs(): array
    {
        return [
            'propositions' => $this->propositionRepository->countOpen(),
            'signalements' => $this->signalementRepository->countOpen(),
        ];
    }
}
