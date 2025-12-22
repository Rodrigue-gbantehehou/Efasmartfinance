<?php

namespace App\Controller\Dashboard;

use App\Entity\Tontine;
use App\Repository\TontineRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/Dashboard')]
final class PagesController extends AbstractController
{
    #[Route(path: '/tontines_payment' , name: 'app_tontines_payment', methods: ['GET'])]
    public function index(TontineRepository $tontineRepository): Response
    {
        return $this->render('dashboard/pages/tontines/tontines.html.twig', [
            'tontines' => $tontineRepository->findAll(),
            ]);
    }
    
}