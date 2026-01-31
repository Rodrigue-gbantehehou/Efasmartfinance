<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

#[AsCommand(
    name: 'app:test-email',
    description: 'Test the email sending functionality',
)]
class TestEmailCommand extends Command
{
    private MailerInterface $mailer;
    private string $adminEmail;
    private string $supportEmail;

    public function __construct(
        MailerInterface $mailer,
        string $adminEmail = 'admin@efasmartfinance.com',
        string $supportEmail = 'support@efasmartfinance.com'
    ) {
        parent::__construct();
        $this->mailer = $mailer;
        $this->adminEmail = $adminEmail;
        $this->supportEmail = $supportEmail;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Test the email sending functionality')
            ->addArgument('to', InputArgument::OPTIONAL, 'Email recipient', $this->adminEmail ?? 'rodriguenagnon@gmail.com')
            ->addArgument('subject', InputArgument::OPTIONAL, 'Email subject', 'Test Email from EFA Smart Finance')
            ->addArgument('message', InputArgument::OPTIONAL, 'Email message', 'This is a test email to verify the email service is working correctly.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $to = $input->getArgument('to');
        $subject = $input->getArgument('subject');
        $message = $input->getArgument('message');

        try {
            $io->section('Testing email sending...');
            $io->text(sprintf('From: %s', $this->supportEmail));
            $io->text(sprintf('To: %s', $to));
            $io->text(sprintf('Subject: %s', $subject));
            $io->text('Message: ' . $message);

            $email = (new Email())
                ->from($this->supportEmail)
                ->to($to)
                ->subject($subject)
                ->text($message)
                ->html(sprintf('<p>%s</p>', nl2br(htmlspecialchars($message))));

            // Add a test attachment
            $email->attach('This is a test attachment', 'test_attachment.txt', 'text/plain');

            $this->mailer->send($email);
            
            $io->success('Email sent successfully!');
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Error sending email: %s', $e->getMessage()));
            $io->error($e->getTraceAsString());
            
            return Command::FAILURE;
        }
    }
}
