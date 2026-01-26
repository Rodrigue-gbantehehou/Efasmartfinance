<?php

namespace App\Service;

use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class ActivityLogger
{
    public function __construct(
        private EntityManagerInterface $em,
        private RequestStack $requestStack
    ) {}

    public function log(
        ?object $user,
        string $action,
        string $entityType,
        ?int $entityId,
        string $description
    ): void {
        $request = $this->requestStack->getCurrentRequest();

        $log = new ActivityLog();
        $log->setUtilisateur($user);
        $log->setActions($action);
        $log->setEntityType($entityType);
        $log->setEntityId($entityId);
        $log->setDescription($description);
        $log->setIpAdress($request?->getClientIp());
        $log->setUserAgent($request?->headers->get('User-Agent'));
        $log->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($log);
        $this->em->flush();
    }
}
