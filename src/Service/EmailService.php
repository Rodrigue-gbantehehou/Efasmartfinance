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
        private Environment $twig,
        private string $contactEmail,
        private string $noreplyEmail,
        private string $projectDir
    ) {}

    public function send(
        string $to,
        string $subject,
        string $htmlContent,
        ?string $from = null
    ): void {
        try {
            $sender = $from ?? $this->noreplyEmail;
            
            // Extract a text version from HTML
            $textContent = strip_tags(str_replace(['<br>', '</div>', '</p>'], ["\n", "\n", "\n\n"], $htmlContent));
            
            $this->logger->info("Préparation de l'email à {$to} depuis {$sender} avec le sujet : {$subject}");
            
            $email = (new Email())
                ->from(new \Symfony\Component\Mime\Address($sender, 'Efa Smart Finance'))
                ->to($to)
                ->subject($subject)
                ->html($htmlContent)
                ->text($textContent);

            $this->embedLogo($email);

            $this->mailer->send($email);
            $this->logger->info("Email envoyé avec succès à {$to}");
        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de l'envoi de l'email à {$to}: " . $e->getMessage(), [
                'exception' => $e,
                'to' => $to,
                'subject' => $subject
            ]);
            throw $e;
        }
    }

    /**
     * Envoie un email avec une pièce jointe
     */
    public function sendWithAttachment(
        string $to,
        string $subject,
        string $htmlContent,
        string $attachmentPath,
        string $filename,
        ?string $from = null
    ): void {
        try {
            $sender = $from ?? $this->noreplyEmail;
            $this->logger->info("Préparation de l'email avec pièce jointe à {$to} depuis {$sender} avec le sujet : {$subject}");
            
            $textContent = strip_tags(str_replace(['<br>', '</div>', '</p>'], ["\n", "\n", "\n\n"], $htmlContent));

            $email = (new Email())
                ->from(new \Symfony\Component\Mime\Address($sender, 'Efa Smart Finance'))
                ->to($to)
                ->subject($subject)
                ->html($htmlContent)
                ->text($textContent);

            $this->embedLogo($email);

            // Ajout de la pièce jointe
            if (file_exists($attachmentPath)) {
                $email->attachFromPath($attachmentPath, $filename, 'application/pdf');
            }

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

    public function embedLogo(Email $email): void
    {
        $logoPath = $this->projectDir . '/public/images/logovert.png';
        if (file_exists($logoPath)) {
            // Using a safe internal CID and explicit filename/mimetype
            $email->addPart((new DataPart(fopen($logoPath, 'r'), 'logo-efa.png', 'image/png'))->asInline());
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
                $htmlContent,
                $this->noreplyEmail
            );
        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de l'envoi de l'email de verrouillage : " . $e->getMessage());
            
            // Fallback text email if twig fails
            try {
                $textEmail = (new Email())
                    ->from(new \Symfony\Component\Mime\Address($this->noreplyEmail, 'Efa Smart Finance'))
                    ->to($user->getEmail())
                    ->subject('Alerte de sécurité : Votre compte est verrouillé')
                    ->text("Bonjour {$user->getFirstname()}, votre compte est verrouillé jusqu'à {$unlockTime} suite à 5 tentatives PIN erronées.");
                
                $this->mailer->send($textEmail);
            } catch (\Exception $e2) {
                $this->logger->critical("Échec total de l'envoi d'email de verrouillage : " . $e2->getMessage());
            }
        }
    }
    /**
     * Envoie un rappel de paiement pour une tontine
     */
    public function sendTontineReminder(\App\Entity\User $user, \App\Entity\Tontine $tontine): void
    {
        try {
            $htmlContent = $this->twig->render('emails/tontine_reminder.html.twig', [
                'user' => $user,
                'tontine' => $tontine
            ]);

            $this->send(
                $user->getEmail(),
                'Rappel de paiement : Votre tontine ' . $tontine->getName(),
                $htmlContent
            );
        } catch (\Exception $e) {
            $this->logger->error("Erreur l'envoi du rappel tontine : " . $e->getMessage());
        }
    }

    /**
     * Envoie une notification de fin de tontine
     */
    public function sendTontineCompletion(\App\Entity\User $user, \App\Entity\Tontine $tontine): void
    {
        try {
            $htmlContent = $this->twig->render('emails/tontine_completion.html.twig', [
                'user' => $user,
                'tontine' => $tontine
            ]);

            $this->send(
                $user->getEmail(),
                'Félicitations ! Votre tontine ' . $tontine->getName() . ' est terminée',
                $htmlContent
            );
        } catch (\Exception $e) {
            $this->logger->error("Erreur l'envoi de la notification de fin de tontine : " . $e->getMessage());
        }
    }

    /**
     * Envoie un avertissement 2 jours avant la suppression définitive
     */
    public function sendDeletionWarningEmail(\App\Entity\User $user): void
    {
        try {
            $htmlContent = $this->twig->render('emails/account_deletion_warning.html.twig', [
                'user' => $user,
                'days_remaining' => $user->getDaysUntilDeletion()
            ]);

            $this->send(
                $user->getEmail(),
                'Avertissement : Suppression définitive de votre compte dans 48h',
                $htmlContent
            );
        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de l'envoi de l'avertissement de suppression : " . $e->getMessage());
        }
    }

    /**
     * Envoie une confirmation d'anonymisation du compte
     */
    public function sendAccountAnonymizedEmail(string $userEmail, string $firstname): void
    {
        try {
            $htmlContent = $this->twig->render('emails/account_anonymized.html.twig', [
                'firstname' => $firstname
            ]);

            $this->send(
                $userEmail,
                'Confirmation : Votre compte a été définitivement supprimé',
                $htmlContent
            );
        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de l'envoi de la confirmation d'anonymisation : " . $e->getMessage());
        }
    }

    /**
     * Email de confirmation d'approbation de compte
     */
    public function sendAccountApprovedEmail(\App\Entity\User $user): void
    {
        try {
            $htmlContent = $this->twig->render('emails/account_approved.html.twig', [
                'user' => $user
            ]);

            $this->send(
                $user->getEmail(),
                'Félicitations ! Votre compte Efa Smart Finance est activé',
                $htmlContent
            );
        } catch (\Exception $e) {
            $this->logger->error("Erreur l'envoi de l'email d'approbation : " . $e->getMessage());
        }
    }

    /**
     * Email de rejet de vérification de compte
     */
    public function sendAccountRejectedEmail(\App\Entity\User $user, string $reason): void
    {
        try {
            $htmlContent = $this->twig->render('emails/account_rejected.html.twig', [
                'user' => $user,
                'reason' => $reason
            ]);

            $this->send(
                $user->getEmail(),
                'Important : Votre demande de vérification a été rejetée',
                $htmlContent
            );
        } catch (\Exception $e) {
            $this->logger->error("Erreur l'envoi de l'email de rejet : " . $e->getMessage());
        }
    }

    /**
     * Email de suspension de compte
     */
    public function sendAccountSuspendedEmail(\App\Entity\User $user): void
    {
        try {
            $htmlContent = $this->twig->render('emails/account_suspended.html.twig', [
                'user' => $user
            ]);

            $this->send(
                $user->getEmail(),
                'Alerte : Votre compte Efa Smart Finance a été suspendu',
                $htmlContent
            );
        } catch (\Exception $e) {
            $this->logger->error("Erreur l'envoi de l'email de suspension : " . $e->getMessage());
        }
    }

    /**
     * Email de demande de retrait
     */
    public function sendWithdrawalRequestEmail(\App\Entity\User $user, float $amount): void
    {
        try {
            $htmlContent = $this->twig->render('emails/withdrawal_request.html.twig', [
                'user' => $user,
                'amount' => $amount
            ]);

            $this->send(
                $user->getEmail(),
                'Demande de retrait reçue - Efa Smart Finance',
                $htmlContent
            );
        } catch (\Exception $e) {
            $this->logger->error("Erreur l'envoi de l'email de demande de retrait : " . $e->getMessage());
        }
    }

    /**
     * Email d'approbation de retrait
     */
    public function sendWithdrawalApprovedEmail(\App\Entity\User $user, float $amount): void
    {
        try {
            $htmlContent = $this->twig->render('emails/withdrawal_approved.html.twig', [
                'user' => $user,
                'amount' => $amount
            ]);

            $this->send(
                $user->getEmail(),
                'Votre retrait a été approuvé ! - Efa Smart Finance',
                $htmlContent
            );
        } catch (\Exception $e) {
            $this->logger->error("Erreur l'envoi de l'email d'approbation de retrait : " . $e->getMessage());
        }
    }

    /**
     * Email de rejet de retrait
     */
    public function sendWithdrawalRejectedEmail(\App\Entity\User $user, float $amount, string $reason): void
    {
        try {
            $htmlContent = $this->twig->render('emails/withdrawal_rejected.html.twig', [
                'user' => $user,
                'amount' => $amount,
                'reason' => $reason
            ]);

            $this->send(
                $user->getEmail(),
                'Mise à jour concernant votre demande de retrait - Efa Smart Finance',
                $htmlContent
            );
        } catch (\Exception $e) {
            $this->logger->error("Erreur l'envoi de l'email de rejet de retrait : " . $e->getMessage());
        }
    }
}

