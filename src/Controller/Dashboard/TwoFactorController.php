<?php

namespace App\Controller\Dashboard;

use App\Entity\SecuritySettings;
use App\Service\TwoFactorAuthService;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/2fa')]
#[IsGranted('ROLE_USER')]
class TwoFactorController extends AbstractController
{
    public function __construct(
        private TwoFactorAuthService $twoFactorAuthService,
        private EntityManagerInterface $entityManager,
        private EmailService $emailService
    ) {}

    #[Route('/', name: 'app_dashboard_2fa_index')]
    public function index(): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $twoFactorAuth = $user->getTwoFactorAuth();

        return $this->render('dashboard/two_factor/index.html.twig', [
            'twoFactorAuth' => $twoFactorAuth,
            'isTwoFactorEnabled' => $this->twoFactorAuthService->isTwoFactorEnabled($user),
            'backupCodes' => $user->getBackupCodes(),
        ]);
    }

    #[Route('/reset-and-enable', name: 'app_dashboard_2fa_reset_enable')]
    public function resetAndEnable(): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        
        // Supprimer l'ancien TwoFactorAuth s'il existe
        $existingTwoFactor = $user->getTwoFactorAuth();
        if ($existingTwoFactor) {
            $this->entityManager->remove($existingTwoFactor);
            $this->entityManager->flush();
        }
        
        // Créer un nouveau TwoFactorAuth avec un nouveau secret
        $twoFactorAuth = $this->twoFactorAuthService->enableTwoFactor($user);
        
        // Forcer le flush pour s'assurer que tout est sauvegardé
        $this->entityManager->flush();
        
        return $this->render('dashboard/two_factor/enable.html.twig', [
            'twoFactorAuth' => $twoFactorAuth,
        ]);
    }

    #[Route('/enable', name: 'app_dashboard_2fa_enable')]
    public function enable(): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        
        // Activer le 2FA (méthode e-mail par défaut)
        $twoFactorAuth = $this->twoFactorAuthService->enableTwoFactor($user);
        
        // Générer un code de vérification aléatoire pour l'activation
        $verificationCode = sprintf('%06d', random_int(0, 999999));
        $twoFactorAuth->setEmailCode($verificationCode);
        $this->entityManager->flush();
        
        // Envoyer le code par e-mail
        try {
            $this->emailService->sendAuthCode($user);
            $this->addFlash('success', 'Un code de vérification a été envoyé à votre adresse e-mail.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'envoi de l\'e-mail : ' . $e->getMessage());
        }

        return $this->render('dashboard/two_factor/enable.html.twig', [
            'twoFactorAuth' => $twoFactorAuth,
        ]);
    }

    #[Route('/qrcode/{secret}', name: 'app_dashboard_2fa_qrcode')]
    public function qrcode(string $secret): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        
        // Vérifier si l'utilisateur a un secret
        $userSecret = $user->getTotpSecret();
        if (!$userSecret) {
            throw new \Exception('Aucun secret TOTP trouvé pour cet utilisateur');
        }
        
        // Vérifier que le secret correspond
        if ($userSecret !== $secret) {
            throw new \Exception('Secret invalide');
        }

        try {
            return $this->twoFactorAuthService->generateQrCode($user, $secret);
        } catch (\Exception $e) {
            throw new \Exception('Erreur génération QR code: ' . $e->getMessage());
        }
    }

    #[Route('/confirm', name: 'app_dashboard_2fa_confirm', methods: ['POST'])]
    public function confirm(Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $code = $request->request->get('code');

        if (!$code) {
            $this->addFlash('error', 'Le code de vérification est requis.');
            return $this->redirectToRoute('app_dashboard_2fa_enable');
        }

        try {
            $result = $this->twoFactorAuthService->confirmTwoFactor($user, $code);
            
            if ($result) {
                // Succès : redirection vers la page de succès (codes de secours)
                return $this->redirectToRoute('app_dashboard_2fa_success');
            } else {
                // Échec : retour à la page d'activation avec erreur
                $this->addFlash('error', 'Le code saisi est invalide. Veuillez réessayer.');
                return $this->redirectToRoute('app_dashboard_2fa_enable');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur est survenue : ' . $e->getMessage());
            return $this->redirectToRoute('app_dashboard_2fa_enable');
        }
    }

    #[Route('/success', name: 'app_dashboard_2fa_success')]
    public function success(): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $twoFactorAuth = $user->getTwoFactorAuth();

        if (!$twoFactorAuth || !$twoFactorAuth->isEnabled()) {
            return $this->redirectToRoute('app_dashboard_2fa_index');
        }

        return $this->render('dashboard/two_factor/success.html.twig', [
            'backupCodes' => $user->getBackupCodes(),
        ]);
    }

    #[Route('/disable', name: 'app_dashboard_2fa_disable', methods: ['POST'])]
    public function disable(Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $password = $request->request->get('password');

        if (!$password) {
            return new JsonResponse(['success' => false, 'message' => 'Mot de passe requis']);
        }

        // Vérifier le mot de passe
        if (!password_verify($password, $user->getPassword())) {
            return new JsonResponse(['success' => false, 'message' => 'Mot de passe incorrect']);
        }

        $this->twoFactorAuthService->disableTwoFactor($user);

        return new JsonResponse(['success' => true, 'message' => '2FA désactivé avec succès']);
    }

    #[Route('/regenerate-backup-codes', name: 'app_dashboard_2fa_regenerate_backup', methods: ['POST'])]
    public function regenerateBackupCodes(Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $password = $request->request->get('password');

        if (!$password) {
            return new JsonResponse(['success' => false, 'message' => 'Mot de passe requis']);
        }

        // Vérifier le mot de passe
        if (!password_verify($password, $user->getPassword())) {
            return new JsonResponse(['success' => false, 'message' => 'Mot de passe incorrect']);
        }

        $twoFactorAuth = $user->getTwoFactorAuth();
        if (!$twoFactorAuth || !$twoFactorAuth->isEnabled()) {
            return new JsonResponse(['success' => false, 'message' => '2FA non activé']);
        }

        // Générer nouveaux codes de sauvegarde
        $backupCodes = [];
        for ($i = 0; $i < 10; $i++) {
            $backupCodes[] = sprintf('%06d', random_int(0, 999999));
        }
        
        $twoFactorAuth->setBackupCodesArray($backupCodes);
        
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true, 
            'message' => 'Codes de sauvegarde régénérés',
            'backupCodes' => $backupCodes
        ]);
    }
}
