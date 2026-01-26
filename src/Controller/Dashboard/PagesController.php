<?php

namespace App\Controller\Dashboard;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Service\CountryService;

#[Route('/dashboard')]
final class PagesController extends AbstractController
{
    private CountryService $countryService;

    public function __construct(CountryService $countryService)
    {
        $this->countryService = $countryService;
    }

    #[Route('/aide', name: 'app_aide')]
    public function aide(): Response
    {
        return $this->render('dashboard/pages/aide/aide.html.twig');
    }
    #[Route('/cgu', name: 'app_cgu')]
    public function cgu(): Response
    {
        return $this->render('dashboard/pages/legals/cgu.html.twig');
    }
    #[Route('/mentionlegal', name: 'app_mentions')]
    public function mentionlegal(): Response
    {
        return $this->render('dashboard/pages/legals/mentionlegal.html.twig');
    }
    #[Route('/tarif-et-frais', name: 'app_tarifs')]
    public function tarifetfrais(): Response
    {
        return $this->render('dashboard/pages/legals/tarifetfrais.html.twig');
    }
  
    #[Route('/contact', name: 'app_contact_support')]
    public function contact(): Response
    {
        return $this->render('dashboard/pages/legals/contact.html.twig');
    }

    #[Route('/support', name: 'app_support')]
    public function support(): Response
    {
        // TODO: Implement support functionality
        return $this->render('dashboard/pages/support/support.html.twig');
    }
    
}