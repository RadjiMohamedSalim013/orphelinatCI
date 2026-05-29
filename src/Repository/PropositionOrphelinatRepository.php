<?php

namespace App\Repository;

use App\Entity\PropositionOrphelinat;
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
     * @return PropositionOrphelinat[]
     */
    public function findPending(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.statut = :statut')
            ->setParameter('statut', 'en_attente')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult()

        ;
    }

    //    public function findOneBySomeField($value): ?PropositionOrphelinat
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
