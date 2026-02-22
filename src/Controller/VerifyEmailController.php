<?php

namespace App\Controller;

use App\Entity\User;
use App\Security\EmailVerifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

class VerifyEmailController extends AbstractController
{
    public function __construct(
        private EmailVerifier $emailVerifier,
        private string $contactEmail
    ) {
    }
    #[Route('/verify/email/notice', name: 'app_verify_email_notice')]
    public function verifyEmailNotice(): Response
    {
        return $this->render('registration/verify_email_notice.html.twig');
    }

    #[Route('/verify/email/verified', name: 'app_email_verified')]
    public function emailVerified(): Response
    {
        return $this->render('registration/email_verified.html.twig');
    }

    #[Route('/resend/verification', name: 'app_resend_verification')]
    public function resendVerification(#[CurrentUser] ?User $user = null): Response
    {
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté pour renvoyer un email de vérification.');
            return $this->redirectToRoute('app_login');
        }

        if ($user->isVerified()) {
            $this->addFlash('info', 'Votre adresse email est déjà vérifiée.');
            return $this->redirectToRoute('app_home');
        }

        try {
            $this->emailVerifier->sendEmailConfirmation(
                'app_verify_email',
                $user,
                (new TemplatedEmail())
                    ->from(new Address($this->contactEmail, 'Efa Smart Finance'))
                    ->to($user->getEmail())
                    ->subject('Veuillez confirmer votre email')
                    ->htmlTemplate('emails/confirmation_email.html.twig')
            );

            $this->addFlash('success', 'Un nouvel email de vérification a été envoyé à votre adresse email.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur est survenue lors de l\'envoi de l\'email de vérification. Veuillez réessayer plus tard.');
        }

        return $this->redirectToRoute('app_verify_email_notice');
    }
}
