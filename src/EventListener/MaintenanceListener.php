<?php

namespace App\EventListener;

use App\Repository\SystemSettingsRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Environment;

class MaintenanceListener
{
    public function __construct(
        private SystemSettingsRepository $settingsRepo,
        private Security $security,
        private Environment $twig
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        
        // Ne pas bloquer les routes admin, profiler, assets
        $path = $request->getPathInfo();
        if (
            str_starts_with($path, '/admin') ||
            str_starts_with($path, '/pin') ||
            str_starts_with($path, '/login') ||
            str_starts_with($path, '/logout') ||
            str_starts_with($path, '/_profiler') ||
            str_starts_with($path, '/_wdt') ||
            str_starts_with($path, '/assets') ||
            str_starts_with($path, '/build')
        ) {
            return;
        }

        // Vérifier si le mode maintenance est activé
        $settings = $this->settingsRepo->findOneBy(['settingKey' => 'maintenance']);
        
        if (!$settings || !$settings->isMaintenanceMode()) {
            return;
        }

        // Autoriser les admins à accéder
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        // Afficher la page de maintenance
        $response = new Response(
            $this->twig->render('maintenance.html.twig', [
                'message' => $settings->getMaintenanceMessage() ?? 'Le site est actuellement en maintenance. Nous serons de retour bientôt.',
                'startedAt' => $settings->getMaintenanceStartedAt(),
            ]),
            Response::HTTP_SERVICE_UNAVAILABLE
        );

        $event->setResponse($response);
    }
}
