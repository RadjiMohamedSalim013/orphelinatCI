<?php

namespace App\Repository;

use App\Entity\Signalement;
use App\Enum\SignalementStatut;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Signalement>
 */
class SignalementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Signalement::class);
    }

    public function countOpen(): int
    {
        return $this->count(['statut' => [SignalementStatut::Nouveau, SignalementStatut::EnCours]]);
    }

    /**
     * @param SignalementStatut[] $statuts
     *
     * @return Signalement[]
     */
    public function findByStatuts(array $statuts, int $limit = 200): array
    {
        $qb = $this->createQueryBuilder('s')
            ->join('s.orphelinat', 'o')
            ->addSelect('o')
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($statuts) {
            $qb->andWhere('s.statut IN (:statuts)')->setParameter('statuts', $statuts);
        }

        return $qb->getQuery()->getResult();
    }
}
