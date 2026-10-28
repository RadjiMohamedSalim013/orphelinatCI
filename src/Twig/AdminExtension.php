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
            new TwigFunction('echelle_graphique', $this->echelle(...)),
        ];
    }

    /**
     * Haut d’échelle « rond » pour 4 graduations (pas de 1 ; 1,5 ; 2 ; 2,5 ; 3 ; 4 ; 5 ; 6 ou 8 × 10ⁿ),
     * au plus près du maximum pour que les barres occupent la hauteur disponible.
     */
    public function echelle(int|float $maximum): int
    {
        if ($maximum <= 4) {
            return 4;
        }

        $brut = $maximum / 4;
        $puissance = 10 ** floor(log10($brut));
        foreach ([1, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10] as $facteur) {
            if ($facteur * $puissance >= $brut) {
                return (int) ceil($facteur * $puissance * 4);
            }
        }

        return (int) ceil($maximum);
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
