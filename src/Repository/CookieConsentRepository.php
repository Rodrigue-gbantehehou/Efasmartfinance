<?php

namespace App\Repository;

use App\Entity\CookieConsent;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CookieConsent>
 *
 * @method CookieConsent|null find($id, $lockMode = null, $lockVersion = null)
 * @method CookieConsent|null findOneBy(array $criteria, array $orderBy = null)
 * @method CookieConsent[]    findAll()
 * @method CookieConsent[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CookieConsentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CookieConsent::class);
    }

    public function findLatestConsentByUser(User $user): ?CookieConsent
    {
        return $this->createQueryBuilder('cc')
            ->andWhere('cc.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('cc.consentDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function hasGivenConsent(User $user): bool
    {
        return $this->count(['utilisateur' => $user]) > 0;
    }

    public function save(CookieConsent $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CookieConsent $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
