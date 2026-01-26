<?php

namespace App\Controller\Dashboard;

use App\Entity\Wallets;
use App\Entity\Transaction;
use App\Entity\WalletTransactions;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[IsGranted('ROLE_USER')]
#[Route('/dashboard')]
final class WalletController extends AbstractController
{
    #[Route('/wallet', name: 'app_wallet')]
    public function index(EntityManagerInterface $em): Response
    {
        // Get the logged-in user
        $user = $this->getUser();
        
        // Try to get the user's wallet
        $wallet = $em->getRepository(Wallets::class)->findOneBy(['utilisateur' => $user]);
        
        // If wallet doesn't exist, create a new one
        if (!$wallet) {
            $wallet = new Wallets();
            $wallet->setUtilisateur($user);
            $wallet->setBalance(0);
            $wallet->setAutoPayEnabled(false);
            $wallet->setUpdatedAt(new \DateTimeImmutable());
            
            $em->persist($wallet);
            $em->flush();
        }
        
        // Get transactions for the wallet
        $transactions = $em->getRepository(WalletTransactions::class)->findBy(['wallet' => $wallet]);
        $recentTransactions = $em->getRepository(WalletTransactions::class)->findBy(
            ['wallet' => $wallet], 
            ['createdAt' => 'DESC'], 
            5
        );
       
        return $this->render('dashboard/pages/wallet/index.html.twig', [
            'wallet' => $wallet,
            'transactions' => $transactions,
            'totalDeposited' => array_sum(array_map(fn($t) => $t->getAmount(), $transactions)),
            'recentTransactions' => $recentTransactions
        ]);
    }

    #[Route('/wallet/transactions', name: 'app_wallet_transactions')]
    public function transactions(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $wallet = $em->getRepository(Wallets::class)->findOneBy(['utilisateur' => $user]);
        
        // If wallet doesn't exist, create a new one
        if (!$wallet) {
            $wallet = new Wallets();
            $wallet->setUtilisateur($user);
            $wallet->setBalance(0);
            $wallet->setAutoPayEnabled(false);
            $wallet->setUpdatedAt(new \DateTimeImmutable());
            
            $em->persist($wallet);
            $em->flush();
        }
        
        $transactions = $em->getRepository(WalletTransactions::class)->findBy(['wallet' => $wallet]);
        $recentTransactions = $em->getRepository(WalletTransactions::class)->findBy(
            ['wallet' => $wallet], 
            ['createdAt' => 'DESC'], 
            5
        );
        
        return $this->render('dashboard/pages/wallet/index.html.twig', [
            'wallet' => $wallet,
            'transactions' => $transactions,
            'recentTransactions' => $recentTransactions,
            'totalDeposited' => array_sum(array_map(fn($t) => $t->getAmount(), $transactions))
        ]);
    }

    #[Route('/wallet/deposit', name: 'app_wallet_deposit')]
    public function deposit(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $wallet = $em->getRepository(Wallets::class)->findOneBy(['utilisateur' => $user]);
        
        // If wallet doesn't exist, create a new one
        if (!$wallet) {
            $wallet = new Wallets();
            $wallet->setUtilisateur($user);
            $wallet->setBalance(0);
            $wallet->setAutoPayEnabled(false);
            $wallet->setUpdatedAt(new \DateTimeImmutable());
            
            $em->persist($wallet);
            $em->flush();
        }
        
        $transactions = $em->getRepository(WalletTransactions::class)->findBy(['wallet' => $wallet]);
        $recentTransactions = $em->getRepository(WalletTransactions::class)->findBy(
            ['wallet' => $wallet], 
            ['createdAt' => 'DESC'], 
            5
        );
        
        return $this->render('dashboard/pages/wallet/index.html.twig', [
            'wallet' => $wallet,
            'transactions' => $transactions,
            'recentTransactions' => $recentTransactions,
            'totalDeposited' => array_sum(array_map(fn($t) => $t->getAmount(), $transactions))
        ]);
    }
    
    #[Route('/wallet/autopay', name: 'app_wallet_toggle_autopay')]
    public function autopay(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $wallet = $em->getRepository(Wallets::class)->findOneBy(['utilisateur' => $user]);
        
        // If wallet doesn't exist, create a new one with auto-pay enabled by default
        if (!$wallet) {
            $wallet = new Wallets();
            $wallet->setUtilisateur($user);
            $wallet->setBalance(0);
            $wallet->setAutoPayEnabled(true); // Enable auto-pay by default for new wallets
            $wallet->setUpdatedAt(new \DateTimeImmutable());
            
            $em->persist($wallet);
            $em->flush();
            
            return $this->json([
                'success' => true,
                'enabled' => true,
                'message' => 'Wallet created with auto-pay enabled'
            ]);
        }
        
        // Toggle auto-pay for existing wallet
        $wallet->setAutoPayEnabled(!$wallet->isAutoPayEnabled());
        $em->flush();
        
        return $this->json([
            'success' => true,
            'enabled' => $wallet->isAutoPayEnabled()
        ]);
    }
      
    


}
