<?php

namespace App\Controller;

use App\Service\TwoFactorAuthService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class TwoFactorVerificationController extends AbstractController
{
    public function __construct(
        private TwoFactorAuthService $twoFactorAuthService,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/2fa/verification', name: 'app_2fa_verification')]
    public function verification(Request $request): Response
    {
        $userId = $request->getSession()->get('2fa_user');
        
        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        $user = $this->entityManager->getRepository(\App\Entity\User::class)->find($userId);
        
        if (!$user) {
            $request->getSession()->remove('2fa_user');
            return $this->redirectToRoute('app_login');
        }

        // Si l'utilisateur est déjà authentifié complètement
        if ($this->getUser()) {
            return $this->redirectToRoute('app_tontines_index');
        }

        return $this->render('security/2fa_verification.html.twig', [
            'user' => $user,
            'error' => $request->getSession()->get('2fa_error'),
        ]);
    }

    #[Route('/2fa/verify', name: 'app_2fa_verify', methods: ['POST'])]
    public function verify(Request $request): JsonResponse
    {
        $userId = $request->getSession()->get('2fa_user');
        
        if (!$userId) {
            return new JsonResponse(['success' => false, 'message' => 'Session expirée']);
        }

        $user = $this->entityManager->getRepository(\App\Entity\User::class)->find($userId);
        
        if (!$user) {
            $request->getSession()->remove('2fa_user');
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur non trouvé']);
        }

        $code = $request->request->get('code');

        if (!$code) {
            return new JsonResponse(['success' => false, 'message' => 'Code requis']);
        }

        // Vérifier le code TOTP
        if ($this->twoFactorAuthService->verifyTotpCode($user, $code)) {
            // Authentifier l'utilisateur manuellement
            $request->getSession()->remove('2fa_user');
            $request->getSession()->remove('2fa_error');
            
            // Créer manuellement le token d'authentification
            $token = new \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken(
                $user,
                'main',
                $user->getRoles()
            );
            
            $this->container->get('security.token_storage')->setToken($token);
            $request->getSession()->set('_security_main', serialize($token));

            return new JsonResponse(['success' => true, 'redirect' => $this->generateUrl('app_tontines_index')]);
        }

        // Vérifier les codes de sauvegarde
        if ($this->twoFactorAuthService->verifyBackupCode($user, $code)) {
            $request->getSession()->remove('2fa_user');
            $request->getSession()->remove('2fa_error');
            
            // Créer manuellement le token d'authentification
            $token = new \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken(
                $user,
                'main',
                $user->getRoles()
            );
            
            $this->container->get('security.token_storage')->setToken($token);
            $request->getSession()->set('_security_main', serialize($token));

            return new JsonResponse(['success' => true, 'redirect' => $this->generateUrl('app_tontines_index')]);
        }

        return new JsonResponse(['success' => false, 'message' => 'Code invalide']);
    }

    #[Route('/2fa/cancel', name: 'app_2fa_cancel')]
    public function cancel(Request $request): Response
    {
        $request->getSession()->remove('2fa_user');
        $request->getSession()->remove('2fa_error');
        
        return $this->redirectToRoute('app_login');
    }
}
