<?php

namespace App\Controller\Dashboard;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/dashboard')]
class HelpController extends AbstractController
{
    #[Route('/aide', name: 'app_aide')]
    public function index(): Response
    {
        return $this->render('dashboard/pages/aide/aide.html.twig');
    }
     #[Route('/tarifs', name: 'app_tarifs')]
    public function tarifs(): Response
    {
        return $this->render('dashboard/pages/legals/tarifetfrais.html.twig');
    }
    #[Route('/contact-support', name: 'app_contact_support')]
    public function contactSupport(): Response
    {
        return $this->render('dashboard/pages/contact/contact_form.html.twig');
    }
}
