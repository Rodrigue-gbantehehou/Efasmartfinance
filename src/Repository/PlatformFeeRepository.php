<?php

namespace App\Repository;

use App\Entity\PlatformFee;
use App\Entity\Tontine;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use DateTimeInterface;

/**
 * @extends ServiceEntityRepository<PlatformFee>
 */
class PlatformFeeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlatformFee::class);
    }

    public function getDailySummary(\DateTimeInterface $date): array
    {
        $start = \DateTimeImmutable::createFromInterface($date)->setTime(0, 0, 0);
        $end = \DateTimeImmutable::createFromInterface($date)->setTime(23, 59, 59);

        return $this->createQueryBuilder('f')
            ->select('f.type, SUM(f.amount) as total')
            ->where('f.createdAt BETWEEN :start AND :end')
            ->andWhere('f.status = :status')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('status', 'collected')
            ->groupBy('f.type')
            ->getQuery()
            ->getResult();
    }

    public function getMonthlySummary(int $year, int $month): array
    {
        $start = new \DateTimeImmutable(sprintf('%d-%02d-01 00:00:00', $year, $month));
        $end = $start->modify('last day of this month 23:59:59');

        return $this->createQueryBuilder('f')
            ->select('f.type, SUM(f.amount) as total')
            ->where('f.createdAt BETWEEN :start AND :end')
            ->andWhere('f.status = :status')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('status', 'collected')
            ->groupBy('f.type')
            ->getQuery()
            ->getResult();
    }

    public function getAnnualSummary(int $year): array
    {
        $start = new \DateTimeImmutable(sprintf('%d-01-01 00:00:00', $year));
        $end = new \DateTimeImmutable(sprintf('%d-12-31 23:59:59', $year));

        return $this->createQueryBuilder('f')
            ->select('f.type, SUM(f.amount) as total')
            ->where('f.createdAt BETWEEN :start AND :end')
            ->andWhere('f.status = :status')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('status', 'collected')
            ->groupBy('f.type')
            ->getQuery()
            ->getResult();
    }

    public function getFeesByTontine(Tontine $tontine): array
    {
        return $this->createQueryBuilder('f')
            ->select('f.type, SUM(f.amount) as total')
            ->where('f.tontine = :tontine')
            ->andWhere('f.status = :status')
            ->setParameter('tontine', $tontine)
            ->setParameter('status', 'collected')
            ->groupBy('f.type')
            ->getQuery()
            ->getResult();
    }

    public function getFeesByUser(User $user): array
    {
        return $this->createQueryBuilder('f')
            ->select('f.type, SUM(f.amount) as total')
            ->where('f.user = :user')
            ->andWhere('f.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'collected')
            ->groupBy('f.type')
            ->getQuery()
            ->getResult();
    }
}
