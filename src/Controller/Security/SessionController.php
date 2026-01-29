<?php

namespace App\Controller\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class SessionController extends AbstractController
{
    
    #[Route('/check-session', name: 'app_check_session', methods: ['GET'])]
    public function checkSession(Request $request, SessionInterface $session): JsonResponse
    {
        $lastActivity = $session->get('last_activity');
        $maxInactiveTime = 1800; // 30 minutes en secondes
        
        // Mettre à jour l'activité à chaque vérification
        $session->set('last_activity', time());
        
        // Si c'est une requête AJAX de vérification, vérifier l'inactivité
        if ($request->isXmlHttpRequest()) {
            $inactiveTime = $lastActivity ? time() - $lastActivity : 0;
            
            if ($inactiveTime > $maxInactiveTime) {
                $session->invalidate();
                return $this->json(['valid' => false]);
            }
            
            return $this->json(['valid' => true]);
        }
        
        return $this->json(['valid' => true]);
    }
    
    // Supprimez ou commentez la route /update-activity si elle n'est plus utilisée
    /*
    #[Route('/update-activity', name: 'app_update_activity', methods: ['POST'])]
    public function updateActivity(SessionInterface $session): JsonResponse
    {
        $session->set('last_activity', time());
        return $this->json(['success' => true]);
    }
    */
}