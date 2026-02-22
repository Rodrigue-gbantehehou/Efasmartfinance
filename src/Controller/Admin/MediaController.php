<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[Route('/admin/media')]
#[IsGranted('ROLE_ADMIN')]
class MediaController extends AbstractController
{
    private $params;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
    }

    #[Route('/{type}/{filename}', name: 'admin_media_serve')]
    public function serveFile(string $type, string $filename): Response
    {
        // Types autorisés : documents, selfies
        if (!in_array($type, ['documents', 'selfies'])) {
            throw $this->createNotFoundException('Type de média non autorisé.');
        }

        $uploadDir = $this->params->get('uploads_directory');
        $filePath = $uploadDir . '/' . $type . '/' . $filename;

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Le fichier n\'existe pas.');
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $filename);

        return $response;
    }
}
