<?php

namespace App\Controller;

use App\Entity\Tontine;
use App\Entity\Withdrawals;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class PublicTontineCardController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}
     #[Route('/tontines', name: 'page_tontine')]
    public function pageTontine(): Response
    {
        return $this->render('pages/tontine/presentation.html.twig', [
            'controller_name' => 'TontineController::pageTontine',
        ]);
    }

    #[Route('/tontine/card/{code}', name: 'app_public_tontine_card')]
    public function show(string $code): Response
    {
        $tontine = $this->entityManager->getRepository(Tontine::class)->findOneBy(['id' => $code]);

        if (!$tontine) {
            throw $this->createNotFoundException('Carte tontine non trouvée');
        }

        return $this->render('public/tontine_card.html.twig', [
            'tontine' => $tontine,
            'user' => $tontine->getUtilisateur(),
        ]);
    }
}
