<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

use Twig\Environment;

class EmailService
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

    /**
     * Envoie une notification de verrouillage du compte suite à trop d'échecs PIN
     */
    public function sendPinLockoutNotification(\App\Entity\User $user, string $unlockTime): void
    {
        try {
            $htmlContent = $this->twig->render('emails/pin_lockout.html.twig', [
                'user' => $user,
                'unlockTime' => $unlockTime
            ]);

            $this->send(
                $user->getEmail(),
                'Alerte de sécurité : Votre compte est verrouillé',
                $htmlContent
            );
        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de l'envoi de l'email de verrouillage : " . $e->getMessage());
            
            // Fallback text email if twig fails
            try {
                $textEmail = (new Email())
                    ->from('EFA Smart Finance <contact@binajia.org>')
                    ->to($user->getEmail())
                    ->subject('Alerte de sécurité : Votre compte est verrouillé')
                    ->text("Bonjour {$user->getFirstname()}, votre compte est verrouillé jusqu'à {$unlockTime} suite à 5 tentatives PIN erronées.");
                
                $this->mailer->send($textEmail);
            } catch (\Exception $e2) {
                $this->logger->critical("Échec total de l'envoi d'email de verrouillage : " . $e2->getMessage());
            }
        }
    }
}
