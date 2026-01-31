<?php

namespace App\Repository;

use App\Entity\PinAuth;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PinAuth>
 */
class PinAuthRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PinAuth::class);
    }

    public function save(PinAuth $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PinAuth $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find PinAuth by user
     */
    public function findByUser(User $user): ?PinAuth
    {
        return $this->findOneBy(['user' => $user]);
    }

    /**
     * Find enabled PinAuth by user
     */
    public function findEnabledByUser(User $user): ?PinAuth
    {
        return $this->findOneBy(['user' => $user, 'isEnabled' => true]);
    }

    /**
     * Find users with locked PIN accounts
     */
    public function findLockedAccounts(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.lockedUntil IS NOT NULL')
            ->andWhere('p.lockedUntil > :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /**
     * Find users who must change their PIN
     */
    public function findUsersWhoMustChangePin(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.mustChangePin = :mustChange')
            ->setParameter('mustChange', true)
            ->getQuery()
            ->getResult();
    }
}
