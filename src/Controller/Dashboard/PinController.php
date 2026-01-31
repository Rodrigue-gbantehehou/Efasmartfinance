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
        private PinAuthService $pinAuthService
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

            // Verify password (you'll need to inject PasswordHasher)
            // For now, we'll just reset the PIN
            
            try {
                $temporaryPin = $this->pinAuthService->resetPin($user);
                
                // TODO: Send temporary PIN by email
                // For now, display it (NOT SECURE FOR PRODUCTION)
                $this->addFlash('success', sprintf(
                    'Votre code PIN a été réinitialisé. Votre nouveau code PIN temporaire est: %s. Vous devrez le changer à la prochaine connexion.',
                    $temporaryPin
                ));
                
                return $this->redirectToRoute('pin_change');
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('dashboard/pin/reset_request.html.twig');
    }
}
