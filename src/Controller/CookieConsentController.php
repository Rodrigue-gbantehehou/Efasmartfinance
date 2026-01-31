<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\CookieConsent;
use App\Service\CookieConsentManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CookieConsentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CookieConsentManager $cookieConsentManager,
        private Security $security
    ) {}

    #[Route('/cookies', name: 'app_cookie_consent')]
    public function index(): Response
    {
        return $this->render('cookie_consent/index.html.twig');
    }

    #[Route('/api/cookie-consent', name: 'api_cookie_consent', methods: ['POST'])]
    public function saveConsent(Request $request): JsonResponse
    {
        // Récupération de l'utilisateur actuel
        $user = $this->security->getUser();
    try {
        $data = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Données JSON invalides');
        }

        if (!$data) {
            throw new \InvalidArgumentException('Aucune donnée de consentement fournie');
        }

        $consent = $this->cookieConsentManager->createConsent(
            $user,
            $request->getClientIp(),
            $request->headers->get('User-Agent'),
            $data
        );

        $response = new JsonResponse([
            'success' => true,
            'consent' => [
                'id' => $consent->getId(),
                'consentDate' => $consent->getConsentDate()->format('c'),
                'consentVersion' => $consent->getConsentVersion(),
                'userId' => $user ? $user->getId() : null
            ]
        ]);

        // Attacher le cookie à la réponse
        $cookie = $this->cookieConsentManager->createCookie($data);
        if ($cookie) {
            $response->headers->setCookie($cookie);
        }

        return $response;

    } catch (\InvalidArgumentException $e) {
        return $this->json([
            'success' => false,
            'message' => $e->getMessage()
        ], Response::HTTP_BAD_REQUEST);
    } catch (\Exception $e) {
        // Log l'erreur pour le débogage
        error_log('Erreur lors de l\'enregistrement du consentement: ' . $e->getMessage());
        error_log($e->getTraceAsString());
        
        return $this->json([
            'success' => false,
            'message' => 'Une erreur est survenue lors de l\'enregistrement du consentement.'
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

#[Route('/api/cookie-consent/check', name: 'api_cookie_consent_check', methods: ['GET'])]
public function checkConsent(
    #[CurrentUser] ?User $user
): JsonResponse {
    // Vérifier le cookie (pour utilisateur connecté ou non)
    $cookieData = $this->cookieConsentManager->getConsentFromCookie();
    
    if ($cookieData !== null) {
        // L'utilisateur a un cookie de consentement valide
        return $this->json([
            'hasConsent' => true,
            'consent' => [
                'analyticsCookies' => $cookieData['consent']['analytics'] ?? false,
                'marketingCookies' => $cookieData['consent']['marketing'] ?? false,
                'preferencesCookies' => $cookieData['consent']['preferences'] ?? false,
                'consentVersion' => $cookieData['consent']['version'] ?? '1.0',
                'consentDate' => $cookieData['consent']['consentDate'] ?? null
            ],
            'source' => 'cookie'
        ]);
    }
    
    // Si l'utilisateur est connecté, vérifier la base de données
    if ($user) {
        $consent = $this->cookieConsentManager->getLatestConsent($user);
        
        return $this->json([
            'hasConsent' => $consent !== null,
            'consent' => $consent ? [
                'analyticsCookies' => $consent->isAnalyticsCookies(),
                'marketingCookies' => $consent->isMarketingCookies(),
                'preferencesCookies' => $consent->isPreferencesCookies(),
                'consentVersion' => $consent->getConsentVersion(),
                'consentDate' => $consent->getConsentDate()->format('c')
            ] : null,
            'source' => 'database'
        ]);
    }

    // Pas de consentement trouvé
    return $this->json(['hasConsent' => false]);
}

    // Dans CookieConsentController.php, ajoutez une route de test :
    #[Route('/api/cookie-consent/test', name: 'api_cookie_consent_test', methods: ['GET'])]
    public function test(): JsonResponse
    {
        return $this->json([
            'status' => 'API cookie consent fonctionnelle',
            'timestamp' => (new \DateTime())->format('c')
        ]);
    }
}
