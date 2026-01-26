<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use App\Repository\TontineRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Bundle\SecurityBundle\Security;

class AppExtension extends AbstractExtension
{
    private $tontineRepository;
    private $requestStack;
    private $security;

    public function __construct(TontineRepository $tontineRepository, RequestStack $requestStack, Security $security)
    {
        $this->tontineRepository = $tontineRepository;
        $this->requestStack = $requestStack;
        $this->security = $security;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('getCurrentTontine', [$this, 'getCurrentTontine']),
        ];
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
}
