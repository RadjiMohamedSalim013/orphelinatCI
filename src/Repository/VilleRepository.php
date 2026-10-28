<?php

namespace App\Repository;

use App\Entity\Ville;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ville>
 */
class VilleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ville::class);
    }

    /**
     * @return Ville[]
     */
    public function findByPaysId(?int $paysId): array
    {
        if (null === $paysId) {
            return [];
        }

        return $this->createQueryBuilder('v')
            ->andWhere('IDENTITY(v.pays) = :pays')
            ->setParameter('pays', $paysId)
            ->orderBy('v.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
