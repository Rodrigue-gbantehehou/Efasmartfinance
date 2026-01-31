<?php

namespace App\Controller\Admin;

use App\Entity\SystemSettings;
use App\Repository\SystemSettingsRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/maintenance')]
#[IsGranted('ROLE_SUPPORT')]
class MaintenanceController extends AbstractController
{
    public function __construct(
        private SystemSettingsRepository $settingsRepo,
        private EntityManagerInterface $em,
        private ActivityLogger $activityLogger
    ) {}

    #[Route('', name: 'admin_maintenance')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('VIEW_MODULE', 'maintenance');
        $settings = $this->settingsRepo->findOneBy(['settingKey' => 'maintenance']);
        
        if (!$settings) {
            $settings = new SystemSettings();
            $settings->setSettingKey('maintenance');
            $this->em->persist($settings);
            $this->em->flush();
        }

        return $this->render('admin/pages/maintenance/index.html.twig', [
            'settings' => $settings,
        ]);
    }

    #[Route('/toggle', name: 'admin_maintenance_toggle', methods: ['POST'])]
    public function toggle(Request $request): Response
    {
        $this->denyAccessUnlessGranted('EDIT_MODULE', 'maintenance');
        if (!$this->isCsrfTokenValid('toggle_maintenance', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_maintenance');
        }

        $settings = $this->settingsRepo->findOneBy(['settingKey' => 'maintenance']);
        
        if (!$settings) {
            $settings = new SystemSettings();
            $settings->setSettingKey('maintenance');
            $this->em->persist($settings);
        }

        $newStatus = !$settings->isMaintenanceMode();
        $settings->setMaintenanceMode($newStatus);
        
        $message = $request->request->get('message');
        if ($message) {
            $settings->setMaintenanceMessage($message);
        }

        $this->em->flush();

        $this->activityLogger->log(
            $this->getUser(),
            'MAINTENANCE_TOGGLED',
            'SystemSettings',
            $settings->getId(),
            'Mode maintenance ' . ($newStatus ? 'activé' : 'désactivé')
        );

        $this->addFlash(
            'success',
            $newStatus ? '🔧 Mode maintenance activé' : '✅ Mode maintenance désactivé'
        );

        return $this->redirectToRoute('admin_maintenance');
    }

    #[Route('/update-message', name: 'admin_maintenance_update_message', methods: ['POST'])]
    public function updateMessage(Request $request): Response
    {
        $this->denyAccessUnlessGranted('EDIT_MODULE', 'maintenance');
        if (!$this->isCsrfTokenValid('update_message', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_maintenance');
        }

        $settings = $this->settingsRepo->findOneBy(['settingKey' => 'maintenance']);
        
        if (!$settings) {
            $this->addFlash('error', 'Configuration introuvable.');
            return $this->redirectToRoute('admin_maintenance');
        }

        $message = $request->request->get('message');
        $settings->setMaintenanceMessage($message);
        $this->em->flush();

        $this->addFlash('success', 'Message de maintenance mis à jour.');
        return $this->redirectToRoute('admin_maintenance');
    }
}
