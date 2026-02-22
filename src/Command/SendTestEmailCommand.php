<?php

namespace App\Command;

use App\Service\EmailService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-test-email',
    description: 'Envoie un email de test en utilisant EmailService',
)]
class SendTestEmailCommand extends Command
{
    public function __construct(
        private EmailService $emailService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('to', InputArgument::REQUIRED, 'Adresse email du destinataire')
            ->setHelp('Cette commande envoie un email de test en utilisant le service EmailService.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $to = $input->getArgument('to');

        $io->title('📧 Test d\'envoi d\'email via EmailService');
        $io->section('Configuration');
        
        // Afficher la configuration
        $io->table(
            ['Paramètre', 'Valeur'],
            [
                ['MAILER_DSN', $_ENV['MAILER_DSN'] ?? 'Non défini'],
                ['MAIL_HOST', $_ENV['MAIL_HOST'] ?? 'Non défini'],
                ['MAIL_PORT', $_ENV['MAIL_PORT'] ?? 'Non défini'],
                ['MAIL_FROM_ADDRESS', $_ENV['MAIL_FROM_ADDRESS'] ?? 'Non défini'],
                ['Destinataire', $to],
            ]
        );

        $io->section('Envoi de l\'email');

        try {
            $htmlContent = '
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px; }
                        .content { padding: 20px; background-color: #f9f9f9; margin-top: 20px; border-radius: 5px; }
                        .info { background-color: #e3f2fd; padding: 15px; margin: 15px 0; border-left: 4px solid #2196F3; }
                        .footer { text-align: center; padding: 10px; font-size: 12px; color: #666; margin-top: 20px; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h1>✅ Email de Test Réussi</h1>
                        </div>
                        <div class="content">
                            <h2>Félicitations !</h2>
                            <p>Si vous recevez cet email, cela signifie que la configuration SMTP de <strong>Efa Smart Finance</strong> fonctionne parfaitement.</p>
                            
                            <div class="info">
                                <h3>📋 Informations de configuration</h3>
                                <p><strong>Serveur SMTP :</strong> mira.o2switch.net</p>
                                <p><strong>Port :</strong> ' . ($_ENV['MAIL_PORT'] ?? '587') . '</p>
                                <p><strong>Expéditeur :</strong> noreply@efasmartfinance.com</p>
                                <p><strong>Date d\'envoi :</strong> ' . date('d/m/Y à H:i:s') . '</p>
                            </div>
                            
                            <p>✨ Votre système d\'envoi d\'emails est maintenant opérationnel en production !</p>
                        </div>
                        <div class="footer">
                            <p>Cet email a été envoyé automatiquement par la commande app:send-test-email</p>
                            <p>&copy; ' . date('Y') . ' Efa Smart Finance - Tous droits réservés</p>
                        </div>
                    </div>
                </body>
                </html>
            ';

            $this->emailService->send(
                $to,
                '✅ Test SMTP Production - Efa Smart Finance',
                $htmlContent
            );

            $io->success([
                '✅ Email envoyé avec succès !',
                '',
                "📬 Vérifiez la boîte de réception de : {$to}",
                "⚠️  N'oubliez pas de vérifier le dossier spam/courrier indésirable",
                '',
                '💡 Si vous ne recevez pas l\'email, vérifiez :',
                '   - Les logs dans var/log/prod.log',
                '   - La configuration SMTP dans .env',
                "   - Que le port " . ($_ENV['MAIL_PORT'] ?? '587') . " n'est pas bloqué",
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error([
                '❌ Erreur lors de l\'envoi de l\'email',
                '',
                'Message d\'erreur : ' . $e->getMessage(),
                'Fichier : ' . $e->getFile() . ':' . $e->getLine(),
            ]);

            $io->section('🔍 Suggestions de dépannage');
            $io->listing([
                'Vérifiez que MAILER_DSN est correctement configuré dans .env',
                'Assurez-vous que le serveur mira.o2switch.net est accessible',
                "Vérifiez que le port " . ($_ENV['MAIL_PORT'] ?? '587') . " n'est pas bloqué par un pare-feu",
                'Confirmez que les identifiants SMTP sont corrects',
                'Vérifiez que l\'extension PHP openssl est activée',
            ]);

            $io->note('Consultez les logs détaillés dans var/log/prod.log');

            return Command::FAILURE;
        }
    }
}
