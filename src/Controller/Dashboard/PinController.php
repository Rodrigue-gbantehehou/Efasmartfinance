<?php

namespace App\Controller\Dashboard;

use App\Service\PinAuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/pin')]
#[IsGranted('ROLE_USER')]
class PinController extends AbstractController
{
    public function __construct(
        private PinAuthService $pinAuthService,
        private \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface $passwordHasher
    ) {
    }

    #[Route('/setup', name: 'pin_setup')]
    public function setup(Request $request): Response
    {
        $user = $this->getUser();

        // If user already has PIN, redirect to change page
        if ($user->hasPinAuth()) {
            return $this->redirectToRoute('pin_change');
        }

        if ($request->isMethod('POST')) {
            $pin = $request->request->get('pin');
            $pinConfirm = $request->request->get('pin_confirm');

            // Validate PIN
            if (!$pin || !$pinConfirm) {
                $this->addFlash('error', 'Veuillez remplir tous les champs');
                return $this->redirectToRoute('pin_setup');
            }

            if ($pin !== $pinConfirm) {
                $this->addFlash('error', 'Les codes PIN ne correspondent pas');
                return $this->redirectToRoute('pin_setup');
            }

            if (!preg_match('/^\d{5}$/', $pin)) {
                $this->addFlash('error', 'Le code PIN doit contenir exactement 5 chiffres');
                return $this->redirectToRoute('pin_setup');
            }

            try {
                $this->pinAuthService->createPin($user, $pin);
                
                // Mark PIN as verified in session
                $request->getSession()->set('pin_verified', true);
                $request->getSession()->set('pin_verified_at', time());

                $this->addFlash('success', 'Votre code PIN a été créé avec succès');
                return $this->redirectToRoute('app_tontines_index');
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('dashboard/pin/setup.html.twig');
    }

    #[Route('/change', name: 'pin_change')]
    public function change(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user->hasPinAuth()) {
            return $this->redirectToRoute('pin_setup');
        }

        if ($request->isMethod('POST')) {
            $oldPin = $request->request->get('old_pin');
            $newPin = $request->request->get('new_pin');
            $newPinConfirm = $request->request->get('new_pin_confirm');

            // Validate inputs
            if (!$oldPin || !$newPin || !$newPinConfirm) {
                $this->addFlash('error', 'Veuillez remplir tous les champs');
                return $this->redirectToRoute('pin_change');
            }

            if ($newPin !== $newPinConfirm) {
                $this->addFlash('error', 'Les nouveaux codes PIN ne correspondent pas');
                return $this->redirectToRoute('pin_change');
            }

            if (!preg_match('/^\d{5}$/', $newPin)) {
                $this->addFlash('error', 'Le nouveau code PIN doit contenir exactement 5 chiffres');
                return $this->redirectToRoute('pin_change');
            }

            try {
                if ($this->pinAuthService->changePin($user, $oldPin, $newPin)) {
                    $this->addFlash('success', 'Votre code PIN a été modifié avec succès');
                    return $this->redirectToRoute('app_tontines_index');
                } else {
                    $this->addFlash('error', 'L\'ancien code PIN est incorrect');
                }
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('dashboard/pin/change.html.twig', [
            'must_change' => $user->mustChangePin()
        ]);
    }

    #[Route('/reset-request', name: 'pin_reset_request')]
    public function resetRequest(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user->hasPinAuth()) {
            return $this->redirectToRoute('pin_setup');
        }

        if ($request->isMethod('POST')) {
            $password = $request->request->get('password');

            if (!$password) {
                $this->addFlash('error', 'Veuillez saisir votre mot de passe');
                return $this->redirectToRoute('pin_reset_request');
            }

            // Verify password
            if (!$this->passwordHasher->isPasswordValid($user, $password)) {
                $this->addFlash('error', 'Mot de passe incorrect');
                return $this->redirectToRoute('pin_reset_request');
            }
            
            try {
                // Clear any lockout
                $this->pinAuthService->resetFailedAttempts($user);
                
                // Authorize PIN change in session
                $request->getSession()->set('pin_reset_authorized', true);
                
                $this->addFlash('success', 'Sécurité vérifiée. Vous pouvez maintenant définir votre nouveau code PIN.');
                return $this->redirectToRoute('pin_setup_forced');
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('dashboard/pin/reset_request.html.twig');
    }

    #[Route('/setup/forced', name: 'pin_setup_forced')]
    public function setupForced(Request $request): Response
    {
        $user = $this->getUser();

        // Security check: only allow if password was just verified
        if (!$request->getSession()->get('pin_reset_authorized')) {
            return $this->redirectToRoute('pin_reset_request');
        }

        if ($request->isMethod('POST')) {
            $pin = $request->request->get('pin');
            $pinConfirm = $request->request->get('pin_confirm');

            if ($pin !== $pinConfirm) {
                $this->addFlash('error', 'Les codes PIN ne correspondent pas');
                return $this->redirectToRoute('pin_setup_forced');
            }

            try {
                // Use the special reset method in service
                $this->pinAuthService->createPinAfterReset($user, $pin);
                
                // Clear temporary authorization
                $request->getSession()->remove('pin_reset_authorized');
                
                $this->addFlash('success', 'Votre nouveau code PIN a été configuré avec succès');
                return $this->redirectToRoute('app_tontines_index');
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('dashboard/pin/setup.html.twig', [
            'is_forced' => true
        ]);
    }
}
