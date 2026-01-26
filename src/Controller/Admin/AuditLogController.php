<?php

namespace App\Controller\Admin;

use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/audit')]
#[IsGranted('ROLE_ADMIN')]
class AuditLogController extends AbstractController
{
    #[Route('/logs', name: 'admin_audit_logs')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $logs = $entityManager->getRepository(ActivityLog::class)
            ->createQueryBuilder('l')
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/pages/audit/logs.html.twig', [
            'logs' => $logs,
        ]);
    }

    #[Route('/log/{id}', name: 'admin_audit_log_detail')]
    public function show(ActivityLog $log): Response
    {
        return $this->render('admin/pages/audit/log_detail.html.twig', [
            'log' => $log,
        ]);
    }
}
