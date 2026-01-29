<?php

namespace App\Repository;

use App\Entity\TwoFactorAuth;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TwoFactorAuth>
 */
class TwoFactorAuthRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TwoFactorAuth::class);
    }

    public function save(TwoFactorAuth $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TwoFactorAuth $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find TwoFactorAuth by user
     */
    public function findByUser(User $user): ?TwoFactorAuth
    {
        return $this->findOneBy(['user' => $user]);
    }

    /**
     * Find enabled TwoFactorAuth by user
     */
    public function findEnabledByUser(User $user): ?TwoFactorAuth
    {
        return $this->findOneBy(['user' => $user, 'isEnabled' => true]);
    }

    /**
     * Check if user has 2FA enabled
     */
    public function isUserEnabled(User $user): bool
    {
        return $this->count(['user' => $user, 'isEnabled' => true]) > 0;
    }

    /**
     * Get all users with 2FA enabled
     */
    public function findAllEnabled(): array
    {
        return $this->findBy(['isEnabled' => true]);
    }

    /**
     * Get statistics about 2FA usage
     */
    public function getStatistics(): array
    {
        $total = $this->count([]);
        $enabled = $this->count(['isEnabled' => true]);
        $totp = $this->count(['isEnabled' => true, 'method' => 'totp']);

        return [
            'total_users' => $total,
            'enabled_users' => $enabled,
            'totp_users' => $totp,
            'adoption_rate' => $total > 0 ? round(($enabled / $total) * 100, 2) : 0,
        ];
    }
}
