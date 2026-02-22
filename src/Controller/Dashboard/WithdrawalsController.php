<?php

namespace App\Controller\Dashboard;

use App\Entity\User;
use App\Entity\Tontine;
use App\Entity\Withdrawals;
use App\Service\ActivityLogger;
use App\Form\WithdrawalRequestType;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\WithdrawalsRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[IsGranted('ROLE_USER')]
#[Route('/dashboard')]
final class WithdrawalsController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ActivityLogger $activityLogger,
        private \App\Service\NotificationService $notificationService,
        private \App\Service\EmailService $emailService
    ) {}

    #[Route('/withdrawals', name: 'app_withdrawals_index', methods: ['GET'])]
    public function index(Request $request, \Knp\Component\Pager\PaginatorInterface $paginator): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $query = $this->em->getRepository(Withdrawals::class)
            ->createQueryBuilder('w')
            ->where('w.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('w.requestedAt', 'DESC')
            ->getQuery();

        $withdrawals = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('dashboard/pages/withdrawals/index.html.twig', [
            'withdrawals' => $withdrawals,
        ]);
    }

    /**
     * Affiche le formulaire de demande de retrait et traite sa soumission
     */
    #[Route('/withdrawals/request/{id}', name: 'app_withdrawal_request', methods: ['GET', 'POST'])]
    public function requestWithdrawal(Request $request, $id, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $tontine = $em->getRepository(Tontine::class)->find((int)$id);

        /** @var User $user */
        // Vérifier si la tontine existe
        if (!$tontine) {
            $this->addFlash('error', 'Tontine avec l\'ID ' . $id . ' non trouvée.');
            return $this->redirectToRoute('app_tontines_index');
        }

        // Vérifier si l'utilisateur est propriétaire
        if ($tontine->getUtilisateur()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Accès refusé à cette tontine.');
            return $this->redirectToRoute('app_tontines_index');
        }

        // Vérifier si le compte est vérifié (vérification de l'identité)
        if ($user->getVerificationStatus() !== 'Vérifié') {
            $this->addFlash('error', 'Veuillez d\'abord faire vérifier votre compte avant de pouvoir effectuer un retrait.');
            return $this->redirectToRoute('app_tontines_show', ['id' => $tontine->getId()]);
        }

        /**Vérifier si la tontine est complétée
        if ($tontine->getStatut() !== 'completed') {
            $this->addFlash('error', 'La tontine doit être complétée avant de pouvoir effectuer un retrait.');
            return $this->redirectToRoute('app_tontines_show', ['id' => $tontine->getId()]);
        }**/ 


        // Si on arrive ici, c'est que les frais sont payés, on peut continuer avec la demande de retrait
        $form = $this->createForm(WithdrawalRequestType::class, null, [
            'tontine' => $tontine,
            'action' => $this->generateUrl('app_withdrawal_request', ['id' => $tontine->getId()])
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            
            // Calculer le montant à retirer
            $grossAmount = ($data['withdrawal_type'] === 'tontine')
                ? ($tontine->getTotalPay() - $tontine->getWithdrawnAmount())
                : $data['custom_amount'];

            $netAmount = ($data['withdrawal_type'] === 'tontine')
                ? $tontine->getAvailableWithdrawalAmount()
                : $data['custom_amount'];

            if ($netAmount <= 0) {
                $this->addFlash('error', 'Le montant du retrait doit être supérieur à 0.');
                return $this->redirectToRoute('app_tontines_show', ['id' => $tontine->getId()]);
            }

            // Vérifier que le montant demandé ne dépasse pas le montant total cotisé
            if ($grossAmount > $tontine->getTotalPay()) {
                $this->addFlash('error', 'Le montant du retrait ne peut pas dépasser le montant total cotisé.');
                return $this->redirectToRoute('app_withdrawal_request', ['id' => $tontine->getId()]);
            }

            // Vérifier s'il y a déjà une demande en cours
            $demandeEnCours = $em->getRepository(Withdrawals::class)->findOneBy([
                'tontine' => $tontine,
                'statut' => 'pending',
                'utilisateur' => $user
            ]);

            if ($demandeEnCours) {
                $this->addFlash('warning', 'Une demande de retrait est déjà en attente pour cette tontine.');
                return $this->redirectToRoute('app_tontines_show', ['id' => $tontine->getId()]);
            }

            // Stocker les données en session pour la confirmation après PIN
            $request->getSession()->set('pending_withdrawal', [
                'tontine_id' => $tontine->getId(),
                'amount' => $netAmount,
                'gross_amount' => $grossAmount,
                'method' => $data['withdrawal_method'] ?? 'mobile_money',
                'phone_number' => $data['phone_number'] ?? null,
                'withdrawal_type' => $data['withdrawal_type'],
                'fee_payment_method' => $data['fee_payment_method'] ?? null
            ]);

            // Définir le chemin de retour après vérification du PIN
            $request->getSession()->set('_security.main.target_path', $this->generateUrl('app_withdrawal_confirm'));

            return $this->redirectToRoute('pin_verify');
        }

        return $this->render('dashboard/pages/withdrawals/request.html.twig', [
            'form' => $form->createView(),
            'tontine' => $tontine,
            'availableAmount' => $tontine->getAvailableWithdrawalAmount()
        ]);
    }

    /**
     * Finalise la demande de retrait après vérification du PIN
     */
    #[Route('/withdrawals/confirm', name: 'app_withdrawal_confirm', methods: ['GET'])]
    public function confirmWithdrawal(Request $request, EntityManagerInterface $em): Response
    {
        $session = $request->getSession();
        
        // Vérifier si le PIN a été validé récemment
        if (!$session->get('pin_verified')) {
            $this->addFlash('error', 'Veuillez d\'abord valider votre identité avec votre code PIN.');
            return $this->redirectToRoute('app_withdrawals_index');
        }

        $pendingData = $session->get('pending_withdrawal');
        if (!$pendingData) {
            $this->addFlash('error', 'Aucune demande de retrait en attente.');
            return $this->redirectToRoute('app_withdrawals_index');
        }

        /** @var User $user */
        $user = $this->getUser();
        $tontine = $em->getRepository(Tontine::class)->find($pendingData['tontine_id']);

        if (!$tontine || $tontine->getUtilisateur() !== $user) {
            $session->remove('pending_withdrawal');
            $this->addFlash('error', 'Données de retrait invalides.');
            return $this->redirectToRoute('app_tontines_index');
        }

        $amount = $pendingData['amount'];
        $grossAmount = $pendingData['gross_amount'] ?? $amount;

        try {
            // Créer la demande de retrait
            $withdrawal = new Withdrawals();
            $withdrawal->setUtilisateur($user);
            $withdrawal->setTontine($tontine);
            $withdrawal->setAmount((string)$amount);
            $withdrawal->setTotalAmount((string)$grossAmount);
            $withdrawal->setMethod($pendingData['method']);
            // Si numéro de téléphone présent, on peut le stocker (si l'entité le supporte, sinon dans raison)
            $reason = 'Demande de retrait de ' . number_format($amount, 0, ',', ' ') . ' FCFA';
            if ($pendingData['phone_number']) {
                $reason .= ' (Vers: ' . $pendingData['phone_number'] . ')';
            }
            $withdrawal->setReason($reason);
            $withdrawal->setStatut('pending');
            $withdrawal->setRequestedAt(new \DateTimeImmutable());

            // Effectuer le retrait du montant demandé
            $tontine->withdraw($amount);
            
            // Collecter les frais de service proratisés
            $feesDueAtThisStage = $tontine->getFeesDue();
            
            if ($feesDueAtThisStage > 0) {
                // On ne prélève que ce qui est disponible si le solde est insuffisant (et si > 0)
                $actualFee = min($feesDueAtThisStage, $tontine->getTotalPay());
                
                if ($actualFee > 0) {
                    // 1. Enregistrement dans PlatformFee (Admin)
                    $platformFee = new \App\Entity\PlatformFee();
                    $platformFee->setUser($user);
                    $platformFee->setTontine($tontine);
                    $platformFee->setAmount((int)$actualFee);
                    $platformFee->setType('service_fee');
                    $platformFee->setStatus('collected');
                    $platformFee->setCreatedAt(new \DateTimeImmutable());
                    $em->persist($platformFee);

                    // 2. Enregistrement dans Transaction (Visibilité Utilisateur)
                    $feeTransaction = new \App\Entity\Transaction();
                    $feeTransaction->setTontine($tontine);
                    $feeTransaction->setUtilisateur($user);
                    $feeTransaction->setAmount((string)$actualFee);
                    $feeTransaction->setType('frais_service');
                    $feeTransaction->setPaymentMethod('deduction');
                    $feeTransaction->setProvider('system');
                    $feeTransaction->setStatut('completed');
                    $feeTransaction->setCreatedAt(new \DateTimeImmutable());
                    $em->persist($feeTransaction);

                    // 3. Mettre à jour les frais payés sur la tontine
                    $tontine->setPaidFees($tontine->getPaidFees() + (int)$actualFee);

                    // 4. Log de la transaction de frais
                    $this->activityLogger->log(
                        $user,
                        'FEE_DEDUCTION',
                        'Transaction',
                        $feeTransaction->getId(),
                        sprintf(
                            'Prélèvement automatique des frais de service : %s FCFA pour la tontine %s',
                            number_format($actualFee, 0, ',', ' '),
                            $tontine->getName()
                        )
                    );
                }
            }

            $em->persist($withdrawal);
            $em->flush();

            // Notification in-app
            $this->notificationService->sendWithdrawalRequestNotification(
                $user,
                (float) $amount
            );

            // Notification Email
            $this->emailService->sendWithdrawalRequestEmail($user, (float) $amount);

            // Mettre à jour le statut de la tontine si nécessaire
            if ($tontine->isComplete()) {
                $tontine->setStatut('completed');
                $tontine->setEndedAt(new \DateTimeImmutable());
                $em->persist($tontine);
                $em->flush();
            }

            // Nettoyer la session
            $session->remove('pending_withdrawal');
            // On garde pin_verified pour le reste de la session ou on peut le reset
            // $session->remove('pin_verified'); 

            $this->addFlash('success', 'Votre demande de retrait a été validée avec succès après vérification PIN.');

            // Log de l'activité
            $this->activityLogger->log(
                $user,
                'WITHDRAWAL_REQUEST',
                'Withdrawal',
                $withdrawal->getId(),
                sprintf(
                    'Retrait validé par PIN - Montant: %s FCFA',
                    number_format($amount, 0, ',', ' ')
                )
            );

            return $this->redirectToRoute('app_withdrawals_index');

        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur est survenue lors de la finalisation : ' . $e->getMessage());
            return $this->redirectToRoute('app_withdrawal_request', ['id' => $tontine->getId()]);
        }
    }


    /**
     * Affiche la page de succès après une demande de retrait
     */
    #[Route('/withdrawals/success/{id}', name: 'app_withdrawal_success', methods: ['GET'])]
    public function withdrawalSuccess(int $id, WithdrawalsRepository $withdrawalsRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $withdrawal = $withdrawalsRepo->find($id);
        
        if (!$withdrawal) {
            throw $this->createNotFoundException('Demande de retrait non trouvée');
        }
        
        // Vérifier que l'utilisateur est bien le propriétaire
        if ($withdrawal->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Accès non autorisé.');
        }
        
        return $this->render('dashboard/pages/withdrawals/success.html.twig', [
            'withdrawal' => $withdrawal
        ]);
    }

    #[Route('/withdrawals/{id}', name: 'app_withdrawal_show', methods: ['GET'])]
    public function show(int $id, WithdrawalsRepository $withdrawalsRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $withdrawal = $withdrawalsRepo->find($id);
        
        if (!$withdrawal || $withdrawal->getUtilisateur() !== $this->getUser()) {
            throw $this->createNotFoundException('Demande de retrait non trouvée');
        }
        
        return $this->render('dashboard/pages/withdrawals/_details.html.twig', [
            'withdrawal' => $withdrawal
        ]);
    }
}
