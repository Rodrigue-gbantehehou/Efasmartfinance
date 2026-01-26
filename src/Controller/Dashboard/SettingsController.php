<?php

namespace App\Controller\Dashboard;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/parametres')]
#[IsGranted('ROLE_USER')]
class SettingsController extends AbstractController
{
    #[Route('', name: 'app_settings')]
    public function index(): Response
    {
        return $this->render('dashboard/pages/settings/index.html.twig', [
            'current_page' => 'settings',
        ]);
    }
}
