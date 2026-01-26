<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class TutorialController extends AbstractController
{
    #[Route('/tutoriels', name: 'app_tutoriels')]
    public function index(): Response
    {
        return $this->render('pages/tutoriels/index.html.twig', [
            'controller_name' => 'TutorialController',
        ]);
    }

    #[Route('/blog', name: 'app_blog')]
    public function blog(): Response
    {
        return $this->render('pages/blog/index.html.twig', [
            'controller_name' => 'TutorialController',
        ]);
    }
}
