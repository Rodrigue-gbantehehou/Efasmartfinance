<?php

namespace App\Controller;

use App\Service\PinAuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PinVerificationController extends AbstractController
{
    public function __construct(
        private PinAuthService $pinAuthService
    ) {
    }

    #[Route('/pin/verify', name: 'pin_verify')]
    #[IsGranted('IS_AUTHENTICATED')]
    public function verify(Request $request): Response
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Check if user has PIN configured
        if (!$user->hasPinAuth()) {
            // Redirect to PIN setup
            return $this->redirectToRoute('pin_setup');
        }

        // Check if account is locked
        if ($this->pinAuthService->isAccountLocked($user)) {
            $pinAuth = $user->getPinAuth();
            $lockedUntil = $pinAuth->getLockedUntil();
            
            if ($lockedUntil) {
                // Flash message only if not already present
                $flashBag = $request->getSession()->getFlashBag();
                if (!$flashBag->has('error')) {
                    $this->addFlash('error', sprintf(
                        'Votre compte est temporairement verrouillé jusqu\'à %s suite à trop de tentatives échouées.',
                        $lockedUntil->format('H:i')
                    ));
                }
            }
        }

        $remainingAttempts = $this->pinAuthService->getRemainingAttempts($user);

        return $this->render('security/pin_verification.html.twig', [
            'remaining_attempts' => $remainingAttempts,
            'must_change_pin' => $user->mustChangePin()
        ]);
    }

    #[Route('/pin/check', name: 'pin_check', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function checkPin(Request $request): Response
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $pin = $request->request->get('pin');

        if (!$pin) {
            $this->addFlash('error', 'Veuillez saisir votre code PIN');
            return $this->redirectToRoute('pin_verify');
        }

        try {
            if ($this->pinAuthService->verifyPin($user, $pin)) {
                // PIN correct - store in session
                $request->getSession()->set('pin_verified', true);
                $request->getSession()->set('pin_verified_at', time());

                // Check if user must change PIN
                if ($user->mustChangePin()) {
                    $this->addFlash('warning', 'Vous devez changer votre code PIN temporaire');
                    return $this->redirectToRoute('pin_change');
                }

                // Redirect to intended destination or dashboard
                $targetPath = $request->getSession()->get('_security.main.target_path');
                if ($targetPath) {
                    $request->getSession()->remove('_security.main.target_path');
                    return $this->redirect($targetPath);
                }

                return $this->redirectToRoute('app_tontines_index');
            } else {
                $remainingAttempts = $this->pinAuthService->getRemainingAttempts($user);
                
                if ($remainingAttempts > 0) {
                    $this->addFlash('error', sprintf(
                        'Code PIN incorrect. Il vous reste %d tentative(s).',
                        $remainingAttempts
                    ));
                } else {
                    $this->addFlash('error', 'Votre compte a été temporairement verrouillé.');
                }

                return $this->redirectToRoute('pin_verify');
            }
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('pin_verify');
        }
    }
}
