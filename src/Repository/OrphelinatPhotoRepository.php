<?php

namespace App\Repository;

use App\Entity\OrphelinatPhoto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrphelinatPhoto>
 */
class OrphelinatPhotoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrphelinatPhoto::class);
    }
}
