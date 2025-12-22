<?php

namespace App\Controller\Dashboard;

use App\Entity\Tontine;
use App\Form\TontineType;
use App\Repository\TontineRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/Dashboard/Tontines')]
final class TontinesController extends AbstractController
{
    #[Route(name: 'app_tontines_index', methods: ['GET'])]
    public function index(TontineRepository $tontineRepository): Response
    {
        return $this->render('dashboard/pages/tontines/tontines.html.twig', [
            'tontines' => $tontineRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_tontines_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $tontine = new Tontine();
        $form = $this->createForm(TontineType::class, $tontine);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($tontine);
            $entityManager->flush();

            return $this->redirectToRoute('app_tontines_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('dashboard/pages/tontines/new.html.twig', [
            'tontine' => $tontine,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_tontines_show', methods: ['GET'])]
    public function show(Tontine $tontine): Response
    {
        return $this->render('dashboard/pages/tontines/show.html.twig', [
            'tontine' => $tontine,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_tontines_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Tontine $tontine, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TontineType::class, $tontine);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_tontines_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('dashboard/pages/tontines/edit.html.twig', [
            'tontine' => $tontine,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_tontines_delete', methods: ['POST'])]
    public function delete(Request $request, Tontine $tontine, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$tontine->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($tontine);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_tontines_index', [], Response::HTTP_SEE_OTHER);
    }
}
