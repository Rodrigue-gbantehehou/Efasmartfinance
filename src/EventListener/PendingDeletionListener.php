<?php

namespace App\EventListener;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

class PendingDeletionListener implements EventSubscriberInterface
{
    public function __construct(
        private Security $security,
        private RouterInterface $router
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 5]],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Check if the request is for the dashboard
        if (!str_starts_with($path, '/dashboard') && !str_starts_with($path, '/admin')) {
            return;
        }

        // Exclude the restore page and logout to avoid infinite loops and allow recovery
        if ($path === $this->router->generate('app_settings_restore') || 
            $path === $this->router->generate('app_logout')) {
            return;
        }

        /** @var User|null $user */
        $user = $this->security->getUser();

        if ($user instanceof User) {
            // Force logout for deleted or suspended accounts
            if ($user->isDeleted() || !$user->isActive()) {
                $request->getSession()->getFlashBag()->add('error', 'Votre compte n\'est plus actif ou a été supprimé.');
                $event->setResponse(new RedirectResponse($this->router->generate('app_logout')));
                return;
            }

            // Redirect to restoration page for accounts pending deletion
            if ($user->isPendingDeletion()) {
                $event->setResponse(new RedirectResponse($this->router->generate('app_settings_restore')));
            }
        }
    }
}
