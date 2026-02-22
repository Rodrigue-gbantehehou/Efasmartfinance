<?php

namespace App\Repository;

use App\Entity\AdminPermission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdminPermission>
 */
class AdminPermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminPermission::class);
    }

    /**
     * @return AdminPermission[]
     */
    public function findByRole(string $role): array
    {
        return $this->findBy(['role' => $role]);
    }

    public function hasPermission(array $roles, string $module, string $permission): bool
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.role IN (:roles)')
            ->andWhere('p.module = :module')
            ->andWhere('p.permission = :permission')
            ->setParameter('roles', $roles)
            ->setParameter('module', strtolower($module))
            ->setParameter('permission', $permission);

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
