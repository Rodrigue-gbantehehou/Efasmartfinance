<?php

namespace App\Controller\Dashboard;

use App\Entity\User;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\SecurityBundle\Security;

use App\Service\PinAuthService;

#[Route('/dashboard/parametres')]
#[IsGranted('ROLE_USER')]
class SettingsController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ActivityLogger $activityLogger,
        private PinAuthService $pinAuthService
    ) {}

    #[Route('', name: 'app_settings')]
    public function index(): Response
    {
        return $this->render('dashboard/pages/settings/index.html.twig', [
            'current_page' => 'settings',
        ]);
    }

    #[Route('/supprimer-compte', name: 'app_settings_delete', methods: ['POST'])]
    public function delete(Request $request, Security $security): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($this->isCsrfTokenValid('delete-account' . $user->getId(), $request->request->get('_token'))) {
            $pin = $request->request->get('pin');
            
            if (!$pin || !$this->pinAuthService->verifyPin($user, $pin)) {
                $this->addFlash('error', 'Code PIN incorrect.');
                return $this->redirectToRoute('app_settings');
            }

            $user->setDeletionRequestedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $this->activityLogger->log(
                $user,
                'ACCOUNT_DELETION_REQUESTED',
                'User',
                $user->getId(),
                sprintf('Demande de suppression de compte par %s', $user->getEmail())
            );

            $this->addFlash('success', 'Votre demande de suppression a été prise en compte. Vous avez 30 jours pour annuler cette action.');

            return $security->logout(false);
        }

        $this->addFlash('error', 'L\'action a expiré. Veuillez réessayer.');
        return $this->redirectToRoute('app_settings');
    }

    #[Route('/restaurer-compte', name: 'app_settings_restore')]
    public function restore(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user->isPendingDeletion()) {
            return $this->redirectToRoute('app_dashboard');
        }

        if ($request->isMethod('POST')) {
            if ($this->isCsrfTokenValid('restore-account' . $user->getId(), $request->request->get('_token'))) {
                $pin = $request->request->get('pin');
                
                if (!$pin || !$this->pinAuthService->verifyPin($user, $pin)) {
                    $this->addFlash('error', 'Code PIN incorrect.');
                    return $this->redirectToRoute('app_settings_restore');
                }

                $user->setDeletionRequestedAt(null);
                $user->setDeletionWarningSentAt(null);
                $this->entityManager->flush();

                $this->activityLogger->log(
                    $user,
                    'ACCOUNT_RESTORED',
                    'User',
                    $user->getId(),
                    sprintf('Compte restauré par %s', $user->getEmail())
                );

                $this->addFlash('success', 'Votre compte a été restauré avec succès.');
                return $this->redirectToRoute('app_dashboard');
            }
            $this->addFlash('error', 'L\'action a expiré. Veuillez réessayer.');
        }

        return $this->render('dashboard/pages/settings/restore.html.twig', [
            'days_remaining' => $user->getDaysUntilDeletion(),
        ]);
    }
}
