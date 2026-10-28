<?php

namespace App\Repository;

use App\Entity\Pays;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Pays>
 */
class PaysRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pays::class);
    }

    /**
     * @return Pays[]
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['nom' => 'ASC']);
    }

    /**
     * Référentiel complet pays > villes > communes > quartiers en une requête.
     *
     * @return Pays[]
     */
    public function findArborescence(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.villes', 'v')
            ->leftJoin('v.communes', 'c')
            ->leftJoin('c.quartiers', 'q')
            ->addSelect('v', 'c', 'q')
            ->orderBy('p.nom', 'ASC')
            ->addOrderBy('v.nom', 'ASC')
            ->addOrderBy('c.nom', 'ASC')
            ->addOrderBy('q.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
