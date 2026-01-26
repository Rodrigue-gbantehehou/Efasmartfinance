<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment as TwigEnvironment;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

class PdfService
{
    private TwigEnvironment $twig;
    private string $projectDir;
    private Filesystem $filesystem;
    private string $pdfDirectory;
    private Dompdf $dompdf;

    public function __construct(
        TwigEnvironment $twig,
        KernelInterface $kernel
    ) {
        $this->twig = $twig;
        $this->projectDir = $kernel->getProjectDir();
        $this->filesystem = new Filesystem();
        $this->pdfDirectory = $this->projectDir . '/var/factures/';
        
        // Configurer DomPDF
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');
        
        $this->dompdf = new Dompdf($options);
        
        // Créer le répertoire s'il n'existe pas
        if (!$this->filesystem->exists($this->pdfDirectory)) {
            $this->filesystem->mkdir($this->pdfDirectory, 0777);
        }
    }

    /**
     * Génère une facture PDF et la sauvegarde dans le dossier var/factures
     * 
     * @param array $data Données pour le template de la facture
     * @param string $template Chemin vers le template Twig
     * @param string $filename Nom du fichier de sortie (sans l'extension .pdf)
     * @return string Chemin vers le fichier PDF généré
     */
    public function generateInvoice(array $data, string $template, string $filename): string
    {
        // Rendre le template Twig en HTML
        $html = $this->twig->render($template, $data);
        
        // Charger le HTML dans Dompdf
        $this->dompdf->loadHtml($html);
        
        // Définir la taille et l'orientation du papier
        $this->dompdf->setPaper('A4', 'portrait');
        
        // Rendre le PDF
        $this->dompdf->render();
        
        // Créer le chemin complet du fichier de sortie
        $outputFile = $this->pdfDirectory . $filename . '.pdf';
        
        // Sauvegarder le fichier PDF
        file_put_contents($outputFile, $this->dompdf->output());
        
        return $outputFile;
    }

    /**
     * Récupère le contenu du PDF sous forme de chaîne binaire
     */
    public function getPdfContent(string $filepath): string
    {
        if (!$this->filesystem->exists($filepath)) {
            throw new \RuntimeException("Le fichier PDF n'existe pas: $filepath");
        }
        
        return file_get_contents($filepath);
    }
}
