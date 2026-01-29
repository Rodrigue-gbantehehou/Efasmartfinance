<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Scheb\TwoFactorBundle\Mailer\AuthCodeMailerInterface;
use Scheb\TwoFactorBundle\Model\Email\TwoFactorInterface;
use Twig\Environment;

class EmailService implements AuthCodeMailerInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private \Psr\Log\LoggerInterface $logger,
        private Environment $twig
    ) {}

    public function send(
        string $to,
        string $subject,
        string $htmlContent
    ): void {
        try {
            $this->logger->info("Préparation de l'email à {$to} avec le sujet : {$subject}");
            
            $email = (new Email())
                ->from('EFA Smart Finance <contact@binajia.org>')
                ->to($to)
                ->subject($subject)
                ->html($htmlContent);

            $this->mailer->send($email);
            $this->logger->info("Email envoyé avec succès à {$to}");
        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de l'envoi de l'email à {$to}: " . $e->getMessage(), [
                'exception' => $e,
                'to' => $to,
                'subject' => $subject
            ]);
            throw $e; // On relance l'exception pour qu'elle soit gérée par l'appelant
        }
    }

    /**
     * Envoie un email avec une pièce jointe
     *
     * @param string $to Adresse email du destinataire
     * @param string $subject Sujet de l'email
     * @param string $htmlContent Contenu HTML de l'email
     * @param string $attachmentPath Chemin vers le fichier à joindre
     * @param string $filename Nom du fichier pour la pièce jointe
     * @return void
     */
    public function sendWithAttachment(
        string $to,
        string $subject,
        string $htmlContent,
        string $attachmentPath,
        string $filename
    ): void {
        try {
            $this->logger->info("Préparation de l'email avec pièce jointe à {$to} avec le sujet : {$subject}");
            
            $email = (new Email())
                ->from('EFA Smart Finance <contact@binajia.org>')
                ->to($to)
                ->subject($subject)
                ->html($htmlContent);

            // Ajout de la pièce jointe
            $fileContent = file_get_contents($attachmentPath);
            $email->attach($fileContent, $filename, 'application/pdf');

            $this->mailer->send($email);
            $this->logger->info("Email avec pièce jointe envoyé avec succès à {$to}");
        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de l'envoi de l'email avec pièce jointe à {$to}: " . $e->getMessage(), [
                'exception' => $e,
                'to' => $to,
                'subject' => $subject,
                'attachment' => $attachmentPath
            ]);
            throw $e;
        }
    }

    public function sendAuthCode(TwoFactorInterface $user): void
    {
        $authCode = $user->getEmailAuthCode();
        
        try {
            $htmlContent = $this->twig->render('bundles/SchebTwoFactorBundle/Authentication/email.html.twig', [
                'authCode' => $authCode,
                'user' => $user
            ]);

            $this->send(
                $user->getEmailAuthRecipient(),
                'Votre code de sécurité - EFA Smart Finance',
                $htmlContent
            );
        } catch (\Exception $e) {
            $this->logger->error("Erreur lors du rendu du template d'email 2FA: " . $e->getMessage());
            // Fallback sur un contenu texte simple au cas où Twig échoue
            $this->send(
                $user->getEmailAuthRecipient(),
                'Votre code de sécurité - EFA Smart Finance',
                "Votre code de sécurité est : " . $authCode
            );
        }
    }
}
