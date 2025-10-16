<?php

namespace App\Repository;

use App\Entity\EduNonWorkingDay;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EduNonWorkingDay>
 */
class EduNonWorkingDayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EduNonWorkingDay::class);
    }

    public function findBetweenDates(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->createQueryBuilder('nwd')
            ->andWhere('nwd.date >= :start')
            ->andWhere('nwd.date <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('nwd.date', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
