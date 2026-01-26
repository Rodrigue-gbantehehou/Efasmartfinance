<?php

namespace App\Controller\Admin;

use App\Entity\Tontine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/admin/tontines')]
class TontineController extends AbstractController
{
    #[Route('/', name: 'admin_tontine_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $tontines = $entityManager->getRepository(Tontine::class)->findAll();
        
        return $this->render('admin/pages/tontines/index.html.twig', [
            'tontines' => $tontines,
        ]);
    }

    #[Route('/{id}', name: 'admin_tontine_show', methods: ['GET'])]
    public function show(Tontine $tontine): Response
    {
        return $this->render('admin/pages/tontines/show.html.twig', [
            'tontine' => $tontine,
        ]);
    }

    #[Route('/{id}/active', name: 'admin_tontine_activate', methods: ['POST', 'GET'])]
    public function activate(Tontine $tontine, EntityManagerInterface $entityManager): Response
    {
        $tontine->setStatut('active');
        $entityManager->flush();

        $this->addFlash('success', 'La tontine a été activée avec succès.');
        return $this->redirectToRoute('admin_tontine_index');
    }

    #[Route('/{id}/deactivate', name: 'admin_tontine_deactivate', methods: ['POST', 'GET'])]
    public function deactivate(Tontine $tontine, EntityManagerInterface $entityManager): Response
    {
        $tontine->setStatut('inactive');
        $entityManager->flush();

        $this->addFlash('warning', 'La tontine a été désactivée.');
        return $this->redirectToRoute('admin_tontine_index');
    }
}
