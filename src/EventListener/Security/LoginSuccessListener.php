<?php

namespace App\EventListener\Security;

use App\Service\SecurityLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class LoginSuccessListener implements EventSubscriberInterface
{
    public function __construct(
        private SecurityLogger $securityLogger
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        
        if ($user instanceof \App\Entity\User) {
            $this->securityLogger->log(
                $user,
                'LOGIN_SUCCESS',
                'USER',
                $user->getUuid(),
                'Connexion réussie de l\'utilisateur'
            );
        }
    }
}
