<?php

namespace App\Controller\Admin;

use App\Entity\Facture;
use App\Repository\FactureRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use ZipArchive;

use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/invoices')]
#[IsGranted('ROLE_SUPPORT')]
class InvoiceController extends AbstractController
{
    public function __construct(
        private FactureRepository $factureRepo,
        private EntityManagerInterface $em,
        private EmailService $emailService
    ) {}

    #[Route('', name: 'admin_invoices')]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted('VIEW_MODULE', 'invoices');
        $qb = $this->factureRepo->createQueryBuilder('f')
            ->leftJoin('f.client', 'c')
            ->addSelect('c')
            ->orderBy('f.dateEmission', 'DESC');

        // Filtres
        $statut = $request->query->get('statut');
        if ($statut && in_array($statut, ['payee', 'impayee'])) {
            $qb->andWhere('f.statut = :statut')
               ->setParameter('statut', $statut);
        }

        $dateDebut = $request->query->get('date_debut');
        if ($dateDebut) {
            $qb->andWhere('f.dateEmission >= :dateDebut')
               ->setParameter('dateDebut', new \DateTime($dateDebut));
        }

        $dateFin = $request->query->get('date_fin');
        if ($dateFin) {
            $qb->andWhere('f.dateEmission <= :dateFin')
               ->setParameter('dateFin', new \DateTime($dateFin . ' 23:59:59'));
        }

        $search = $request->query->get('search');
        if ($search) {
            $qb->andWhere('f.numero LIKE :search OR c.email LIKE :search OR c.firstname LIKE :search OR c.lastname LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        $factures = $qb->getQuery()->getResult();

        // Statistiques
        $totalFactures = count($factures);
        $totalPayees = count(array_filter($factures, fn($f) => $f->getStatut() === 'payee'));
        $totalImpayees = $totalFactures - $totalPayees;
        $montantTotal = array_reduce($factures, fn($carry, $f) => $carry + (float)$f->getMontantTTC(), 0);

        return $this->render('admin/pages/invoices/index.html.twig', [
            'factures' => $factures,
            'totalFactures' => $totalFactures,
            'totalPayees' => $totalPayees,
            'totalImpayees' => $totalImpayees,
            'montantTotal' => $montantTotal,
            'filters' => [
                'statut' => $statut,
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
                'search' => $search,
            ],
        ]);
    }

    #[Route('/{id}', name: 'admin_invoice_show', methods: ['GET'])]
    public function show(Facture $facture): Response
    {
        $this->denyAccessUnlessGranted('VIEW_MODULE', 'invoices');
        return $this->render('admin/pages/invoices/show.html.twig', [
            'facture' => $facture,
        ]);
    }

    #[Route('/{id}/resend', name: 'admin_invoice_resend', methods: ['POST'])]
    public function resend(Facture $facture, Request $request): Response
    {
        $this->denyAccessUnlessGranted('EDIT_MODULE', 'invoices');
        if (!$this->isCsrfTokenValid('resend' . $facture->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_invoices');
        }

        if (!$facture->getClient()) {
            $this->addFlash('error', 'Impossible de renvoyer la facture : client introuvable.');
            return $this->redirectToRoute('admin_invoices');
        }

        try {
            $pdfPath = $this->getParameter('kernel.project_dir') . '/var/' . $facture->getFichier();
            
            if (!file_exists($pdfPath)) {
                $this->addFlash('error', 'Le fichier PDF de la facture est introuvable.');
                return $this->redirectToRoute('admin_invoices');
            }

            $emailContent = $this->renderView('emails/facture_resend.html.twig', [
                'facture' => $facture,
                'client' => $facture->getClient(),
            ]);

            $this->emailService->sendWithAttachment(
                $facture->getClient()->getEmail(),
                'Facture ' . $facture->getNumero() . ' - Efa Smart Finance',
                $emailContent,
                $pdfPath,
                'facture-' . $facture->getNumero() . '.pdf'
            );

            $this->addFlash('success', 'La facture a été renvoyée avec succès à ' . $facture->getClient()->getEmail());
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'envoi : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_invoices');
    }

    #[Route('/bulk/download', name: 'admin_invoices_bulk_download', methods: ['POST'])]
    public function bulkDownload(Request $request): Response
    {
        $this->denyAccessUnlessGranted('VIEW_MODULE', 'invoices');
        $ids = $request->request->all('invoice_ids');
        
        if (empty($ids)) {
            $this->addFlash('error', 'Aucune facture sélectionnée.');
            return $this->redirectToRoute('admin_invoices');
        }

        $factures = $this->factureRepo->findBy(['id' => $ids]);
        
        if (count($factures) === 1) {
            // Téléchargement direct pour une seule facture
            $facture = $factures[0];
            $pdfPath = $this->getParameter('kernel.project_dir') . '/var/' . $facture->getFichier();
            
            if (!file_exists($pdfPath)) {
                $this->addFlash('error', 'Fichier PDF introuvable.');
                return $this->redirectToRoute('admin_invoices');
            }

            return $this->file($pdfPath, 'facture-' . $facture->getNumero() . '.pdf');
        }

        // Création d'un ZIP pour plusieurs factures
        $zipPath = sys_get_temp_dir() . '/factures_' . time() . '.zip';
        $zip = new ZipArchive();
        
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            $this->addFlash('error', 'Impossible de créer l\'archive ZIP.');
            return $this->redirectToRoute('admin_invoices');
        }

        foreach ($factures as $facture) {
            $pdfPath = $this->getParameter('kernel.project_dir') . '/var/' . $facture->getFichier();
            if (file_exists($pdfPath)) {
                $zip->addFile($pdfPath, 'facture-' . $facture->getNumero() . '.pdf');
            }
        }

        $zip->close();

        $response = new BinaryFileResponse($zipPath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'factures_' . date('Y-m-d') . '.zip'
        );
        $response->deleteFileAfterSend(true);

        return $response;
    }

    #[Route('/{id}/download', name: 'admin_invoice_download', methods: ['GET'])]
    public function download(Facture $facture): Response
    {
        $this->denyAccessUnlessGranted('VIEW_MODULE', 'invoices');
        $pdfPath = $this->getParameter('kernel.project_dir') . '/var/' . $facture->getFichier();
        
        if (!file_exists($pdfPath)) {
            $this->addFlash('error', 'Fichier PDF introuvable.');
            return $this->redirectToRoute('admin_invoices');
        }

        return $this->file($pdfPath, 'facture-' . $facture->getNumero() . '.pdf');
    }
}
