<?php

namespace App\Model;

use Symfony\Component\HttpFoundation\Request;

/**
 * Critères de recherche de l'annuaire public, lus depuis la query string.
 * Les valeurs invalides sont ignorées plutôt que de provoquer une erreur.
 */
final class RechercheCriteres
{
    public const PAR_PAGE = 12;
    private const MAX_PAGE = 1000;

    public function __construct(
        public readonly ?int $paysId = null,
        public readonly ?int $villeId = null,
        public readonly ?int $communeId = null,
        public readonly string $recherche = '',
        public readonly int $page = 1,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        // all() plutôt que get() : un paramètre forgé en tableau (?pays[]=1) est ignoré au lieu de provoquer une erreur 400.
        $query = $request->query->all();
        $id = static function (string $key) use ($query): ?int {
            $value = $query[$key] ?? null;

            return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
        };

        $recherche = $query['q'] ?? '';
        $page = $query['page'] ?? null;

        return new self(
            $id('pays'),
            $id('ville'),
            $id('commune'),
            is_string($recherche) ? mb_substr(trim($recherche), 0, 100) : '',
            is_numeric($page) ? max(1, min(self::MAX_PAGE, (int) $page)) : 1,
        );
    }

    public function hasFilters(): bool
    {
        return null !== $this->paysId || null !== $this->villeId || null !== $this->communeId || '' !== $this->recherche;
    }

    /**
     * @return array<string, int|string>
     */
    public function toQuery(): array
    {
        return array_filter([
            'pays' => $this->paysId,
            'ville' => $this->villeId,
            'commune' => $this->communeId,
            'q' => $this->recherche,
        ], static fn ($v) => null !== $v && '' !== $v);
    }
}
