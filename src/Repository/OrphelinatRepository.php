<?php

namespace App\Repository;

use App\Entity\Orphelinat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
     * @return array{orphelinats: Orphelinat[], totalCount: int, totalPages: int, currentPage: int}
     */
    public function findByFilters(array $filters, int $page = 1, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.quartier', 'q')
            ->leftJoin('q.commune', 'c')
            ->leftJoin('c.ville', 'v')
            ->leftJoin('v.pays', 'p')
            ->addSelect('q, c, v, p')
            ->orderBy('o.nom', 'ASC');

        $search = trim($filters['search'] ?? '');
        if ($search) {
            $qb->andWhere(
                $qb->expr()->orX(
                    'o.nom LIKE :search',
                    'o.description LIKE :search',
                    'o.adresse LIKE :search'
                )
            )->setParameter('search', '%' . $search . '%');
        }

        if ($filters['pays_id'] ?? false) {
            $qb->andWhere('p.id = :pays_id')->setParameter('pays_id', $filters['pays_id']);
        }
        if ($filters['ville_id'] ?? false) {
            $qb->andWhere('v.id = :ville_id')->setParameter('ville_id', $filters['ville_id']);
        }
        if ($filters['commune_id'] ?? false) {
            $qb->andWhere('c.id = :commune_id')->setParameter('commune_id', $filters['commune_id']);
        }
        if ($filters['quartier_id'] ?? false) {
            $qb->andWhere('q.id = :quartier_id')->setParameter('quartier_id', $filters['quartier_id']);
        }

        $totalQb = clone $qb;
        $totalQb->select('COUNT(o.id)');
        $totalCount = $totalQb->getQuery()->getSingleScalarResult();

        $qb->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit);

        $orphelinats = $qb->getQuery()->getResult();

        $totalPages = ceil($totalCount / $limit);

        return [
            'orphelinats' => $orphelinats,
            'totalCount' => $totalCount,
            'totalPages' => $totalPages,
            'currentPage' => $page,
        ];
    }

    //    /**
    //     * @return Orphelinat[] Returns an array of Orphelinat objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('o.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Orphelinat
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
