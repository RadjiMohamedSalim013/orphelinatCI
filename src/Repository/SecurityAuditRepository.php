<?php

namespace App\Repository;

use App\Entity\SecurityAudit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SecurityAuditRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SecurityAudit::class);
    }

    public function findByType(string $type, int $limit = 50): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.type = :type')
            ->setParameter('type', $type)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findBySeverity(string $severity, int $limit = 50): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.severity = :severity')
            ->setParameter('severity', $severity)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findUnresolvedBySeverity(string $severity): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.resolved = :resolved')
            ->andWhere('s.severity = :severity')
            ->setParameter('resolved', false)
            ->setParameter('severity', $severity)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countUnresolved(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.resolved = :resolved')
            ->setParameter('resolved', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findRecent(int $days = 7, int $limit = 100): array
    {
        $date = new \DateTimeImmutable("-{$days} days");

        return $this->createQueryBuilder('s')
            ->andWhere('s.createdAt >= :date')
            ->setParameter('date', $date)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getStatistics(int $days = 30): array
    {
        $date = new \DateTimeImmutable("-{$days} days");

        $qb = $this->createQueryBuilder('s')
            ->select('s.severity, COUNT(s.id) as count')
            ->andWhere('s.createdAt >= :date')
            ->setParameter('date', $date)
            ->groupBy('s.severity');

        $results = $qb->getQuery()->getResult();

        $stats = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'total' => 0,
        ];

        foreach ($results as $row) {
            $stats[$row['severity']] = (int) $row['count'];
            $stats['total'] += (int) $row['count'];
        }

        return $stats;
    }
}