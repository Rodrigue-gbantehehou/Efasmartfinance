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
        $user = $this->getUser();
        $tontine = $em->getRepository(Tontine::class)->find($id);

        // Vérifier si la tontine existe et appartient à l'utilisateur
        if (!$tontine || $tontine->getUtilisateur() !== $user) {
            $this->addFlash('error', 'Tontine non trouvée ou accès refusé.');
            return $this->redirectToRoute('app_tontines_index');
        }

        // Vérifier si le compte est vérifié (vérification de l'email)
        if (!$user->getVerificationStatut() == 'verified') {
            $this->addFlash('error', 'Veuillez d\'abord vérifier votre compte avant de pouvoir effectuer un retrait.');
            return $this->redirectToRoute('app_tontines_show', ['id' => $tontine->getId()]);
        }

        /**Vérifier si la tontine est complétée
        if ($tontine->getStatut() !== 'completed') {
            $this->addFlash('error', 'La tontine doit être complétée avant de pouvoir effectuer un retrait.');
            return $this->redirectToRoute('app_tontines_show', ['id' => $tontine->getId()]);
        }**/ 

        // Vérifier si les frais de retrait ont été payés
        if (!$tontine->isFraisPreleves()) {
            $this->addFlash('error', 'Veuillez d\'abord payer les frais de service avant de pouvoir effectuer un retrait.');
            return $this->redirectToRoute('app_tontines_show', ['id' => $tontine->getId()]);
        }

        // Si on arrive ici, c'est que les frais sont payés, on peut continuer avec la demande de retrait
        $form = $this->createForm(WithdrawalRequestType::class, null, [
            'tontine' => $tontine,
            'action' => $this->generateUrl('app_withdrawal_request', ['id' => $tontine->getId()])
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            
            // Calculer le montant à retirer
            $amount = ($data['withdrawal_type'] === 'tontine')
                ? $tontine->getAvailableWithdrawalAmount()
                : $data['custom_amount'];

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
                'utilisateur' => $user
            ]);

            if ($demandeEnCours) {
                $this->addFlash('warning', 'Une demande de retrait est déjà en attente pour cette tontine.');
                return $this->redirectToRoute('app_tontines_show', ['id' => $tontine->getId()]);
            }

            try {
                // Créer la demande de retrait
                $withdrawal = new Withdrawals();
                $withdrawal->setUtilisateur($user);
                $withdrawal->setTontine($tontine);
                $withdrawal->setAmount($amount);
                $withdrawal->setTotalAmount($amount);
                $withdrawal->setMethod($data['payment_method'] ?? 'mobile_money');
                $withdrawal->setStatut('pending');
                $withdrawal->setRequestedAt(new \DateTimeImmutable());
                $withdrawal->setReason('Demande de retrait de ' . number_format($amount, 0, ',', ' ') . ' FCFA');

                // Effectuer le retrait du montant demandé
                $tontine->withdraw($amount);
                
                $em->persist($withdrawal);
                $em->flush();

                // Mettre à jour le statut de la tontine si nécessaire
                if ($tontine->isComplete()) {
                    $tontine->setStatut('completed');
                    $tontine->setEndedAt(new \DateTimeImmutable());
                    $em->persist($tontine);
                    $em->flush();
                    $message = 'Votre demande de retrait a été soumise avec succès et la tontine a été clôturée.';
                } else {
                    $message = 'Votre demande de retrait a été soumise avec succès.';
                }

                $this->addFlash('success', $message);

                // Log de l'activité
                $this->activityLogger->log(
                    $user,
                    'WITHDRAWAL_REQUEST',
                    'Withdrawal',
                    $withdrawal->getId(),
                    sprintf(
                        'Nouvelle demande de retrait - Montant: %s FCFA',
                        number_format($amount, 0, ',', ' ')
                    )
                );

                return $this->redirectToRoute('app_tontines_show', ['id' => $tontine->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la soumission de votre demande : ' . $e->getMessage());
                return $this->redirectToRoute('app_withdrawal_request', ['id' => $tontine->getId()]);
            }
        }

        return $this->render('dashboard/pages/withdrawals/request.html.twig', [
            'form' => $form->createView(),
            'tontine' => $tontine,
            'availableAmount' => $tontine->getAvailableWithdrawalAmount()
        ]);
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
}
