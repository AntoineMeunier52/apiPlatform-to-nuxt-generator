<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BodyMeasurement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<BodyMeasurement> */
class BodyMeasurementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BodyMeasurement::class);
    }
}
