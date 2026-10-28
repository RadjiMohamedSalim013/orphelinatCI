<?php

namespace App\Service;

use App\Entity\Orphelinat;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Lecture des statistiques d'audience pour l'administration.
 */
class StatistiquesAudience
{
    public const PERIODES = [7, 30, 90];

    private const PAGES = [
        'page:home' => 'Accueil',
        'page:orphelinat_index' => 'Recherche / annuaire',
        'page:proposer_orphelinat' => 'Formulaire de proposition',
    ];

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function periode(int $jours, ?\DateTimeImmutable $fin = null): array
    {
        $jours = in_array($jours, self::PERIODES, true) ? $jours : 30;
        $fin ??= new \DateTimeImmutable('today');
        $debut = $fin->modify(sprintf('-%d days', $jours - 1));
        $debutPrecedent = $debut->modify(sprintf('-%d days', $jours));
        $finPrecedente = $debut->modify('-1 day');

        $totaux = $this->totaux($debut, $fin);
        $precedent = $this->totaux($debutPrecedent, $finPrecedente);

        return [
            'jours' => $jours,
            'debut' => $debut,
            'fin' => $fin,
            'serie' => $this->serie($debut, $fin),
            'totaux' => $totaux,
            'evolution' => [
                'vues' => $this->evolution($totaux['vues'], $precedent['vues']),
                'visiteurs' => $this->evolution($totaux['visiteurs'], $precedent['visiteurs']),
            ],
            'pagesParVisiteur' => $totaux['visiteurs'] > 0 ? round($totaux['vues'] / $totaux['visiteurs'], 1) : 0,
            'appareils' => [
                ['libelle' => 'Mobile', 'icone' => 'fa-mobile-screen', 'total' => $totaux['mobile']],
                ['libelle' => 'Ordinateur', 'icone' => 'fa-desktop', 'total' => $totaux['ordinateur']],
                ['libelle' => 'Tablette', 'icone' => 'fa-tablet-screen-button', 'total' => $totaux['tablette']],
            ],
            'fiches' => $this->fiches($debut, $fin),
            'communes' => $this->communes($debut, $fin),
            'pages' => $this->pages($debut, $fin),
            'sources' => $this->sources($debut, $fin),
            'contributions' => [
                'propositions' => $this->compter('proposition_orphelinat', $debut, $fin),
                'signalements' => $this->compter('signalement', $debut, $fin),
            ],
        ];
    }

    /**
     * @return list<array{jour: \DateTimeImmutable, vues: int, visiteurs: int}>
     */
    private function serie(\DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        $lignes = $this->connection->fetchAllAssociativeIndexed(
            'SELECT jour, vues, visiteurs FROM stat_jour WHERE jour BETWEEN ? AND ?',
            [$debut->format('Y-m-d'), $fin->format('Y-m-d')],
        );

        $serie = [];
        for ($jour = $debut; $jour <= $fin; $jour = $jour->modify('+1 day')) {
            $ligne = $lignes[$jour->format('Y-m-d')] ?? null;
            $serie[] = ['jour' => $jour, 'vues' => (int) ($ligne['vues'] ?? 0), 'visiteurs' => (int) ($ligne['visiteurs'] ?? 0)];
        }

        return $serie;
    }

    /**
     * @return array{vues: int, visiteurs: int, mobile: int, tablette: int, ordinateur: int}
     */
    private function totaux(\DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        $ligne = $this->connection->fetchAssociative(
            'SELECT COALESCE(SUM(vues), 0) vues, COALESCE(SUM(visiteurs), 0) visiteurs, COALESCE(SUM(mobile), 0) mobile,
                    COALESCE(SUM(tablette), 0) tablette, COALESCE(SUM(ordinateur), 0) ordinateur
             FROM stat_jour WHERE jour BETWEEN ? AND ?',
            [$debut->format('Y-m-d'), $fin->format('Y-m-d')],
        ) ?: [];

        return array_map('intval', $ligne + ['vues' => 0, 'visiteurs' => 0, 'mobile' => 0, 'tablette' => 0, 'ordinateur' => 0]);
    }

    /**
     * @return array<string, int> clé => total, triés par total décroissant
     */
    private function classement(string $prefixe, \DateTimeImmutable $debut, \DateTimeImmutable $fin, int $limite): array
    {
        $lignes = $this->connection->fetchAllKeyValue(
            'SELECT cle, SUM(total) t FROM stat_compteur WHERE jour BETWEEN ? AND ? AND cle LIKE ? GROUP BY cle ORDER BY t DESC, cle LIMIT '.$limite,
            [$debut->format('Y-m-d'), $fin->format('Y-m-d'), $prefixe.':%'],
        );

        return array_map('intval', $lignes);
    }

    /**
     * @return list<array{id: int, nom: string, slug: string, commune: string, total: int}>
     */
    private function fiches(\DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        $classement = $this->classement('fiche', $debut, $fin, 8);
        $ids = array_map(static fn (string $cle) => (int) substr($cle, 6), array_keys($classement));
        if (!$ids) {
            return [];
        }

        $noms = $this->connection->fetchAllAssociativeIndexed(
            'SELECT o.id, o.nom, c.nom commune FROM orphelinat o JOIN commune c ON c.id = o.commune_id WHERE o.id IN (?)',
            [$ids],
            [ArrayParameterType::INTEGER],
        );

        $resultat = [];
        foreach ($classement as $cle => $total) {
            $id = (int) substr($cle, 6);
            if (isset($noms[$id])) {
                $resultat[] = ['id' => $id, 'nom' => $noms[$id]['nom'], 'slug' => Orphelinat::slugify($noms[$id]['nom']), 'commune' => $noms[$id]['commune'], 'total' => $total];
            }
        }

        return $resultat;
    }

    /**
     * @return list<array{nom: string, ville: string, total: int}>
     */
    private function communes(\DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        $classement = $this->classement('commune', $debut, $fin, 8);
        $ids = array_map(static fn (string $cle) => (int) substr($cle, 8), array_keys($classement));
        if (!$ids) {
            return [];
        }

        $noms = $this->connection->fetchAllAssociativeIndexed(
            'SELECT c.id, c.nom, v.nom ville FROM commune c JOIN ville v ON v.id = c.ville_id WHERE c.id IN (?)',
            [$ids],
            [ArrayParameterType::INTEGER],
        );

        $resultat = [];
        foreach ($classement as $cle => $total) {
            $id = (int) substr($cle, 8);
            if (isset($noms[$id])) {
                $resultat[] = ['nom' => $noms[$id]['nom'], 'ville' => $noms[$id]['ville'], 'total' => $total];
            }
        }

        return $resultat;
    }

    /**
     * @return list<array{libelle: string, total: int}>
     */
    private function pages(\DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        $resultat = [];
        foreach ($this->classement('page', $debut, $fin, 10) as $cle => $total) {
            $resultat[] = ['libelle' => self::PAGES[$cle] ?? $cle, 'total' => $total];
        }
        $fiches = (int) $this->connection->fetchOne(
            'SELECT COALESCE(SUM(total), 0) FROM stat_compteur WHERE jour BETWEEN ? AND ? AND cle LIKE ?',
            [$debut->format('Y-m-d'), $fin->format('Y-m-d'), 'fiche:%'],
        );
        if ($fiches > 0) {
            $resultat[] = ['libelle' => 'Fiches d’établissement', 'total' => $fiches];
        }
        usort($resultat, static fn (array $a, array $b) => $b['total'] <=> $a['total']);

        return $resultat;
    }

    /**
     * @return list<array{libelle: string, total: int}>
     */
    private function sources(\DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        $resultat = [];
        foreach ($this->classement('source', $debut, $fin, 8) as $cle => $total) {
            $hote = substr($cle, 7);
            $resultat[] = ['libelle' => 'direct' === $hote ? 'Accès direct ou favori' : $hote, 'total' => $total];
        }

        return $resultat;
    }

    private function compter(string $table, \DateTimeImmutable $debut, \DateTimeImmutable $fin): int
    {
        return (int) $this->connection->fetchOne(
            sprintf('SELECT COUNT(*) FROM %s WHERE created_at >= ? AND created_at < ?', $table),
            [$debut->format('Y-m-d 00:00:00'), $fin->modify('+1 day')->format('Y-m-d 00:00:00')],
        );
    }

    private function evolution(int $actuel, int $precedent): ?int
    {
        return $precedent > 0 ? (int) round(($actuel - $precedent) / $precedent * 100) : null;
    }
}
