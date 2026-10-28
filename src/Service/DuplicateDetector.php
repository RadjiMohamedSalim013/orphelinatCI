<?php

namespace App\Service;

use App\Entity\PropositionOrphelinat;
use App\Repository\OrphelinatRepository;
use App\Repository\PropositionOrphelinatRepository;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Repère les établissements déjà répertoriés qui ressemblent à une proposition :
 * nom proche (sans accents ni mots génériques) ou même numéro de téléphone.
 */
class DuplicateDetector
{
    /** Mots trop fréquents pour distinguer deux établissements. */
    private const STOP_WORDS = ['orphelinat', 'orphelinats', 'centre', 'maison', 'foyer', 'association', 'le', 'la', 'les', 'de', 'des', 'du', 'd', 'l', 'et', 'pour', 'enfants', 'enfant'];

    private const SIMILARITY_THRESHOLD = 80.0;

    private AsciiSlugger $slugger;

    public function __construct(
        private readonly OrphelinatRepository $orphelinatRepository,
        private readonly PropositionOrphelinatRepository $propositionRepository,
    ) {
        $this->slugger = new AsciiSlugger('fr');
    }

    /**
     * @return list<array{id: int, nom: string, commune: string, ville: string, statut: mixed, raisons: list<string>, score: float}>
     */
    public function findSimilarOrphelinats(PropositionOrphelinat $proposition): array
    {
        $nom = $this->normalize((string) $proposition->getNom());
        $telephone = $this->digits($proposition->getTelephone());
        $communeId = $proposition->getCommune()?->getId();

        $matches = [];
        foreach ($this->orphelinatRepository->findDuplicateCandidates() as $candidate) {
            $raisons = [];
            $score = $this->similarity($nom, $this->normalize($candidate['nom']));

            if ($score >= self::SIMILARITY_THRESHOLD) {
                $raisons[] = sprintf('Nom similaire (%d %%)', (int) round($score));
            }
            if (null !== $telephone && $telephone === $this->digits($candidate['telephone'])) {
                $raisons[] = 'Même numéro de téléphone';
                $score = max($score, 100.0);
            }
            if (!$raisons) {
                continue;
            }
            if (null !== $communeId && $communeId === $candidate['communeId']) {
                $raisons[] = 'Même commune';
            }

            $matches[] = $candidate + ['raisons' => $raisons, 'score' => $score];
        }

        usort($matches, static fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return array_slice($matches, 0, 10);
    }

    /**
     * @return list<array{id: int, nom: string}>
     */
    public function findSimilarOpenPropositions(PropositionOrphelinat $proposition): array
    {
        $nom = $this->normalize((string) $proposition->getNom());

        return array_values(array_filter(
            $this->propositionRepository->findOpenNamesExcept((int) $proposition->getId()),
            fn (array $other) => $this->similarity($nom, $this->normalize($other['nom'])) >= self::SIMILARITY_THRESHOLD,
        ));
    }

    public function normalize(string $nom): string
    {
        $words = explode('-', $this->slugger->slug($nom)->lower()->toString());
        $significant = array_diff($words, self::STOP_WORDS);

        return implode(' ', $significant ?: $words);
    }

    private function similarity(string $a, string $b): float
    {
        if ('' === $a || '' === $b) {
            return 0.0;
        }
        if ($a === $b) {
            return 100.0;
        }

        similar_text($a, $b, $percent);

        return $percent;
    }

    private function digits(?string $telephone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $telephone);

        // Les 8 derniers chiffres suffisent à comparer avec ou sans indicatif.
        return strlen($digits) >= 8 ? substr($digits, -8) : null;
    }
}
