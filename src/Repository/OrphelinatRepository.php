<?php

namespace App\Repository;

use App\Entity\Orphelinat;
use App\Enum\OrphelinatStatut;
use App\Enum\VerificationStatut;
use App\Model\RechercheCriteres;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Orphelinat>
 */
class OrphelinatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Orphelinat::class);
    }

    /**
     * Recherche publique : seuls les établissements actifs sont listés.
     *
     * @return array{items: Orphelinat[], total: int, pages: int, page: int}
     */
    public function searchPublic(RechercheCriteres $criteres): array
    {
        $qb = $this->publicQueryBuilder($criteres)
            ->orderBy('o.nom', 'ASC')
            ->setFirstResult(($criteres->page - 1) * RechercheCriteres::PAR_PAGE)
            ->setMaxResults(RechercheCriteres::PAR_PAGE);

        $paginator = new Paginator($qb, fetchJoinCollection: false);
        $total = count($paginator);

        return [
            'items' => iterator_to_array($paginator),
            'total' => $total,
            'pages' => max(1, (int) ceil($total / RechercheCriteres::PAR_PAGE)),
            'page' => $criteres->page,
        ];
    }

    /**
     * Points à afficher sur la carte pour les mêmes critères (toutes pages confondues).
     *
     * @return list<array{id: int, nom: string, latitude: string, longitude: string, commune: string, verifie: bool}>
     */
    public function findMapPoints(RechercheCriteres $criteres, int $limit = 500): array
    {
        $rows = $this->publicQueryBuilder($criteres)
            ->select('o.id, o.nom, o.latitude, o.longitude, c.nom AS commune, o.verification')
            ->andWhere('o.latitude IS NOT NULL AND o.longitude IS NOT NULL')
            ->orderBy('o.nom', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $r) => [
            'id' => $r['id'],
            'nom' => $r['nom'],
            'slug' => Orphelinat::slugify($r['nom']),
            'latitude' => $r['latitude'],
            'longitude' => $r['longitude'],
            'commune' => $r['commune'],
            'verifie' => VerificationStatut::Verifie === VerificationStatut::tryFrom($r['verification'] instanceof VerificationStatut ? $r['verification']->value : (string) $r['verification']),
        ], $rows);
    }

    /**
     * @return Orphelinat[]
     */
    public function findRecent(int $limit = 6): array
    {
        return $this->publicQueryBuilder(new RechercheCriteres())
            ->orderBy('o.createdAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findPublicById(int $id): ?Orphelinat
    {
        return $this->createQueryBuilder('o')
            ->join('o.commune', 'c')
            ->join('c.ville', 'v')
            ->join('v.pays', 'p')
            ->leftJoin('o.quartier', 'q')
            ->leftJoin('o.photos', 'ph')
            ->addSelect('c', 'v', 'p', 'q', 'ph')
            ->andWhere('o.id = :id')
            ->andWhere('o.statut <> :archive')
            ->setParameter('id', $id)
            ->setParameter('archive', OrphelinatStatut::Archive)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Liste d'administration avec filtres et tri.
     *
     * @param array{q?: string, statut?: ?OrphelinatStatut, verification?: ?VerificationStatut, commune?: ?int, tri?: string} $filtres
     *
     * @return array{items: Orphelinat[], total: int, pages: int, page: int}
     */
    public function searchAdmin(array $filtres, int $page, int $parPage = 20): array
    {
        $qb = $this->withLocalisation($this->createQueryBuilder('o'));

        if ('' !== ($q = trim($filtres['q'] ?? ''))) {
            $qb->andWhere('o.nom LIKE :q OR o.adresse LIKE :q OR o.telephone LIKE :q OR o.email LIKE :q')
                ->setParameter('q', '%'.addcslashes($q, '%_').'%');
        }
        if ($filtres['statut'] ?? null) {
            $qb->andWhere('o.statut = :statut')->setParameter('statut', $filtres['statut']);
        }
        if ($filtres['verification'] ?? null) {
            $qb->andWhere('o.verification = :verification')->setParameter('verification', $filtres['verification']);
        }
        if ($filtres['commune'] ?? null) {
            $qb->andWhere('c.id = :commune')->setParameter('commune', $filtres['commune']);
        }

        match ($filtres['tri'] ?? 'nom') {
            'recent' => $qb->orderBy('o.createdAt', 'DESC'),
            'commune' => $qb->orderBy('c.nom', 'ASC')->addOrderBy('o.nom', 'ASC'),
            'maj' => $qb->orderBy('o.updatedAt', 'DESC')->addOrderBy('o.createdAt', 'DESC'),
            default => $qb->orderBy('o.nom', 'ASC'),
        };

        $qb->setFirstResult(($page - 1) * $parPage)->setMaxResults($parPage);
        $paginator = new Paginator($qb, fetchJoinCollection: false);
        $total = count($paginator);

        return [
            'items' => iterator_to_array($paginator),
            'total' => $total,
            'pages' => max(1, (int) ceil($total / $parPage)),
            'page' => $page,
        ];
    }

    /**
     * Données minimales pour la détection de doublons.
     *
     * @return list<array{id: int, nom: string, telephone: ?string, communeId: int, commune: string, ville: string, statut: OrphelinatStatut}>
     */
    public function findDuplicateCandidates(): array
    {
        return $this->createQueryBuilder('o')
            ->select('o.id, o.nom, o.telephone, c.id AS communeId, c.nom AS commune, v.nom AS ville, o.statut')
            ->join('o.commune', 'c')
            ->join('c.ville', 'v')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * @return array{total: int, actifs: int, verifies: int, fermes: int, archives: int}
     */
    public function countByStatus(): array
    {
        $rows = $this->createQueryBuilder('o')
            ->select('o.statut, o.verification, COUNT(o.id) AS n')
            ->groupBy('o.statut', 'o.verification')
            ->getQuery()
            ->getArrayResult();

        $stats = ['total' => 0, 'actifs' => 0, 'verifies' => 0, 'fermes' => 0, 'archives' => 0];
        foreach ($rows as $row) {
            $n = (int) $row['n'];
            $stats['total'] += $n;
            $statut = $row['statut'] instanceof OrphelinatStatut ? $row['statut'] : OrphelinatStatut::from($row['statut']);
            $verification = $row['verification'] instanceof VerificationStatut ? $row['verification'] : VerificationStatut::from($row['verification']);
            match ($statut) {
                OrphelinatStatut::Actif => $stats['actifs'] += $n,
                OrphelinatStatut::Ferme => $stats['fermes'] += $n,
                OrphelinatStatut::Archive => $stats['archives'] += $n,
            };
            if (OrphelinatStatut::Actif === $statut && VerificationStatut::Verifie === $verification) {
                $stats['verifies'] += $n;
            }
        }

        return $stats;
    }

    /**
     * Répartition des établissements actifs par ville et commune.
     *
     * @return list<array{ville: string, commune: string, total: int}>
     */
    public function countActiveByCommune(int $limit = 15): array
    {
        $rows = $this->createQueryBuilder('o')
            ->select('IDENTITY(v.pays) AS paysId, v.id AS villeId, v.nom AS ville, c.id AS communeId, c.nom AS commune, COUNT(o.id) AS total')
            ->join('o.commune', 'c')
            ->join('c.ville', 'v')
            ->andWhere('o.statut = :actif')
            ->setParameter('actif', OrphelinatStatut::Actif)
            ->groupBy('c.id', 'v.id', 'v.nom', 'c.nom')
            ->orderBy('total', 'DESC')
            ->addOrderBy('c.nom', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $r) => [
            'paysId' => (int) $r['paysId'],
            'villeId' => (int) $r['villeId'],
            'ville' => $r['ville'],
            'communeId' => (int) $r['communeId'],
            'commune' => $r['commune'],
            'total' => (int) $r['total'],
        ], $rows);
    }

    /**
     * @return Orphelinat[]
     */
    public function findRecentlyVerified(int $limit = 3): array
    {
        return $this->publicQueryBuilder(new RechercheCriteres())
            ->andWhere('o.verification = :verifie')
            ->setParameter('verifie', VerificationStatut::Verifie)
            ->orderBy('o.verifiedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countActiveCommunes(): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(DISTINCT IDENTITY(o.commune))')
            ->andWhere('o.statut = :actif')
            ->setParameter('actif', OrphelinatStatut::Actif)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function publicQueryBuilder(RechercheCriteres $criteres): QueryBuilder
    {
        $qb = $this->withLocalisation($this->createQueryBuilder('o'))
            ->andWhere('o.statut = :actif')
            ->setParameter('actif', OrphelinatStatut::Actif);

        if (null !== $criteres->communeId) {
            $qb->andWhere('c.id = :commune')->setParameter('commune', $criteres->communeId);
        }
        if (null !== $criteres->villeId) {
            $qb->andWhere('v.id = :ville')->setParameter('ville', $criteres->villeId);
        }
        if (null !== $criteres->paysId) {
            $qb->andWhere('p.id = :pays')->setParameter('pays', $criteres->paysId);
        }
        if ('' !== $criteres->recherche) {
            $qb->andWhere('o.nom LIKE :q OR o.adresse LIKE :q OR q.nom LIKE :q')
                ->setParameter('q', '%'.addcslashes($criteres->recherche, '%_').'%');
        }

        return $qb;
    }

    private function withLocalisation(QueryBuilder $qb): QueryBuilder
    {
        return $qb
            ->join('o.commune', 'c')
            ->join('c.ville', 'v')
            ->join('v.pays', 'p')
            ->leftJoin('o.quartier', 'q')
            ->addSelect('c', 'v', 'p', 'q');
    }
}
