<?php

namespace App\Repository;

use App\Entity\Notification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function countUnread(object $user): int
    {
        return $this->createQueryBuilder('n')
            ->select('count(n.id)')
            ->where('n.user = :user')
            ->andWhere('n.isRead = :isRead')
            ->setParameter('user', $user)
            ->setParameter('isRead', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findLatest(object $user, int $limit = 5): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.user = :user')
            ->orderBy('n.createdAt', 'DESC')
            ->setParameter('user', $user)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
