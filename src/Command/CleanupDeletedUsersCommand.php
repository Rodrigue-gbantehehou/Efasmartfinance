<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\ActivityLogger;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:cleanup-deleted',
    description: 'Permanently anonymizes accounts that have exceeded the 30-day grace period and sends warnings.',
)]
class CleanupDeletedUsersCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private ActivityLogger $activityLogger,
        private EmailService $emailService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Cleanup Deleted Users & Notifications');

        // --- PART 1: SEND 2-DAY WARNING EMAILS ---
        // Find users pending deletion for 28 days or more, who haven't received a warning yet
        $warningDeadline = new \DateTimeImmutable('-28 days');
        
        $usersToWarn = $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from('App\Entity\User', 'u')
            ->where('u.deletionRequestedAt <= :deadline')
            ->andWhere('u.deletedAt IS NULL')
            ->andWhere('u.deletionWarningSentAt IS NULL')
            ->setParameter('deadline', $warningDeadline)
            ->getQuery()
            ->getResult();

        if (count($usersToWarn) > 0) {
            $io->section('Envoi des avertissements (J-2)');
            foreach ($usersToWarn as $user) {
                $this->emailService->sendDeletionWarningEmail($user);
                $user->setDeletionWarningSentAt(new \DateTimeImmutable());
                $io->text(sprintf(' - Avertissement envoyé à %s', $user->getEmail()));
            }
            $this->entityManager->flush();
            $io->success(sprintf('%d avertissement(s) envoyé(s).', count($usersToWarn)));
        }

        // --- PART 2: PERMANENT ANONYMIZATION ---
        // Find users pending deletion for more than 30 days
        $finalDeadline = new \DateTimeImmutable('-30 days');
        
        $usersToAnonymize = $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from('App\Entity\User', 'u')
            ->where('u.deletionRequestedAt <= :deadline')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('deadline', $finalDeadline)
            ->getQuery()
            ->getResult();

        $count = count($usersToAnonymize);

        if ($count === 0) {
            $io->success('Aucun compte à anonymiser.');
            return Command::SUCCESS;
        }

        $io->section('Anonymisation des comptes (J-0)');
        $io->note(sprintf('Traitement de %d compte(s)...', $count));

        foreach ($usersToAnonymize as $user) {
            $userId = $user->getId();
            $userEmail = $user->getEmail();
            $firstname = $user->getFirstname();

            $user->anonymize();
            
            // Send final confirmation email
            $this->emailService->sendAccountAnonymizedEmail($userEmail, $firstname);
            
            $this->activityLogger->log(
                $user,
                'ACCOUNT_PERMANENTLY_ANONYMIZED',
                'User',
                $userId,
                sprintf('Compte anonymisé pour %s après expiration du délai de 30 jours.', $userEmail)
            );

            $io->text(sprintf(' - Compte ID %d (%s) anonymisé.', $userId, $userEmail));
        }

        $this->entityManager->flush();

        $io->success(sprintf('%d compte(s) ont été anonymisés avec succès.', $count));

        return Command::SUCCESS;
    }
}
