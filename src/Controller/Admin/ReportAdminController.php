<?php

namespace App\Controller\Admin;

use App\Repository\UserRepository;
use App\Repository\TontineRepository;
use App\Repository\TransactionRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reports')]
#[IsGranted('ROLE_SUPPORT')]
class ReportAdminController extends AbstractController
{
    #[Route('', name: 'admin_reports', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        TransactionRepository $transactionRepository,
        UserRepository $userRepository,
        TontineRepository $tontineRepository
    ): Response {
        $this->denyAccessUnlessGranted('VIEW_MODULE', 'reports');
        // Gérer la soumission du formulaire d'export
        if ($request->isMethod('POST')) {
            $reportType = $request->request->get('report-type');
            $dateRange = $request->request->get('date-range');
            $format = $request->request->get('format');
            
            // Définir la période en fonction de la sélection
            $endDate = new \DateTime();
            $startDate = clone $endDate;
            
            switch ($dateRange) {
                case '7 derniers jours':
                    $startDate->modify('-7 days');
                    break;
                case '30 derniers jours':
                    $startDate->modify('-30 days');
                    break;
                case 'Mois en cours':
                    $startDate = new \DateTime('first day of this month');
                    break;
                case 'Mois précédent':
                    $startDate = new \DateTime('first day of last month');
                    $endDate = new \DateTime('last day of last month');
                    break;
                default:
                    $startDate->modify('-30 days');
            }
            
            // Générer le rapport en fonction du type
            switch ($reportType) {
                case 'Transactions':
                    return $this->exportTransactions($transactionRepository, $startDate, $endDate, $format);
                case 'Utilisateurs':
                    return $this->exportUsers($userRepository, $startDate, $endDate, $format);
                case 'Tontines':
                    return $this->exportTontines($tontineRepository, $startDate, $endDate, $format);
                case 'Retraits':
                    return $this->exportWithdrawals($transactionRepository, $startDate, $endDate, $format);
                default:
                    $this->addFlash('error', 'Type de rapport non valide.');
            }
        }

        // Récupérer les statistiques pour le tableau de bord
        $stats = [
            'total_users' => $userRepository->count([]),
            'active_tontines' => $tontineRepository->count(['statut' => 'active']),
            'completed_tontines' => $tontineRepository->count(['statut' => 'completed']),
            'total_transactions' => $transactionRepository->count([]),
            'total_volume' => $transactionRepository->getTotalVolume(),
        ];

        // Données pour le graphique d'activité récente (30 derniers jours)
        $endDate = new \DateTime();
        $startDate = (clone $endDate)->modify('-30 days');
        $activityData = $transactionRepository->getDailyVolume($startDate, $endDate);

        // Données pour la répartition des tontines
        $tontineStats = [
            'labels' => ['Actives', 'Terminées', 'En attente'],
            'data' => [
                $tontineRepository->count(['statut' => 'active']),
                $tontineRepository->count(['statut' => 'completed']),
                $tontineRepository->count(['statut' => 'pending']),
            ],
            'colors' => ['#008040', '#3B82F6', '#F59E0B']
        ];

        return $this->render('admin/reports/index.html.twig', [
            'stats' => $stats,
            'activity_data' => json_encode($activityData),
            'tontine_stats' => json_encode($tontineStats),
        ]);
    }

    #[Route('/transactions', name: 'admin_reports_transactions')]
    public function transactions(TransactionRepository $transactionRepository): Response
    {
        $this->denyAccessUnlessGranted('VIEW_MODULE', 'reports');
        $transactions = $transactionRepository->findBy([], ['createdAt' => 'DESC']);
        
        return $this->render('admin/reports/transactions.html.twig', [
            'transactions' => $transactions,
        ]);
    }

    #[Route('/users', name: 'admin_reports_users')]
    public function users(UserRepository $userRepository): Response
    {
        $this->denyAccessUnlessGranted('VIEW_MODULE', 'reports');
        $users = $userRepository->findBy([], ['createdAt' => 'DESC']);
        
        return $this->render('admin/reports/users.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/create', name: 'admin_reports_create')]
    /**
     * Exporte les transactions au format Excel
     */
    private function exportTransactions(TransactionRepository $repository, \DateTime $startDate, \DateTime $endDate, string $format)
    {
        $transactions = $repository->createQueryBuilder('t')
            ->where('t.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $startDate->format('Y-m-d 00:00:00'))
            ->setParameter('end', $endDate->format('Y-m-d 23:59:59'))
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // En-têtes
        $sheet->setCellValue('A1', 'ID')
              ->setCellValue('B1', 'Date')
              ->setCellValue('C1', 'Méthode de paiement')
              ->setCellValue('D1', 'Montant')
              ->setCellValue('E1', 'Statut')
              ->setCellValue('F1', 'Utilisateur')
              ->setCellValue('G1', 'Tontine');

        // Données
        $row = 2;
        foreach ($transactions as $transaction) {
            $user = $transaction->getUtilisateur();
            $tontine = $transaction->getTontine();
            
            $sheet->setCellValue('A' . $row, $transaction->getId())
                  ->setCellValue('B' . $row, $transaction->getCreatedAt()->format('d/m/Y H:i'))
                  ->setCellValue('C' . $row, $transaction->getPaymentMethod() ?? 'Non spécifié')
                  ->setCellValue('D' . $row, $transaction->getAmount())
                  ->setCellValue('E' . $row, $transaction->getStatut() ?? 'Inconnu')
                  ->setCellValue('F' . $row, $user ? $user->getEmail() : 'Système')
                  ->setCellValue('G' . $row, $tontine ? $tontine->getName() : 'N/A');
            $row++;
        }

        // Ajuster la largeur des colonnes
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->exportToFormat($spreadsheet, 'transactions', $format);
    }

    /**
     * Exporte les utilisateurs au format Excel
     */
    private function exportUsers(UserRepository $repository, \DateTime $startDate, \DateTime $endDate, string $format)
    {
        $users = $repository->createQueryBuilder('u')
            ->where('u.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $startDate->format('Y-m-d 00:00:00'))
            ->setParameter('end', $endDate->format('Y-m-d 23:59:59'))
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // En-têtes
        $sheet->setCellValue('A1', 'ID')
              ->setCellValue('B1', 'Email')
              ->setCellValue('C1', 'Nom')
              ->setCellValue('D1', 'Téléphone')
              ->setCellValue('E1', 'Date d\'inscription')
              ->setCellValue('F1', 'Statut');

        // Données
        $row = 2;
        foreach ($users as $user) {
            $sheet->setCellValue('A' . $row, $user->getId())
                  ->setCellValue('B' . $row, $user->getEmail())
                  ->setCellValue('C' . $row, $user->getFullName())
                  ->setCellValue('D' . $row, $user->getPhoneNumber())
                  ->setCellValue('E' . $row, $user->getCreatedAt()->format('d/m/Y H:i'))
                  ->setCellValue('F' . $row, $user->isVerified() ? 'Vérifié' : 'Non vérifié');
            $row++;
        }

        // Ajuster la largeur des colonnes
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->exportToFormat($spreadsheet, 'utilisateurs', $format);
    }

    /**
     * Exporte les tontines au format Excel
     */
    private function exportTontines(TontineRepository $repository, \DateTime $startDate, \DateTime $endDate, string $format)
    {
        $tontines = $repository->createQueryBuilder('t')
            ->where('t.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $startDate->format('Y-m-d 00:00:00'))
            ->setParameter('end', $endDate->format('Y-m-d 23:59:59'))
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // En-têtes
        $sheet->setCellValue('A1', 'ID')
              ->setCellValue('B1', 'Nom')
              ->setCellValue('C1', 'Montant')
              ->setCellValue('D1', 'Période')
              ->setCellValue('E1', 'Statut')
              ->setCellValue('F1', 'Créée le')
              ->setCellValue('G1', 'Créée par');

        // Données
        $row = 2;
        foreach ($tontines as $tontine) {
            $sheet->setCellValue('A' . $row, $tontine->getId())
                  ->setCellValue('B' . $row, $tontine->getName())
                  ->setCellValue('C' . $row, $tontine->getAmountPerPoint()*$tontine->getTotalPoints())
                  ->setCellValue('D' . $row, $tontine->getPeriode())
                  ->setCellValue('E' . $row, ucfirst($tontine->getStatut()))
                  ->setCellValue('F' . $row, $tontine->getCreatedAt()->format('d/m/Y H:i'))
                  ->setCellValue('G' . $row, $tontine->getUtilisateur() ? $tontine->getUtilisateur()->getEmail() : 'Système');
            $row++;
        }

        // Ajuster la largeur des colonnes
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->exportToFormat($spreadsheet, 'tontines', $format);
    }

    /**
     * Exporte les retraits au format Excel
     */
    private function exportWithdrawals(TransactionRepository $repository, \DateTime $startDate, \DateTime $endDate, string $format)
    {
        $withdrawals = $repository->createQueryBuilder('t')
            ->where('t.type = :type')
            ->andWhere('t.createdAt BETWEEN :start AND :end')
            ->setParameter('type', 'retrait')
            ->setParameter('start', $startDate->format('Y-m-d 00:00:00'))
            ->setParameter('end', $endDate->format('Y-m-d 23:59:59'))
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // En-têtes
        $sheet->setCellValue('A1', 'ID')
              ->setCellValue('B1', 'Date')
              ->setCellValue('C1', 'Montant')
              ->setCellValue('D1', 'Statut')
              ->setCellValue('E1', 'Bénéficiaire')
              ->setCellValue('F1', 'Méthode de paiement');

        // Données
        $row = 2;
        foreach ($withdrawals as $withdrawal) {
            $sheet->setCellValue('A' . $row, $withdrawal->getId())
                  ->setCellValue('B' . $row, $withdrawal->getCreatedAt()->format('d/m/Y H:i'))
                  ->setCellValue('C' . $row, $withdrawal->getAmount())
                  ->setCellValue('D' . $row, $withdrawal->getStatut())
                  ->setCellValue('E' . $row, $withdrawal->getUtilisateur() ? $withdrawal->getUtilisateur()->getEmail() : 'Inconnu')
                  ->setCellValue('F' . $row, $withdrawal->getPaymentMethod() ?? 'Non spécifiée');
            $row++;
        }

        // Ajuster la largeur des colonnes
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->exportToFormat($spreadsheet, 'retraits', $format);
    }

    /**
     * Exporte le document dans le format demandé
     */
    private function exportToFormat(Spreadsheet $spreadsheet, string $filename, string $format)
    {
        $filename = sprintf('export_%s_%s', $filename, date('Y-m-d_His'));
        
        if ($format === 'PDF (.pdf)') {
            // Pour le PDF, on utilise mPDF via PhpSpreadsheet
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf($spreadsheet);
            $filename .= '.pdf';
            $contentType = 'application/pdf';
        } else if ($format === 'CSV (.csv)') {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet);
            $writer->setDelimiter(';');
            $writer->setEnclosure('"');
            $writer->setLineEnding("\r\n");
            $filename .= '.csv';
            $contentType = 'text/csv';
        } else {
            // Par défaut, on exporte en Excel
            $writer = new Xlsx($spreadsheet);
            $filename .= '.xlsx';
            $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        }

        // Créer un fichier temporaire
        $tempFile = tempnam(sys_get_temp_dir(), 'export_');
        $writer->save($tempFile);

        // Envoyer le fichier
        $response = new \Symfony\Component\HttpFoundation\Response(file_get_contents($tempFile));
        
        // Supprimer le fichier temporaire
        unlink($tempFile);
        
        // Définir les en-têtes de la réponse
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename
        );
        
        $response->headers->set('Content-Type', $contentType);
        $response->headers->set('Content-Disposition', $disposition);
        
        return $response;
    }
}
