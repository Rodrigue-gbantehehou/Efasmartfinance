<?php

namespace App\Controller\Dashboard;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/files')]
#[IsGranted('ROLE_USER')]
class FileController extends AbstractController
{
    /**
     * Affiche un fichier téléchargé
     * 
     * @Route("/view/{path}", name="app_dashboard_file_view", requirements={"path"=".+"})
     */
    public function view(string $path): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $filePath = $this->getParameter('kernel.project_dir') . '/public' . $path;
        
        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Le fichier demandé n\'existe pas.');
        }
        
        $file = new File($filePath);
        
        return $this->file($file, null, ResponseHeaderBag::DISPOSITION_INLINE);
    }
    
    /**
     * Télécharge un fichier
     * 
     * @Route("/download/{path}", name="app_dashboard_file_download", requirements={"path"=".+"})
     */
    public function download(string $path): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $filePath = $this->getParameter('kernel.project_dir') . '/public' . $path;
        
        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Le fichier demandé n\'existe pas.');
        }
        
        $file = new File($filePath);
        
        return $this->file($file);
    }
}
