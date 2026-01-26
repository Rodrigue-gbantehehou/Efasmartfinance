<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\EmailService;
use App\Security\Authenticator;
use App\Security\EmailVerifier;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Service\SecurityLogger;
use Symfony\Component\Mime\Address;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    public function __construct(
        private EmailVerifier $emailVerifier, private EmailService $emailService,private SecurityLogger $securityLogger) {}

    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, Security $security, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user, [
            'validate_email_url' => $this->generateUrl('ajax_check_email')
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();

            // encoder le mot de passe
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            $user->setUuid($this->generateUniqueUserCode($entityManager));
            $user->isActive(true);
            $user->setCreatedAt(new \DateTimeImmutable());

            $entityManager->persist($user);
            $entityManager->flush();

            //log
             $this->securityLogger->log(
                    $user,
                    'USER_REGISTER',
                    'User',
                    $user->getId(),
                    'Nouveau utilisateur enregistré'
                );  

            

            // Envoyer un email avec le code utilisateur
            $this->emailService->send(
                $user->getEmail(),
                'Votre identifiant EFA Smart Finance',
                $this->renderView('emails/user_uuid.html.twig', [
                    'user' => $user,
                    'uuid' => $user->getUuid(),
                ])
            );

            // Envoyer un email de confirmation
            $this->emailVerifier->sendEmailConfirmation(
                'app_verify_email',
                $user,
                (new TemplatedEmail())
                    ->from(new Address('contact@binajia.org', 'Efa Smart Finance'))
                    ->to((string) $user->getEmail())
                    ->subject('Please Confirm your Email')
                    ->htmlTemplate('emails/confirmation_email.html.twig')
            );

            // Connecter automatiquement l'utilisateur
            $security->login($user, Authenticator::class, 'main');

            // Rediriger vers la page d'accueil avec un message de succès
            $this->addFlash('success', 'Votre compte a été créé avec succès ! Un email de confirmation vous a été envoyé.');
            return $this->redirectToRoute('app_verify_email_notice');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, TranslatorInterface $translator, UserRepository $userRepository): Response
    {
        $id = $request->query->get('id');

        if (null === $id) {
            return $this->redirectToRoute('app_register');
        }

        $user = $userRepository->find($id);

        if (null === $user) {
            return $this->redirectToRoute('app_register');
        }

        // valider le lien de confirmation, met User::isVerified=true et persiste
        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));

            return $this->redirectToRoute('app_register');
        }

        $this->addFlash('success', 'Votre adresse email a été vérifiée avec succès !');

        return $this->redirectToRoute('app_email_verified');
    }

    // Générer un code utilisateur unique
    private function generateUniqueUserCode(EntityManagerInterface $em): int
    {
        do {
            $code = random_int(1000000, 9999999);
            $exists = $em->getRepository(User::class)
                ->findOneBy(['uuid' => $code]);
        } while ($exists);

        return $code;
    }

    // Vérifier si l'email existe déjà
    #[Route('/ajax/check-email', name: 'ajax_check_email', methods: ['POST'])]
    public function checkEmail(Request $request, UserRepository $userRepository): JsonResponse
    {
        $email = $request->request->get('email');

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse([
                'exists' => false,
                'error' => 'Email invalide'
            ]);
        }

        $user = $userRepository->findOneBy(['email' => $email]);

        return new JsonResponse([
            'exists' => $user !== null
        ]);
    }
}
