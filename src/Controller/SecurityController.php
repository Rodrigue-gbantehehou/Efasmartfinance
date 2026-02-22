<?php

namespace App\Controller;

use App\Service\SecurityLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    public function __construct(
        private SecurityLogger $securityLogger
    ) {}

    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // if ($this->getUser()) {
        //     return $this->redirectToRoute('target_path');
        // }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        // Log login attempt
        if ($error) {
            $this->securityLogger->log(
                null,
                'LOGIN_FAILED',
                'USER',
                null,
                sprintf('Tentative de connexion échouée pour l\'utilisateur: %s', $lastUsername)
            );
        }

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        $user = $this->getUser();
        if ($user instanceof \App\Entity\User) {
            $this->securityLogger->log(
                $user,
                'USER_LOGOUT',
                'USER',
                $user->getUuid(),
                'Déconnexion de l\'utilisateur'
            );
        }
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route(path: '/access-denied', name: 'app_access_denied')]
    public function accessDenied(): Response
    {
        return $this->render('bundles/TwigBundle/Exception/error403.html.twig');
    }
}
