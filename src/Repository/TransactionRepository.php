<?php

namespace App\Repository;

use App\Entity\Transaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    public function countTodayTransactions(): int
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);

        $tomorrow = clone $today;
        $tomorrow->modify('+1 day');

        return $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.createdAt >= :today')
            ->andWhere('t.createdAt < :tomorrow')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getTotalRevenue(): float
    {
        return $this->createQueryBuilder('t')
            ->select('SUM(t.amount)')
            ->where('t.statut = :statut')
            ->setParameter('statut', 'completed')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
    }
    
    /**
     * Récupère le montant total des transactions pour une date donnée
     */
    public function getRevenueForDate(\DateTimeInterface $date): float
    {
        // Créer un nouvel objet DateTime pour éviter les problèmes de typage
        $startOfDay = new \DateTime($date->format('Y-m-d 00:00:00'));
        $endOfDay = new \DateTime($date->format('Y-m-d 23:59:59'));
        
        $result = $this->createQueryBuilder('t')
            ->select('SUM(t.amount)')
            ->where('t.createdAt >= :startDate')
            ->andWhere('t.createdAt < :endDate')
            ->andWhere('t.statut = :statut')
            ->setParameter('startDate', $startOfDay)
            ->setParameter('endDate', $endOfDay)
            ->setParameter('statut', 'completed')
            ->getQuery()
            ->getSingleScalarResult();
            
        return $result !== null ? (float) $result : 0.0;
    }
    
    public function getAverageTransactionAmount(): float
    {
        return (float) $this->createQueryBuilder('t')
            ->select('AVG(ABS(t.amount))')
            ->where('t.statut = :statut')
            ->setParameter('statut', 'completed')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
    }

    public function getTotalVolume(): float
    {
        return (float) $this->createQueryBuilder('t')
            ->select('SUM(ABS(t.amount))')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
    }

    public function getCurrentMonthRevenue(): float
    {
        $startDate = new \DateTime('first day of this month');
        $endDate = new \DateTime('last day of this month');

        return $this->createQueryBuilder('t')
            ->select('SUM(t.amount)')
            ->where('t.createdAt >= :startDate')
            ->andWhere('t.createdAt <= :endDate')
            ->andWhere('t.statut = :statut')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('statut', 'completed')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
    }

    public function getLastMonthRevenue(): float
    {
        $startDate = new \DateTime('first day of last month');
        $endDate = new \DateTime('last day of last month');

        return $this->createQueryBuilder('t')
            ->select('SUM(t.amount)')
            ->where('t.createdAt >= :startDate')
            ->andWhere('t.createdAt <= :endDate')
            ->andWhere('t.statut = :statut')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('statut', 'completed')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
    }

    public function getMonthlyVolume(): float
    {
        $startDate = new \DateTime('first day of this month');
        $endDate = new \DateTime('last day of this month 23:59:59');

        return (float) $this->createQueryBuilder('t')
            ->select('SUM(ABS(t.amount))')
            ->where('t.createdAt BETWEEN :startDate AND :endDate')
            ->andWhere('t.statut = :statut')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('statut', 'completed')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
    }

    /**
     * Récupère le volume des transactions par jour pour une période donnée
     *
     * @param \DateTime $startDate Date de début
     * @param \DateTime $endDate Date de fin
     * @return array
     */
    public function getMonthlyRevenue(\DateTime $startDate, \DateTime $endDate): array
    {
        // Format the dates to get the first day of each month
        $startDate = new \DateTime($startDate->format('Y-m-01 00:00:00'));
        $endDate = new \DateTime($endDate->format('Y-m-t 23:59:59'));
        
        // Use a raw SQL query with MySQL functions
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = 'SELECT 
                    YEAR(t.created_at) as year,
                    MONTH(t.created_at) as month,
                    SUM(t.amount) as revenue
                FROM `transaction` t
                WHERE t.created_at BETWEEN :startDate AND :endDate
                AND t.statut = :statut
                GROUP BY YEAR(t.created_at), MONTH(t.created_at)
                ORDER BY year ASC, month ASC';
                
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'startDate' => $startDate->format('Y-m-d H:i:s'),
            'endDate' => $endDate->format('Y-m-d H:i:s'),
            'statut' => 'completed'
        ]);
        
        $result = $result->fetchAllAssociative();

        // Format the result into a more usable array
        $formattedResult = [];
        foreach ($result as $row) {
            $date = new \DateTime(sprintf('%d-%02d-01', $row['year'], $row['month']));
            $formattedResult[$date->format('Y-m')] = (float) ($row['revenue'] ?? 0);
        }

        // Fill in missing months with 0
        $period = new \DatePeriod(
            $startDate,
            new \DateInterval('P1M'),
            $endDate
        );

        $completeResult = [];
        foreach ($period as $date) {
            $key = $date->format('Y-m');
            $completeResult[$key] = $formattedResult[$key] ?? 0.0;
        }

        return $completeResult;
    }

    public function getDailyVolume(\DateTime $startDate, \DateTime $endDate): array
    {
        // Créer un tableau de toutes les dates dans l'intervalle
        $period = new \DatePeriod(
            $startDate,
            new \DateInterval('P1D'),
            $endDate->modify('+1 day')
        );

        // Initialiser le tableau de résultats avec des valeurs à zéro
        $results = [];
        foreach ($period as $date) {
            $results[$date->format('Y-m-d')] = [
                'date' => $date->format('Y-m-d'),
                'amount' => 0,
                'count' => 0
            ];
        }

        // Récupérer les données de la base de données
        $query = $this->getEntityManager()->createQuery(
            'SELECT '.
            'SUBSTRING(t.createdAt, 1, 10) as date, '.
            'SUM(ABS(t.amount)) as amount, '.
            'COUNT(t.id) as count '.
            'FROM App\Entity\Transaction t '.
            'WHERE t.createdAt >= :startDate AND t.createdAt <= :endDate '.
            'GROUP BY date '.
            'ORDER BY date ASC'
        )
        ->setParameter('startDate', $startDate->format('Y-m-d 00:00:00'))
        ->setParameter('endDate', $endDate->format('Y-m-d 23:59:59'));

        $queryResults = $query->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);

        // Fusionner les résultats avec les données de la base de données
        foreach ($queryResults as $row) {
            if (isset($results[$row['date']])) {
                $results[$row['date']]['amount'] = (float) $row['amount'];
                $results[$row['date']]['count'] = (int) $row['count'];
            }
        }

        return array_values($results);
    }


    //    /**
    //     * @return Transaction[] Returns an array of Transaction objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('t.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Transaction
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
