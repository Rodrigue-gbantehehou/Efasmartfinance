<?php

namespace App\Command;

use App\Entity\Tontine;
use App\Repository\TontineRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:tontine:monitor',
    description: 'Surveille le cycle de vie des tontines (rappels et clôtures automatiques)',
)]
class TontineMonitorCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TontineRepository $tontineRepository,
        private EmailService $emailService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = new \DateTimeImmutable();

        // 1. Clôture des tontines expirées ou complètes (Statut 'active' -> 'completed')
        $activeTontines = $this->tontineRepository->findBy(['statut' => 'active']);
        $closedCount = 0;

        foreach ($activeTontines as $tontine) {
            if ($tontine->isComplete()) {
                $tontine->setStatut('completed');
                $tontine->setEndedAt($now);
                
                $this->emailService->sendTontineCompletion($tontine->getUtilisateur(), $tontine);
                $closedCount++;
            }
        }

        // 2. Rappels de paiement (Échéance à J+1)
        $tomorrow = (new \DateTime())->modify('+1 day')->format('Y-m-d');
        $remindedCount = 0;

        foreach ($activeTontines as $tontine) {
            $dueDate = $tontine->getNextDueDate();
            if ($dueDate && $dueDate->format('Y-m-d') === $tomorrow) {
                $this->emailService->sendTontineReminder($tontine->getUtilisateur(), $tontine);
                $remindedCount++;
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Monitor tontine terminé : %d clôturées, %d rappels envoyés.',
            $closedCount,
            $remindedCount
        ));

        return Command::SUCCESS;
    }
}
