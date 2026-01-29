<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class AccessDeniedListener implements EventSubscriberInterface
{
    private $router;
    private $authorizationChecker;

    public function __construct(RouterInterface $router, AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->router = $router;
        $this->authorizationChecker = $authorizationChecker;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Gérer uniquement les exceptions AccessDeniedException
        if (!$exception instanceof AccessDeniedException) {
            return;
        }

        $request = $event->getRequest();
        
        // Ne pas gérer les requêtes AJAX ou API
        if ($request->isXmlHttpRequest()) {
            return;
        }

        // Si l'utilisateur n'est pas connecté, rediriger vers la page de connexion
        if (!$this->authorizationChecker->isGranted('IS_AUTHENTICATED')) {
            $response = new RedirectResponse($this->router->generate('app_login'));
            $event->setResponse($response);
            return;
        }

        // Pour les autres cas d'accès refusé, laisser le système gérer avec les pages d'erreur personnalisées
        // Le template error403.html.twig sera utilisé automatiquement
    }
}
