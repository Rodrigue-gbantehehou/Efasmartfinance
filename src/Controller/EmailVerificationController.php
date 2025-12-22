<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EmailVerificationController extends AbstractController
{
    #[Route('/email/verified', name: 'app_email_verified')]
    public function emailVerified(): Response
    {
        return $this->render('registration/email_verified.html.twig', [
            'controller_name' => 'EmailVerificationController',
        ]);
    }
}
