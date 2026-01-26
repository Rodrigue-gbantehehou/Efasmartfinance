<?php

namespace App\Controller\Dashboard;

use App\Entity\User;
use App\Entity\Tontine;
use App\Entity\Withdrawals;
use App\Entity\PlatformFee;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Form\WithdrawalRequestType;
use App\Repository\WithdrawalsRepository;

#[IsGranted('ROLE_USER')]
#[Route('/dashboard')]
final class WithdrawalsController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ActivityLogger $activityLogger
    ) {}

    #[Route('/withdrawals', name: 'app_withdrawals_index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Récupérer les demandes de retrait de l'utilisateur triées par date décroissante
        $withdrawals = $this->em->getRepository(Withdrawals::class)->findBy(
            ['utilisateur' => $user],
            ['requestedAt' => 'DESC']
        );

        return $this->render('dashboard/pages/withdrawals/index.html.twig', [
            'withdrawals' => $withdrawals,
        ]);
    }

    /**
     * Affiche le formulaire de demande de retrait et traite sa soumission
     */
    #[Route('/withdrawals/request/{id}', name: 'app_withdrawal_request', methods: ['GET', 'POST'])]
    public function requestWithdrawal(Request $request, int $id, EntityManagerInterface $em): Response
    {
        // Récupérer la tontine par son ID
        $tontine = $em->getRepository(Tontine::class)->find($id);

        if (!$tontine) {
            throw $this->createNotFoundException('La tontine demandée n\'existe pas');
        }

        // Vérifier si l'utilisateur est autorisé via le Voter
        if (!$this->isGranted('WITHDRAW', $tontine)) {
            $user = $this->getUser();
            
            if ($tontine->getUtilisateur() !== $user) {
                $this->addFlash('error', 'Vous n\'êtes pas autorisé à effectuer un retrait pour cette tontine.');
            } elseif ($tontine->getTotalPay() <= 0) {
                $this->addFlash('error', 'Aucun montant disponible pour un retrait dans cette tontine.');
            } elseif (!in_array($tontine->getStatut(), ['active', 'completed'])) {
                $this->addFlash('error', 'Les retraits ne sont autorisés que pour les tontines actives ou terminées.');
            } else {
                // Vérifier s'il y a déjà une demande en cours
                $demandeEnCours = $this->em->getRepository(Withdrawals::class)->findOneBy([
                    'tontine' => $tontine,
                    'statut' => 'pending',
                    'utilisateur' => $user
                ]);
                
                if ($demandeEnCours) {
                    $this->addFlash('error', 'Une demande de retrait est déjà en attente pour cette tontine.');
                } else {
                    $this->addFlash('error', 'Vous n\'êtes pas autorisé à effectuer un retrait pour le moment.');
                }
            }
            
            return $this->redirectToRoute('app_tontines_show', ['id' => $tontine->getId()]);
        }

        // Vérifier si des frais doivent être payés
        $fees = 0;
        $showFeeWarning = false;

        if (!$tontine->isFraisPreleves()) {
            $fees = $this->calculateFees(
                $tontine->getFrequency(),
                $tontine->getAmountPerPoint(),
                $tontine->getTotalPoints()
            );
            $showFeeWarning = true;

            // Si c'est une requête AJAX pour vérifier le statut des frais
            if ($request->isXmlHttpRequest() && $request->query->has('check_fees')) {
                return new JsonResponse([
                    'has_paid_fees' => false,
                    'fees' => $fees,
                    'tontine_id' => $tontine->getId()
                ]);
            }

            // Si l'utilisateur n'a pas encore payé les frais, on affiche la modale
            if (!$request->isMethod('POST')) {
                return $this->render('dashboard/pages/withdrawals/request.html.twig', [
                    'tontine' => $tontine,
                    'fees' => $fees,
                    'show_fee_warning' => $showFeeWarning,
                    'form' => $this->createForm(WithdrawalRequestType::class, null, [
                        'tontine' => $tontine,
                        'show_fee_warning' => $showFeeWarning,
                        'action' => $this->generateUrl('app_withdrawal_request', ['id' => $tontine->getId()])
                    ])->createView(),
                    'availableAmount' => $tontine->getAvailableWithdrawalAmount()
                ]);
            }
        } else if ($request->isXmlHttpRequest() && $request->query->has('check_fees')) {
            // Répondre en JSON pour les requêtes AJAX
            return new JsonResponse([
                'has_paid_fees' => true,
                'tontine_id' => $tontine->getId()
            ]);
        }

        $form = $this->createForm(WithdrawalRequestType::class, null, [
            'tontine' => $tontine,
            'show_fee_warning' => $showFeeWarning,
            'action' => $this->generateUrl('app_withdrawal_request', ['id' => $tontine->getId()])
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Traitement de la soumission du formulaire
            $data = $form->getData();



            // Calculer le montant à retirer
            $amount = ($data['withdrawal_type'] === 'tontine')
                ? $tontine->getAvailableWithdrawalAmount()
                : $data['custom_amount'];

            // Vérifier si le montant est valide
            if ($amount <= 0) {
                $this->addFlash('error', 'Le montant du retrait doit être supérieur à 0.');
                return $this->redirectToRoute('app_tontines_show', ['id' => $tontine->getId()]);
            }

            // Vérifier que le montant demandé ne dépasse pas le montant total cotisé
            $montantTotalCotise = $tontine->getTotalPay();
            if ($amount > $montantTotalCotise) {
                $this->addFlash('error', 'Le montant du retrait ne peut pas dépasser le montant total cotisé de ' . $montantTotalCotise . ' FCFA.');
                return $this->redirectToRoute('app_withdrawal_request', ['id' => $tontine->getId()]);
            }

            // Vérifier s'il y a déjà une demande en cours
            $demandeEnCours = $em->getRepository(Withdrawals::class)->findOneBy([
                'tontine' => $tontine,
                'statut' => 'pending',
                'utilisateur' => $this->getUser()
            ]);

            if ($demandeEnCours) {
                $this->addFlash('warning', 'Une demande de retrait est déjà en attente pour cette tontine.');
                return $this->redirectToRoute('app_tontines_show', ['id' => $tontine->getId()]);
            }

            try {
                // Vérifier si c'est le premier retrait pour cette tontine
                $fees = 0;
                if (!$tontine->isFraisPreleves()) {
                    // Utiliser le montant par point comme frais pour le premier retrait
                    $fees = $this->calculateFees(
                        $tontine->getFrequency(),      // daily | weekly | monthly
                        $tontine->getAmountPerPoint(),    // montant de base
                        $tontine->getTotalPoints()   // durée en mois
                    );
                    // Calculate fees

                    // Créer l'écriture comptable pour les frais
                    $fee = new PlatformFee();
                    $fee->setUser($this->getUser());
                    $fee->setTontine($tontine);
                    $fee->setAmount($fees);
                    $fee->setType('withdrawal_fee');
                    $fee->setStatus('collected');
                    $em->persist($fee);

                    // Marquer la tontine comme ayant eu un retrait avec frais
                    $tontine->setFraisPreleves(true);

                    // Ajouter un message pour informer l'utilisateur des frais
                    $this->addFlash('info', 'Des frais de retrait uniques de ' . number_format($fees, 0, ',', ' ') . ' FCFA ont été appliqués (montant par point de la tontine).');
                }

                // Créer une nouvelle demande de retrait avec le montant demandé par l'utilisateur
                $withdrawal = new Withdrawals();
                $withdrawal->setUtilisateur($this->getUser());
                $withdrawal->setTontine($tontine);
                $withdrawal->setAmount($amount); // Montant que l'utilisateur recevra
                $withdrawal->setTotalAmount($amount); // Montant total du retrait (sans les frais)

                // Effectuer le retrait du montant demandé (sans les frais)
                $tontine->withdraw($amount);
                $withdrawal->setMethod($data['payment_method'] ?? 'mobile_money');
                $withdrawal->setStatut('pending');
                $withdrawal->setRequestedAt(new \DateTimeImmutable());
                // Set the reason if it exists in the form data
                // Set the reason from the form data or use a default message
                $reason =  'Demande de retrait de ' . number_format($amount, 0, ',', ' ') . ' FCFA';
                if (!empty($reason)) {
                    $withdrawal->setReason($reason);
                }
                // Lier les frais au retrait si c'est le premier retrait
                if (isset($fee)) {
                    $fee->setWithdrawal($withdrawal);
                }

                $em->persist($withdrawal);
                $em->flush();

                // ⚡ Important : ne jamais clôturer la tontine ici si elle est encore active
                // On ne clôture que si la tontine est réellement terminée par la date ou le nombre de points
                if ($tontine->getStatut() === 'active' && $tontine->isComplete()) {
                    $tontine->setStatut('completed');
                    $tontine->setEndedAt(new \DateTimeImmutable());
                    $em->persist($tontine);

                    $withdrawal->setReason($withdrawal->getReason() . ' - Tontine clôturée');
                    $message = 'Le retrait a été effectué avec succès et la tontine a été clôturée.';
                } else {
                    $message = 'Le retrait a été effectué avec succès.';
                }

                $this->addFlash('success', $message);

                // Log de l'activité
                $this->activityLogger->log(
                    $this->getUser(),
                    'WITHDRAWAL_REQUEST',
                    'Withdrawal',
                    $withdrawal->getId(),
                    sprintf(
                        'Nouvelle demande de retrait - Total: %s FCFA | Net: %s FCFA',
                        number_format($withdrawal->getTotalAmount(), 0, ',', ' '),
                        number_format($amount, 0, ',', ' ')
                    )
                );

                return $this->redirectToRoute('app_tontines_show', ['id' => $tontine->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('app_withdrawal_request', ['id' => $tontine->getId()]);
            }
        }

        // Afficher le formulaire
        // Calculer les frais si nécessaire
        $fees = 0;
        $showFeeWarning = false;

        if (!$tontine->isFraisPreleves()) {
            $fees = $this->calculateFees(
                $tontine->getFrequency(),
                $tontine->getAmountPerPoint(),
                $tontine->getTotalPoints()
            );
            $showFeeWarning = true;
        }

        return $this->render('dashboard/pages/withdrawals/request.html.twig', [
            'tontine' => $tontine,
            'form' => $form->createView(),
            'availableAmount' => $tontine->getAvailableWithdrawalAmount(),
            'fees' => $fees,
            'show_fee_warning' => $showFeeWarning
        ]);
    }

    /**
     * Vérifie le paiement KkiaPay et met à jour le statut des frais
     */
    #[Route('/withdrawals/verify-payment/{id}', name: 'app_withdrawal_verify_payment', methods: ['GET', 'POST'])]
    public function verifyKkiapayPayment(Request $request, int $id): Response
    {
        $tontine = $this->em->getRepository(Tontine::class)->find($id);

        if (!$tontine) {
            throw $this->createNotFoundException('La tontine demandée n\'existe pas');
        }

        // Vérifier si c'est le premier retrait et que les frais n'ont pas encore été payés
        if ($tontine->isFraisPreleves()) {
            $this->addFlash('info', 'Les frais de retrait ont déjà été payés pour cette tontine.');
            return $this->redirectToRoute('app_withdrawal_request', ['id' => $tontine->getId()]);
        }

        // Récupérer les paramètres de la requête KkiaPay
        $transactionId = $request->query->get('transaction_id');
        $status = $request->query->get('status');

        if ($status === 'SUCCESS' && $transactionId) {
            try {
                // Calculer les frais
                $fees = $this->calculateFees(
                    $tontine->getFrequency(),
                    $tontine->getAmountPerPoint(),
                    $tontine->getTotalPoints()
                );

                // Créer l'écriture comptable pour les frais
                $fee = new PlatformFee();
                $fee->setUser($this->getUser());
                $fee->setTontine($tontine);
                $fee->setAmount($fees);
                $fee->setType('withdrawal_fee');
                $fee->setStatus('paid');
                $fee->setTransactionId($transactionId);

                // Marquer la tontine comme ayant payé les frais
                $tontine->setFraisPreleves(true);

                $this->em->persist($fee);
                $this->em->flush();

                $this->addFlash('success', 'Paiement des frais de retrait effectué avec succès !');

                // Rediriger vers la page de demande de retrait
                return $this->redirectToRoute('app_withdrawal_request', ['id' => $tontine->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors du traitement du paiement : ' . $e->getMessage());
                return $this->redirectToRoute('app_tontines_show', ['id' => $tontine->getId()]);
            }
        } else {
            $this->addFlash('error', 'Le paiement a échoué ou a été annulé.');
            return $this->redirectToRoute('app_tontines_show', ['id' => $tontine->getId()]);
        }
    }

    private function calculateFees(string $type, float $amount, ?int $duration): float
    {
        // Si la durée est null ou zéro, on utilise 1 comme valeur par défaut (1 mois)
        $duration = $duration ?? 1;

        if (!$type || !$amount || $duration <= 0) {
            return 0;
        }

        switch ($type) {
            case 'daily':
                // 1000/jour → 1 mois = 1000 | 3 mois = 3000
                return $amount * $duration;

            case 'weekly':
                // (montant × 4 / 30) × nombre de mois
                return ($amount * 4 / 30) * $duration;

            case 'monthly':
                // montant / 30 × nombre de mois
                return ($amount / 30) * $duration;

            default:
                return 0;
        }
    }

    /**
     * Traite le paiement des frais de retrait
     */
    #[Route('/withdrawals/process-fee', name: 'app_withdrawal_process_fee', methods: ['POST'])]
    public function processFeePayment(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var User $user */
        $user = $this->getUser();

        // Récupérer les données du formulaire
        $tontineId = $request->request->get('withdrawal_request')['tontine'] ?? null;
        $amount = $request->request->get('withdrawal_request')['amount'] ?? null;
        $withdrawalType = $request->request->get('withdrawal_request')['withdrawal_type'] ?? null;
        $withdrawalMethod = $request->request->get('withdrawal_request')['withdrawal_method'] ?? null;
        $phoneNumber = $request->request->get('withdrawal_request')['phone_number'] ?? null;

        // Valider les données
        if (!$tontineId || !$amount || !$withdrawalType || !$withdrawalMethod) {
            return $this->json([
                'success' => false,
                'message' => 'Données de requête incomplètes.'
            ], 400);
        }

        // Récupérer la tontine
        $tontine = $em->getRepository(Tontine::class)->find($tontineId);
        if (!$tontine) {
            return $this->json([
                'success' => false,
                'message' => 'Tontine non trouvée.'
            ], 404);
        }

        // Vérifier si l'utilisateur est autorisé
        $this->denyAccessUnlessGranted('WITHDRAW', $tontine, 'Accès non autorisé à cette fonctionnalité.');

        try {
            // Créer une nouvelle demande de retrait
            $withdrawal = new Withdrawals();
            $withdrawal->setUtilisateur($user);
            $withdrawal->setTontine($tontine);
            $withdrawal->setAmount($amount);
            $withdrawal->setWithdrawalType($withdrawalType);
            $withdrawal->setWithdrawalMethod($withdrawalMethod);
            $withdrawal->setPhoneNumber($phoneNumber);
            $withdrawal->setStatut('pending_fee_payment');
            $withdrawal->setRequestedAt(new \DateTimeImmutable());

            $em->persist($withdrawal);
            $em->flush();

            // Enregistrer l'activité
            $this->activityLogger->log(
                $user,
                'WITHDRAWAL_FEE_CREATED',
                'Withdrawal',
                $withdrawal->getId(),
                'Nouvelle demande de retrait avec frais en attente de paiement - Montant: ' . number_format($amount, 0, ',', ' ') . ' FCFA'
            );

            return $this->json([
                'success' => true,
                'withdrawal_id' => $withdrawal->getId(),
                'message' => 'Demande de retrait créée avec succès.'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création de la demande de retrait.'
            ], 500);
        }
    }

    /**
     * Vérifie le statut du paiement des frais
     */
    #[Route('/withdrawals/verify-fee/{id}', name: 'app_withdrawal_verify_fee', methods: ['GET'])]
    public function verifyFeePayment(string $id, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        // Récupérer la demande de retrait
        $withdrawal = $em->getRepository(Withdrawals::class)->find($id);

        if (!$withdrawal) {
            $this->addFlash('error', 'Demande de retrait non trouvée.');
            return $this->redirectToRoute('app_dashboard');
        }

        // Vérifier que l'utilisateur est bien le propriétaire
        if ($withdrawal->getUtilisateur() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Accès non autorisé.');
        }

        // Récupérer les paramètres de la requête KkiaPay
        $transactionId = $request->query->get('transaction_id');
        $status = $request->query->get('status');

        if ($status === 'SUCCESS' && $transactionId) {
            try {
                // Calculer les frais
                $fees = $this->calculateFees(
                    $withdrawal->getTontine()->getFrequency(),
                    $withdrawal->getTontine()->getAmountPerPoint(),
                    $withdrawal->getTontine()->getTotalPoints()
                );

                // Mettre à jour le statut du retrait
                $withdrawal->setStatut('pending_approval');
                $withdrawal->setFeePaid(true);
                $withdrawal->setFeePaymentDate(new \DateTimeImmutable());
                $withdrawal->setTransactionId($transactionId);

                // Créer l'écriture comptable pour les frais
                $fee = new PlatformFee();
                $fee->setUser($this->getUser());
                $fee->setTontine($withdrawal->getTontine());
                $fee->setAmount($fees);
                $fee->setType('withdrawal_fee');
                $fee->setStatus('collected');
                $fee->setWithdrawal($withdrawal);
                $fee->setTransactionId($transactionId);

                $em->persist($fee);

                // Marquer les frais comme prélevés pour cette tontine
                $tontine = $withdrawal->getTontine();
                $tontine->setFraisPreleves(true);

                $em->flush();

                // Enregistrer l'activité
                $this->activityLogger->log(
                    $this->getUser(),
                    'WITHDRAWAL_FEE_PAID',
                    'Withdrawal',
                    $withdrawal->getId(),
                    'Frais de retrait payés avec succès - Montant: ' . number_format($fees, 0, ',', ' ') . ' FCFA - Transaction: ' . $transactionId
                );

                $this->addFlash('success', 'Le paiement des frais a été effectué avec succès. Votre demande de retrait est en cours de traitement.');
                return $this->redirectToRoute('app_withdrawal_success', ['id' => $withdrawal->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors du traitement du paiement.');
                return $this->redirectToRoute('app_withdrawal_request', ['id' => $withdrawal->getTontine()->getId()]);
            }
        } else {
            // Échec du paiement
            $withdrawal->setStatut('fee_payment_failed');
            $em->flush();

            $this->addFlash('error', 'Le paiement des frais a échoué. Veuillez réessayer ou contacter le support.');
            return $this->redirectToRoute('app_withdrawal_request', ['id' => $withdrawal->getTontine()->getId()]);
        }
    }

    /**
     * Affiche la page de succès après paiement des frais
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

    #[Route('/withdrawal/prepare', name: 'app_withdrawal_prepare', methods: ['POST'])]
    public function prepare(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        // Get tontine ID from request data
        $tontineId = $request->request->get('tontine_id');
        if (!$tontineId) {
            return $this->json([
                'success' => false,
                'message' => 'ID de la tontine manquant dans la requête.'
            ], 400);
        }

        // Find the tontine
        $tontine = $em->getRepository(Tontine::class)->find($tontineId);
        if (!$tontine) {
            return $this->json([
                'success' => false,
                'message' => 'Tontine non trouvée.'
            ], 404);
        }

        // Vérifier si l'utilisateur est autorisé
        $this->denyAccessUnlessGranted('WITHDRAW', $tontine, 'Accès non autorisé à cette fonctionnalité.');

        // Create form with tontine option
        $form = $this->createForm(WithdrawalRequestType::class, null, [
            'tontine' => $tontine,
            'show_fee_warning' => $request->request->getBoolean('show_fee_warning', false)
        ]);

        $form->handleRequest($request);

        if (!$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[$error->getOrigin()->getName()] = $error->getMessage();
            }

            return $this->json([
                'success' => false,
                'message' => 'Formulaire invalide',
                'errors' => $errors
            ], 422);
        }

        $data = $form->getData();

        // Calculer le montant
        $amount = ($data['withdrawal_type'] === 'tontine')
            ? $tontine->getAvailableWithdrawalAmount()
            : $data['custom_amount'];

        // Vérifier que le montant est valide
        if ($amount <= 0) {
            return $this->json([
                'success' => false,
                'message' => 'Le montant du retrait doit être supérieur à 0.'
            ], 400);
        }

        // Vérifier que le montant demandé ne dépasse pas le montant total cotisé
        $montantTotalCotise = $tontine->getTotalPay();
        if ($amount > $montantTotalCotise) {
            return $this->json([
                'success' => false,
                'message' => 'Le montant du retrait ne peut pas dépasser le montant total cotisé de ' . $montantTotalCotise . ' FCFA.'
            ], 400);
        }

        // Vérifier s'il y a déjà une demande en cours
        $demandeEnCours = $em->getRepository(Withdrawals::class)->findOneBy([
            'tontine' => $tontine,
            'statut' => 'pending',
            'utilisateur' => $this->getUser()
        ]);

        if ($demandeEnCours) {
            return $this->json([
                'success' => false,
                'message' => 'Une demande de retrait est déjà en attente pour cette tontine.'
            ], 400);
        }

        // Calculer les frais
        $fees = $this->calculateFees(
            $tontine->getFrequency(),
            $tontine->getAmountPerPoint(),
            $tontine->getTotalPoints()
        );

        // Déterminer si le paiement est requis
        $requiresPayment = !$tontine->isFraisPreleves();

        // Créer la demande de retrait avec statut approprié
        $withdrawal = new Withdrawals();
        $withdrawal->setUtilisateur($this->getUser());
        $withdrawal->setTontine($tontine);
        $withdrawal->setAmount($amount);
        $withdrawal->setTotalAmount($amount);
        $withdrawal->setMethod($data['withdrawal_method'] ?? 'mobile_money');

        // Définir le statut selon si les frais doivent être payés
        $withdrawal->setStatut($requiresPayment ? 'pending' : 'pending');

        $withdrawal->setRequestedAt(new \DateTimeImmutable());

        // Définir le type de retrait
        $withdrawal->setWithdrawalType($data['withdrawal_type']);

        // Gérer le numéro de téléphone si mobile money
        if ($data['withdrawal_method'] === 'mobile_money' && isset($data['phone_number'])) {
            $withdrawal->setPhoneNumber($data['phone_number']);
        }

        // Définir la raison
        $reason = 'Demande de retrait de ' . number_format($amount, 0, ',', ' ') . ' FCFA';
        $withdrawal->setReason($reason);

        $em->persist($withdrawal);
        $em->flush();

        // Enregistrer l'activité
        $this->activityLogger->log(
            $this->getUser(),
            'WITHDRAWAL_CREATED',
            'Withdrawal',
            $withdrawal->getId(),
            'Nouvelle demande de retrait créée - Montant: ' . number_format($amount, 0, ',', ' ') . ' FCFA' .
                ($requiresPayment ? ' (frais en attente de paiement)' : '')
        );

        return $this->json([
            'success' => true,
            'withdrawal_id' => $withdrawal->getId(), // ID réel de la base de données
            'fees' => $fees,
            'currency' => 'XOF',
            'requires_payment' => $requiresPayment
        ]);
    }
    #[Route('/withdrawal/confirm-fee', name: 'app_withdrawal_confirm_fee', methods: ['POST'])]
    public function confirmFee(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $data = json_decode($request->getContent(), true);
        $withdrawalId = $data['withdrawal_id'] ?? null;
        $transactionId = $data['transaction_id'] ?? null;

        if (!$withdrawalId || !$transactionId) {
            return $this->json([
                'success' => false,
                'message' => 'Données manquantes'
            ], 400);
        }

        // Récupérer le retrait
        $withdrawal = $em->getRepository(Withdrawals::class)->find($withdrawalId);

        if (!$withdrawal) {
            return $this->json([
                'success' => false,
                'message' => 'Demande de retrait non trouvée'
            ], 404);
        }

        // Vérifier que l'utilisateur est bien le propriétaire
        if ($withdrawal->getUtilisateur() !== $this->getUser()) {
            return $this->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        try {
            // Calculer les frais
            $fees = $this->calculateFees(
                $withdrawal->getTontine()->getFrequency(),
                $withdrawal->getTontine()->getAmountPerPoint(),
                $withdrawal->getTontine()->getTotalPoints()
            );

            // Mettre à jour le statut du retrait
            $withdrawal->setStatut('pending');
            $withdrawal->setFeePaid(true);
            $withdrawal->setFeePaymentDate(new \DateTimeImmutable());
            $withdrawal->setTransactionId($transactionId);

            // Créer l'écriture comptable pour les frais
            $fee = new PlatformFee();
            $fee->setUser($this->getUser());
            $fee->setTontine($withdrawal->getTontine());
            $fee->setAmount($fees);
            $fee->setType('withdrawal_fee');
            $fee->setStatus('collected');
            $fee->setWithdrawal($withdrawal);
            

            $em->persist($fee);

            // Marquer les frais comme prélevés pour cette tontine
            $tontine = $withdrawal->getTontine();
            $tontine->setFraisPreleves(true);

            // Effectuer le retrait du montant demandé
            $tontine->withdraw($withdrawal->getAmount());

            $em->flush();

            // Enregistrer l'activité
            $this->activityLogger->log(
                $this->getUser(),
                'WITHDRAWAL_FEE_PAID',
                'Withdrawal',
                $withdrawal->getId(),
                'Frais de retrait payés avec succès - Montant: ' . number_format($fees, 0, ',', ' ') .
                    ' FCFA - Transaction: ' . $transactionId
            );

            return $this->json([
                'success' => true,
                'withdrawal_id' => $withdrawal->getId()
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la confirmation: ' . $e->getMessage()
            ], 500);
        }
    }
}
