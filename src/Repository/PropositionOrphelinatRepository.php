<?php

namespace App\Repository;

use App\Entity\PropositionOrphelinat;
use App\Enum\PropositionStatut;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PropositionOrphelinat>
 */
class PropositionOrphelinatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PropositionOrphelinat::class);
    }

    /**
     * @param PropositionStatut[] $statuts liste vide = toutes
     *
     * @return PropositionOrphelinat[]
     */
    public function findByStatuts(array $statuts, int $limit = 200): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.commune', 'c')
            ->addSelect('c')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($statuts) {
            $qb->andWhere('p.statut IN (:statuts)')->setParameter('statuts', $statuts);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<string, int> nombre de propositions par valeur de statut
     */
    public function countByStatut(): array
    {
        $counts = array_fill_keys(array_map(static fn (PropositionStatut $s) => $s->value, PropositionStatut::cases()), 0);
        $rows = $this->createQueryBuilder('p')
            ->select('p.statut, COUNT(p.id) AS n')
            ->groupBy('p.statut')
            ->getQuery()
            ->getArrayResult();

        foreach ($rows as $row) {
            $statut = $row['statut'] instanceof PropositionStatut ? $row['statut']->value : $row['statut'];
            $counts[$statut] = (int) $row['n'];
        }

        return $counts;
    }

    public function countOpen(): int
    {
        return $this->count(['statut' => [PropositionStatut::EnAttente, PropositionStatut::EnVerification]]);
    }

    /**
     * Autres propositions encore ouvertes, pour repérer les envois multiples.
     *
     * @return list<array{id: int, nom: string}>
     */
    public function findOpenNamesExcept(int $id): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.id, p.nom')
            ->andWhere('p.id <> :id')
            ->andWhere('p.statut IN (:statuts)')
            ->setParameter('id', $id)
            ->setParameter('statuts', [PropositionStatut::EnAttente, PropositionStatut::EnVerification])
            ->getQuery()
            ->getArrayResult();
    }
}
