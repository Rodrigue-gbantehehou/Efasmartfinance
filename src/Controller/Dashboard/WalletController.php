<?php

namespace App\Controller\Dashboard;

use App\Entity\Wallets;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\Transaction;
use App\Entity\WalletTransactions;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/dashboard')]
final class WalletController extends AbstractController
{
    #[Route('/wallet', name: 'app_wallet')]
    public function index(EntityManagerInterface $em): Response
    {
        //le portefeuille de l'utilisateur connecté
        $user = $this->getUser();
        $wallet = $em->getRepository(Wallets::class)->findOneBy(['utilisateur' => $user]);
        $transactions = $em->getRepository(WalletTransactions::class)->findBy(['wallet' => $wallet]);
       $recentstransactions = $em->getRepository(WalletTransactions::class)->findBy(['wallet' => $wallet], ['createdAt' => 'DESC'], 5);
       
        return $this->render('dashboard/pages/wallet/index.html.twig', [
            'wallet' => $wallet,
            'transactions' => $transactions,
               'totalDeposited' => 0,
               'recentTransactions' => $recentstransactions

        ]);
    }

    #[Route('/wallet/transactions', name: 'app_wallet_transactions')]
    public function transactions(EntityManagerInterface $em): Response
    {
        //le portefeuille de l'utilisateur connecté
        $user = $this->getUser();
        $wallet = $em->getRepository(Wallets::class)->findOneBy(['utilisateur' => $user]);
        $transactions = $em->getRepository(WalletTransactions::class)->findBy(['wallet' => $wallet]);
        return $this->render('dashboard/pages/wallet/index.html.twig', [
            'wallet' => $wallet,
            'transactions' => $transactions,
        ]);
    }

     #[Route('/wallet/deposit', name: 'app_wallet_deposit')]
    public function deposit(EntityManagerInterface $em): Response
    {
        //le portefeuille de l'utilisateur connecté
        $user = $this->getUser();
        $wallet = $em->getRepository(Wallets::class)->findOneBy(['utilisateur' => $user]);
        $transactions = $em->getRepository(WalletTransactions::class)->findBy(['wallet' => $wallet]);
        return $this->render('dashboard/pages/wallet/index.html.twig', [
            'wallet' => $wallet,
            'transactions' => $transactions,
         
        ]);
    }
    
 #[Route('/wallet/autopay', name: 'app_wallet_toggle_autopay')]
    public function autopay(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $wallet = $em->getRepository(Wallets::class)->findOneBy(['utilisateur' => $user]);
        $wallet->setAutoPayEnabled(!$wallet->isAutoPayEnabled());
        $em->flush();
        
        return $this->json([
            'success' => true,
            'enabled' => $wallet->isAutoPayEnabled()
        ]);
    }
      
    


}
