<?php

namespace App\Repository;

use App\Entity\Withdrawals;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Withdrawals>
 */
class WithdrawalsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Withdrawals::class);
    }

    public function countPending(): int
    {
        return $this->count(['statut' => 'pending']);
    }

    public function getTotalApprovedAmount(): float
    {
        return (float) $this->createQueryBuilder('w')
            ->select('SUM(w.amount)')
            ->where('w.statut = :status')
            ->setParameter('status', 'approved')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getPendingAmount(): float
    {
        return (float) $this->createQueryBuilder('w')
            ->select('SUM(w.amount)')
            ->where('w.statut = :status')
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getSingleScalarResult();
    }

    //    /**
    //     * @return Withdrawals[] Returns an array of Withdrawals objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('w')
    //            ->andWhere('w.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('w.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Withdrawals
    //    {
    //        return $this->createQueryBuilder('w')
    //            ->andWhere('w.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
