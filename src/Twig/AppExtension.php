<?php

namespace App\Twig;

use Twig\TwigFunction;
use Twig\TwigFilter;
use App\Repository\TontineRepository;
use App\Service\CookieConsentManager;
use Twig\Extension\AbstractExtension;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class AppExtension extends AbstractExtension
{
    private $tontineRepository;
    private $requestStack;
    private $security;
    private $cookieConsentManager;

    public function __construct(TontineRepository $tontineRepository, RequestStack $requestStack, Security $security, CookieConsentManager $cookieConsentManager)
    {
        $this->tontineRepository = $tontineRepository;
        $this->requestStack = $requestStack;
        $this->security = $security;
        $this->cookieConsentManager = $cookieConsentManager;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('getCurrentTontine', [$this, 'getCurrentTontine']),
            new TwigFunction('has_cookie_consent', [$this, 'hasCookieConsent']),
            new TwigFunction('cookie_consent_preferences', [$this, 'getCookieConsentPreferences']),
        ];
    }

    public function getFilters()
    {
        return [
            new TwigFilter('json_decode', [$this, 'jsonDecode']),
        ];
    }

    public function jsonDecode($string)
    {
        return json_decode($string, true);
    }

    public function getCurrentTontine()
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return null;
        }

        $route = $request->attributes->get('_route');
        
        // Only get tontine for specific routes where it makes sense
        if (strpos($route, 'app_tontines_') === 0) {
            $tontineId = $request->attributes->get('id');
            if ($tontineId) {
                $user = $this->security->getUser();
                return $this->tontineRepository->findOneBy([
                    'id' => $tontineId,
                    'utilisateur' => $user
                ]);
            }
        }
        
        return null;
    }

        public function hasCookieConsent(): bool
    {
        return $this->cookieConsentManager->hasConsent();
    }

    public function getCookieConsentPreferences(): array
    {
        return $this->cookieConsentManager->getConsentPreferences();
    }
}
