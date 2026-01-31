<?php

namespace App\Controller\Dashboard;

use App\Entity\Facture;
use App\Entity\Tontine;
use App\Entity\PlatformFee;
use App\Entity\Transaction;
use App\Service\EmailService;
use App\Service\ActivityLogger;
use App\Service\Payment\KkiaPayService;
use App\Service\PdfService;
use App\Service\NumerotationFactureService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class TontineFeesController extends AbstractController
{
    public function __construct(
        private PdfService $pdfService,
        private NumerotationFactureService $numerotationFactureService,
        private EmailService $emailService,
        private KkiaPayService $kkiaPayService,
        private ActivityLogger $activityLogger,
        private LoggerInterface $logger,
        #[Autowire('%env(KKIAPAY_PUBLIC_KEY)%')]
        private string $kkiapayPublicKey
    ) {}

    #[Route('/dashboard/tontines/fees/pay/{id}', name: 'app_tontine_pay_fees', methods: ['GET'])]
    public function pay(Tontine $tontine): Response
    {
        // Calcul des frais (exemple: 1000 FCFA par mois)
        $months = $tontine->getTotalPoints() ?? 1;
        $montantParPoint = $tontine->getAmountPerPoint(); 

    	if($tontine->getfrequency() == 'daily')
		{
			$feePerMonth = $montantParPoint * $months / 30;
            $months = $months / 30;
		}
		elseif($tontine->getfrequency() == 'weekly')
		{
			$feePerMonth = $montantParPoint * 4 / 30;
            $months = $months / 4;
		}
		elseif($tontine->getfrequency() == 'monthly')
		{
			$feePerMonth = $montantParPoint / 30;
		}
		
        $totalFee = $months * $feePerMonth;

        return $this->render('dashboard/pages/tontines/pay_fees.html.twig', [
            'tontine' => $tontine,
            'feePerMonth' => $feePerMonth,
            'months' => $months,
            'totalFee' => $totalFee,
            'kkiapay_public_key' => $this->kkiapayPublicKey
        ]);
    }

    #[Route('/dashboard/tontines/fees/verify/{id}', name: 'app_tontine_fees_verify', methods: ['POST'])]
    public function verify(
        Request $request, 
        Tontine $tontine, 
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $transactionId = $data['transactionId'] ?? null;

        if (!$transactionId) {
            return new JsonResponse(['success' => false, 'message' => 'ID de transaction manquant'], 400);
        }

        // Vérification réelle avec KkiaPayService
        $verifyResult = $this->kkiaPayService->verifyTransaction($transactionId);
        
        if (!$verifyResult || ($verifyResult['status'] ?? null) !== 'SUCCESS') {
            return new JsonResponse([
                'success' => false, 
                'message' => 'La vérification du paiement a échoué : ' . ($verifyResult['message'] ?? 'Paiement non validé par Kkiapay')
            ], 400);
        }

        $user = $this->getUser();
        
        // Récupération sécurisée du montant
        $amountPaid = 0;
        if (isset($verifyResult['data']['amount'])) {
            $amountPaid = (float) $verifyResult['data']['amount'];
        } elseif (isset($verifyResult['amount'])) {
            $amountPaid = (float) $verifyResult['amount'];
        }

        if ($amountPaid <= 0) {
            return new JsonResponse(['success' => false, 'message' => 'Montant payé invalide'], 400);
        }

        // Créer la transaction pour les frais
        $transaction = new Transaction();
        $transaction->setTontine($tontine);
        $transaction->setUtilisateur($user);
        $transaction->setAmount((string)$amountPaid);
        $transaction->setType('frais_service');
        $transaction->setPaymentMethod('online');
        $transaction->setProvider('kkiapay');
        $transaction->setStatut('completed');
        $transaction->setExternalReference($transactionId);
        $transaction->setCreatedAt(new \DateTimeImmutable());
        $em->persist($transaction);

        // Créer la facture
        $facture = new Facture();
        $facture->setNumero($this->numerotationFactureService->genererNumero());
        $facture->setDateEmission(new \DateTime());
        $facture->setMontantHT((string)$amountPaid);
        $facture->setTva('0.00');
        $facture->setMontantTTC((string)$amountPaid);
        $facture->setStatut('payee');
        $facture->setClient($user);
        $facture->setDescription('Paiement de frais de service pour la tontine : ' . $tontine->getName());
        $em->persist($facture);

        // Marquer la tontine comme ayant les frais payés
        $tontine->setFraisPreleves(true);

        // Créer l'enregistrement PlatformFee pour le dashboard admin
        $platformFee = new PlatformFee();
        $platformFee->setUser($user);
        $platformFee->setTontine($tontine);
        $platformFee->setAmount((int)$amountPaid);
        $platformFee->setType('service_fee');
        $platformFee->setStatus('collected');
        $platformFee->setTransactionId($transactionId);
        $platformFee->setCreatedAt(new \DateTimeImmutable());
        $em->persist($platformFee);

        $em->flush();

        // Journalisation de l'activité
        $this->activityLogger->log(
            $user,
            'FEES_PAID',
            'Tontine',
            $tontine->getId(),
            'Paiement des frais de service de ' . $amountPaid . ' FCFA pour la tontine ' . $tontine->getName()
        );

        // Générer le PDF
        $pdfData = [
            'payment' => $transaction,
            'user' => $user,
            'facture' => $facture,
            'hasPdf' => true
        ];

        try {
            $pdfPath = $this->pdfService->generateInvoice(
                $pdfData,
                'emails/facture_frais_pdf.html.twig',
                'facture-frais-' . $facture->getNumero()
            );

            // Mettre à jour la facture avec le fichier
            $facture->setFichier('factures/' . basename($pdfPath));
            
            // Mettre à jour la transaction avec le chemin de la facture
            $transaction->setInvoicePath('factures/' . basename($pdfPath));
            
            $em->flush();

            // Envoyer l'email
            $emailContent = $this->renderView('emails/facture_frais.html.twig', [
                'user' => $user,
                'payment' => $transaction,
                'facture' => $facture,
                'hasPdf' => true
            ]);

            $this->emailService->sendWithAttachment(
                $user->getEmail(),
                'Votre facture de frais de service Efasmartfinance ' . $facture->getNumero(),
                $emailContent,
                $pdfPath,
                'facture-frais-' . $facture->getNumero() . '.pdf'
            );
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la génération ou l\'envoi de la facture de frais', [
                'error' => $e->getMessage(),
                'tontine_id' => $tontine->getId()
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'redirect' => $this->generateUrl('app_tontines_show', ['id' => $tontine->getId()])
        ]);
    }
}
