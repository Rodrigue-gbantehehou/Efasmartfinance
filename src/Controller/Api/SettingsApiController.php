<?php

namespace App\Controller\Api;

use App\Entity\User;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Form\FormError;
use App\Form\NotificationSettingsType;
use App\Entity\NotificationPreferences;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Repository\SecuritySettingsRepository;

#[Route('/dashboard/api/settings')]
class SettingsApiController extends AbstractController
{
    private $entityManager;
    private $security;
    private $passwordHasher;

    public function __construct(EntityManagerInterface $entityManager, Security $security, UserPasswordHasherInterface $passwordHasher)
    {
        $this->entityManager = $entityManager;
        $this->security = $security;
        $this->passwordHasher = $passwordHasher;
    }

    #[Route('/profile', name: 'api_settings_profile', methods: ['GET', 'PUT'])]
    public function handleProfileSettings(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->security->getUser();
        
        // Méthode GET : Récupérer les informations du profil
        if ($request->isMethod('GET')) {
            return $this->json([
                'status' => 'success',
                'profile' => [
                    'firstname' => $user->getFirstname(),
                    'lastname' => $user->getLastName(),
                    'email' => $user->getEmail(),
                    'phone' => $user->getPhoneNumber()
                ]
            ]);
        }
        
        // Méthode PUT : Mettre à jour le profil
        $data = json_decode($request->getContent(), true);
        
        // Validation des données
        if (empty($data)) {
            return $this->json([
                'status' => 'error',
                'message' => 'Aucune donnée fournie'
            ], JsonResponse::HTTP_BAD_REQUEST);
        }
        
        // Mise à jour des champs fournis
        if (isset($data['firstname'])) {
            $user->setFirstname($data['firstname']);
        }
        
        if (isset($data['lastname'])) {
            $user->setLastname($data['lastname']);
        }
        
        if (isset($data['email'])) {
            // Vérifier si l'email est déjà utilisé
            $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $data['email']]);
            if ($existingUser && $existingUser->getId() !== $user->getId()) {
                return $this->json([
                    'status' => 'error',
                    'message' => 'Cet email est déjà utilisé par un autre compte'
                ], JsonResponse::HTTP_CONFLICT);
            }
            $user->setEmail($data['email']);
        }
        
        if (isset($data['phone'])) {
            $user->setPhoneNumber($data['phone']);
        }
        
        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            
            return $this->json([
                'status' => 'success',
                'message' => 'Profil mis à jour avec succès',
                'profile' => [
                    'firstname' => $user->getFirstname(),
                    'lastname' => $user->getLastname(),
                    'email' => $user->getEmail(),
                    'phone' => $user->getPhoneNumber()
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Une erreur est survenue lors de la mise à jour du profil',
                'error' => $e->getMessage()
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    #[Route('/notification', name: 'api_settings_notification', methods: ['GET', 'POST'])]
    public function handleNotificationSettings(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->security->getUser();
        
        // Méthode GET : Récupérer les préférences
        if ($request->isMethod('GET')) {
            $preferences = $user->getNotificationPreferences()->first();
            
            return $this->json([
                'status' => 'success',
                'preferences' => [
                    'emailNotifications' => $preferences ? $preferences->isEmailNotifications() : false,
                    'pushNotifications' => $preferences ? $preferences->isPushNotifications() : false,
                    'transactionAlerts' => $preferences ? $preferences->isTransactionAlerts() : false,
                    'marketingEmail' => $preferences ? $preferences->isMarketingEmail() : false,
                    'paymentReminders' => $preferences ? $preferences->isPaymentReminders() : false,
                    'securityAlerts' => $preferences ? $preferences->isSecurityAlerts() : false
                ]
            ]);
        }
        
        // Méthode POST : Mettre à jour les préférences
        $data = json_decode($request->getContent(), true);

        // Récupérer ou créer les préférences de notification
        $preferences = $user->getNotificationPreferences()->first() ?: new NotificationPreferences();
        
        // Mettre à jour les préférences
        $preferences->setEmailNotifications($data['emailNotifications'] ?? false);
        $preferences->setPushNotifications($data['pushNotifications'] ?? false);
        $preferences->setTransactionAlerts($data['transactionAlerts'] ?? false);
        $preferences->setMarketingEmail($data['marketingEmail'] ?? false);
        $preferences->setPaymentReminders($data['paymentReminders'] ?? false);
        $preferences->setSecurityAlerts($data['securityAlerts'] ?? false);
        $preferences->setUpdatedAt(new \DateTimeImmutable());
        
        if (!$preferences->getCreatedAt()) {
            $preferences->setCreatedAt(new \DateTimeImmutable());
            $preferences->setUtilisateur($user);
            $this->entityManager->persist($preferences);
            $user->addNotificationPreference($preferences);
        }
        
        $this->entityManager->flush();

        return $this->json([
            'status' => 'success',
            'message' => 'Paramètres de notification mis à jour avec succès.'
        ]);
    }
    
    #[Route('/security', name: 'api_settings_security', methods: ['GET', 'POST', 'PUT'])]
    public function handleSecuritySettings(Request $request, SecuritySettingsRepository $settingsRepo): JsonResponse
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $settings = $settingsRepo->getSettings();    
        
        // Handle GET request - Return current security settings
        if ($request->isMethod('GET')) {
            return $this->json([
                'status' => 'success',
                'security' => [
                    'hasPassword' => !empty($user->getPassword()),
                    'lastPasswordChange' => $user->getPasswordChangedAt() ? $user->getPasswordChangedAt()->format('Y-m-d H:i:s') : null,
                    'pinEnabled' => $settings ? $settings->isPinEnabled() : false
                ]
            ]);
        }
        
        // Handle POST request - Update security settings (password change)
        $data = json_decode($request->getContent(), true);
        
        if (empty($data)) {
            return $this->json([
                'status' => 'error',
                'message' => 'Aucune donnée fournie'
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Vérifier le mot de passe actuel
        if (!isset($data['currentPassword']) || !$this->passwordHasher->isPasswordValid($user, $data['currentPassword'])) {
            return $this->json([
                'status' => 'error',
                'message' => 'Le mot de passe actuel est incorrect'
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        /* Vérifier que les nouveaux mots de passe correspondent
        if (!isset($data['newPassword']) || !isset($data['confirmPassword']) || 
            $data['newPassword'] !== $data['newPassword']) {
            return $this->json([
                'status' => 'error',
                'message' => 'Les nouveaux mots de passe ne correspondent pas'
            ], JsonResponse::HTTP_BAD_REQUEST);
        }*/

        // Vérifier la complexité du mot de passe (au moins 8 caractères, 1 majuscule, 1 chiffre)
        if (!preg_match('/^(?=.*[A-Z])(?=.*\d).{8,}$/', $data['newPassword'])) {
            return $this->json([
                'status' => 'error',
                'message' => 'Le mot de passe doit contenir au moins 8 caractères, dont une majuscule et un chiffre'
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Mettre à jour le mot de passe
        $user->setPassword(
            $this->passwordHasher->hashPassword($user, $data['newPassword'])
        );
        
        // Update password change timestamp if you have this field
        if (method_exists($user, 'setPasswordChangedAt')) {
            $user->setPasswordChangedAt(new \DateTimeImmutable());
        }

        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return $this->json([
                'status' => 'success',
                'message' => 'Mot de passe mis à jour avec succès'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Une erreur est survenue lors de la mise à jour du mot de passe',
                'error' => $e->getMessage()
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}