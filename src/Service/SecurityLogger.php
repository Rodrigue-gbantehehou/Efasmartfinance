<?php 


namespace App\Service;

use App\Entity\SecurityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Service\ActivityLogger; // Added the missing use statement

class SecurityLogger
{
    public function __construct(
        private EntityManagerInterface $em,
        private RequestStack $requestStack,
        private ActivityLogger $activityLogger
    ) {}

    public function log(
        ?object $user,
        string $action,
        string $entityType,
        ?int $entityId,
        string $description
    ): void {
        $request = $this->requestStack->getCurrentRequest();

        $log = new SecurityLog();
        $log->setUtilisateur($user);
        $log->setEvent($action);
        $log->setIpAdress($request?->getClientIp());
        $log->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($log);
        $this->em->flush();
        
        // Log également dans l'ActivityLogger pour une vue unifiée
        $this->activityLogger->log(
            $user,
            'SECURITY_' . $action,
            $entityType,
            $entityId,
            'Sécurité: ' . $description
        );
    }
}