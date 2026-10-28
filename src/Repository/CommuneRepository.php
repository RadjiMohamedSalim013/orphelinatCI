<?php

namespace App\Repository;

use App\Entity\Commune;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commune>
 */
class CommuneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commune::class);
    }

    /**
     * @return Commune[]
     */
    public function findByVilleId(?int $villeId): array
    {
        if (null === $villeId) {
            return [];
        }

        return $this->createQueryBuilder('c')
            ->andWhere('IDENTITY(c.ville) = :ville')
            ->setParameter('ville', $villeId)
            ->orderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Toutes les communes avec ville et pays, triées pour les listes déroulantes. */
    public function createOrderedQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('c')
            ->join('c.ville', 'v')
            ->join('v.pays', 'p')
            ->addSelect('v', 'p')
            ->orderBy('p.nom', 'ASC')
            ->addOrderBy('v.nom', 'ASC')
            ->addOrderBy('c.nom', 'ASC');
    }
}
