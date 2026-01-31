<?php

namespace App\Service;

use App\Entity\CookieConsent;
use App\Entity\User;
use App\Repository\CookieConsentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class CookieConsentManager
{
    private const COOKIE_NAME = 'cookie_consent';
    private const COOKIE_EXPIRY = 365; // jours

    public function __construct(
        private EntityManagerInterface $entityManager,
        private CookieConsentRepository $cookieConsentRepository,
        private RequestStack $requestStack
    ) {
    }

    public function createConsent(
        ?User $user,
        ?string $ipAddress,
        ?string $userAgent,
        array $consentData
    ): CookieConsent {
        // Créer un nouveau consentement
        $consent = new CookieConsent();
        // Si un utilisateur est connecté, l'associer
       if ($user) {
           $consent->setUtilisateur($user);
       }


        // Définir les données de base
        $consent->setIpAddress($ipAddress);
        $consent->setUserAgent($userAgent);
        
        // Définir les préférences de cookies avec des valeurs par défaut si non fournies
        $consent->setAnalyticsCookies((bool)($consentData['analytics'] ?? false));
        $consent->setMarketingCookies((bool)($consentData['marketing'] ?? false));
        $consent->setPreferencesCookies((bool)($consentData['preferences'] ?? false));
        $consent->setConsentVersion($consentData['version'] ?? '1.0');

        // Sauvegarder en base de données
        $this->entityManager->persist($consent);
        $this->entityManager->flush();

        // Mettre à jour le cookie du navigateur
        $cookie = $this->createCookie($consentData);
        if ($cookie) {
            $this->attachCookieToResponse($cookie);
        }

        return $consent;
    }

    /**
     * Crée l'objet Cookie sans l'attacher à une réponse
     */
    public function createCookie(array $consentData): ?\Symfony\Component\HttpFoundation\Cookie
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return null;
        }

        $cookieData = [
            'accepted' => true,
            'consent' => $consentData,
            'timestamp' => time()
        ];

        return new \Symfony\Component\HttpFoundation\Cookie(
            self::COOKIE_NAME,
            json_encode($cookieData),
            time() + (self::COOKIE_EXPIRY * 24 * 60 * 60),
            '/',
            null,
            $request->isSecure(),
            true, // httpOnly
            false,
            'Lax'
        );
    }

    /**
     * Tente d'attacher un cookie à la réponse actuelle via la session
     */
    private function attachCookieToResponse(\Symfony\Component\HttpFoundation\Cookie $cookie): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        // Récupérer la réponse depuis l'événement courant ou en créer une nouvelle
        $response = $request->hasSession() && $request->getSession()->has('_response')
            ? $request->getSession()->get('_response')
            : new \Symfony\Component\HttpFoundation\Response();

        $response->headers->setCookie($cookie);

        // Stocker la réponse pour qu'elle soit utilisée plus tard si la session est disponible
        if ($request->hasSession()) {
            try {
                $request->getSession()->set('_response', $response);
            } catch (\Exception $e) {
                // Si la session n'est pas démarrée ou autre erreur, on ignore
            }
        }
    }

    public function getLatestConsent(?User $user): ?CookieConsent
    {
        if ($user) {
            return $this->cookieConsentRepository->findLatestConsentByUser($user);
        }

        return null;
    }

    public function hasConsent(?User $user = null): bool
    {
        // Vérifier d'abord le cookie du navigateur (pour tous les utilisateurs)
        $cookieData = $this->getConsentFromCookie();
        if ($cookieData !== null && isset($cookieData['accepted']) && $cookieData['accepted'] === true) {
            return true;
        }

        // Si l'utilisateur est connecté, vérifier en base de données
        if ($user) {
            return $this->cookieConsentRepository->hasGivenConsent($user);
        }

        return false;
    }

    public function getConsentFromCookie(): ?array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return null;
        }

        $cookie = $request->cookies->get(self::COOKIE_NAME);
        if (!$cookie) {
            return null;
        }

        $data = json_decode($cookie, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        // Vérifier que le cookie contient bien les données attendues
        if (!isset($data['accepted']) || $data['accepted'] !== true) {
            return null;
        }

        return $data;
    }

    public function getConsentPreferences(): array
    {
        $cookieData = $this->getConsentFromCookie();
        
        if (!$cookieData || !isset($cookieData['consent'])) {
            return [
                'analytics' => false,
                'marketing' => false,
                'preferences' => false,
                'necessary' => true // Toujours actif
            ];
        }

        return [
            'analytics' => (bool)($cookieData['consent']['analytics'] ?? false),
            'marketing' => (bool)($cookieData['consent']['marketing'] ?? false),
            'preferences' => (bool)($cookieData['consent']['preferences'] ?? false),
            'necessary' => true, // Toujours actif
            'version' => $cookieData['consent']['version'] ?? '1.0',
            'consentDate' => $cookieData['consent']['consentDate'] ?? null
        ];
    }

    private function setConsentCookie(bool $accepted, array $consentData = []): void
    {
        $cookie = $this->createCookie($consentData);
        if ($cookie) {
            $this->attachCookieToResponse($cookie);
        }
    }

    public function clearConsent(): void
    {
        // Supprimer le cookie
        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $response = new \Symfony\Component\HttpFoundation\Response();
            $response->headers->clearCookie(self::COOKIE_NAME, '/', null, $request->isSecure(), true);
            
            if (!headers_sent()) {
                $response->sendHeaders();
            }
        }
    }

    public function getRequiredCookies(): array
    {
        return [
            'necessary' => [
                'name' => 'Cookies nécessaires',
                'description' => 'Ces cookies sont essentiels au bon fonctionnement du site et ne peuvent pas être désactivés.',
                'required' => true
            ]
        ];
    }

    public function getOptionalCookies(): array
    {
        return [
            'analytics' => [
                'name' => 'Cookies d\'analyse',
                'description' => 'Ces cookies nous permettent de comprendre comment les visiteurs interagissent avec notre site web en recueillant des informations de manière anonyme.',
                'required' => false
            ],
            'preferences' => [
                'name' => 'Cookies de préférences',
                'description' => 'Ces cookies permettent au site de se souvenir des choix que vous faites pour améliorer votre expérience utilisateur.',
                'required' => false
            ],
            'marketing' => [
                'name' => 'Cookies marketing',
                'description' => 'Ces cookies sont utilisés pour suivre les visiteurs à travers les sites web. Le but est d\'afficher des publicités qui sont pertinentes et engageantes pour l\'utilisateur individuel.',
                'required' => false
            ]
        ];
    }
}