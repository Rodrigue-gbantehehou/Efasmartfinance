<?php

namespace App\Controller;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\ErrorCorrectionLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class QrCodeController extends AbstractController
{
    #[Route('/tontine/{id}/qrcode', name: 'qr_code')]
    public function tontineQrCode(int $id, UrlGeneratorInterface $urlGenerator): Response
    {
        $qrData = json_encode([
            'tontineId' => $id,
            'timestamp' => time()
        ]);

        // Génère l’URL ABSOLUE à ouvrir après scan
        $url = $urlGenerator->generate(
            'app_public_tontine_card',
            ['code' => $id],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $qrCode = new QrCode(
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Low,
            size: 80,
            margin: 0,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(0, 128, 0),
            backgroundColor: new Color(255, 255, 255)
        );

        $writer = new PngWriter();
        $result = $writer->write($qrCode);

        return new Response(
            $result->getString(),
            Response::HTTP_OK,
            [
                'Content-Type' => $result->getMimeType(),
                'Cache-Control' => 'no-store, no-cache, must-revalidate'
            ]
        );
    }
}
