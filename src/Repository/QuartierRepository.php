<?php

namespace App\Repository;

use App\Entity\Quartier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Quartier>
 */
class QuartierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Quartier::class);
    }

    /**
     * @return Quartier[]
     */
    public function findByCommuneId(?int $communeId): array
    {
        if (null === $communeId) {
            return [];
        }

        return $this->createQueryBuilder('q')
            ->andWhere('IDENTITY(q.commune) = :commune')
            ->setParameter('commune', $communeId)
            ->orderBy('q.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
