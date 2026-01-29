<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ErrorController extends AbstractController
{
    /**
     * Gère les erreurs 403 (Access Denied)
     */
    public function accessDenied(): Response
    {
        // Si l'utilisateur n'est pas connecté, rediriger vers la page de connexion
        if (!$this->getUser()) {
            $this->addFlash('warning', 'Vous devez être connecté pour accéder à cette page.');
            return $this->redirectToRoute('app_login');
        }

        // Récupérer les rôles de l'utilisateur
        $userRoles = $this->getUser()->getRoles();
        
        // Préparer les informations sur les permissions
        $permissions = [];
        
        if (in_array('ROLE_SUPER_ADMIN', $userRoles)) {
            $permissions[] = 'Super Administrateur - Accès complet à toutes les fonctionnalités';
        } elseif (in_array('ROLE_ADMIN', $userRoles)) {
            $permissions[] = 'Administrateur - Accès au panneau d\'administration';
        } elseif (in_array('ROLE_MANAGER', $userRoles)) {
            $permissions[] = 'Manager - Gestion des équipes et supervision';
        } elseif (in_array('ROLE_SUPERVISOR', $userRoles)) {
            $permissions[] = 'Superviseur - Supervision des opérations';
        } elseif (in_array('ROLE_COMPTABLE', $userRoles)) {
            $permissions[] = 'Comptable - Gestion comptable et financière';
        } elseif (in_array('ROLE_CAISSIER', $userRoles)) {
            $permissions[] = 'Caissier - Gestion des transactions';
        } else {
            $permissions[] = 'Utilisateur - Accès au tableau de bord personnel';
        }

        return $this->render('bundles/TwigBundle/Exception/error403.html.twig', [
            'permissions' => $permissions,
            'requiredRole' => 'ROLE_SUPER_ADMIN', // Peut être personnalisé selon la route
        ]);
    }

    /**
     * Gère les erreurs 404 (Page non trouvée)
     */
    public function notFound(): Response
    {
        return $this->render('bundles/TwigBundle/Exception/error404.html.twig');
    }

    /**
     * Gère les erreurs 500 (Erreur serveur)
     */
    public function serverError(): Response
    {
        return $this->render('bundles/TwigBundle/Exception/error500.html.twig');
    }

    /**
     * Gère toutes les exceptions (méthode principale)
     */
    public function show(\Throwable $exception): Response
    {
        if ($exception instanceof AccessDeniedException) {
            return $this->accessDenied();
        }
        
        if ($exception instanceof NotFoundHttpException) {
            return $this->notFound();
        }
        
        // Pour les autres erreurs serveur
        return $this->serverError();
    }
}
