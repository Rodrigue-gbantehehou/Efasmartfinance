<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class PinSecuritySubscriber implements EventSubscriberInterface
{
    private const EXCLUDED_ROUTES = [
        'pin_verify',
        'pin_check',
        'pin_setup',
        'pin_reset_request',
        'pin_reset',
        'app_login',
        'app_logout',
        'app_register',
        'app_home',
        'pin_recovery',
        'api_cookie_consent_check',
        '_wdt',          // Web Debug Toolbar
        '_profiler'      // Profiler
    ];

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        // If no route or excluded route, do nothing
        if (!$route || in_array($route, self::EXCLUDED_ROUTES)) {
            return;
        }

        // For any other route, we check if the user is authenticated and verified
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }



        // Check if PIN is configured
        if (!$user->hasPinAuth()) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('pin_setup')));
            return;
        }

        // Check verification in session
        $session = $request->getSession();
        $pinVerified = $session->get('pin_verified', false);
        $pinVerifiedAt = $session->get('pin_verified_at', 0);
        
        // PIN verification expires after 30 minutes
        if (!$pinVerified || (time() - $pinVerifiedAt) > 1800) {
            // Save current URL as target path only for "navigable" GET requests
            // Ignore AJAX, JSON, and specific excluded API routes
            if ($request->isMethod('GET') && 
                !$request->isXmlHttpRequest() && 
                !str_contains($request->headers->get('Accept', ''), 'application/json') &&
                !str_starts_with($route, '_') && 
                !str_starts_with($route, 'api_') // Strict: No API route as target path
            ) {
                $session->set('_security.main.target_path', $request->getUri());
            }

            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('pin_verify')));
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 0]], // Priority 0 to run AFTER the firewall (priority 8)
        ];
    }
}
