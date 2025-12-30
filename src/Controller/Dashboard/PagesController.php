<?php

namespace App\Controller\Dashboard;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dashboard')]
final class PagesController extends AbstractController
{
    #[Route('/aide', name: 'app_aide')]
    public function aide(): Response
    {
        return $this->render('dashboard/pages/aide/aide.html.twig');
    }
}