<?php

namespace App\Command;

use App\Entity\User;
use App\Service\PinAuthService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate-to-pin',
    description: 'Migrate existing users from 2FA to PIN authentication',
)]
class MigrateToPinCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PinAuthService $pinAuthService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Migration des utilisateurs vers le système PIN');

        // Get all users without PIN
        $users = $this->entityManager
            ->getRepository(User::class)
            ->createQueryBuilder('u')
            ->leftJoin('u.pinAuth', 'p')
            ->where('p.id IS NULL')
            ->getQuery()
            ->getResult();

        if (empty($users)) {
            $io->success('Tous les utilisateurs ont déjà un code PIN configuré');
            return Command::SUCCESS;
        }

        $io->note(sprintf('Nombre d\'utilisateurs à migrer: %d', count($users)));

        $migrated = 0;
        $errors = 0;

        $io->progressStart(count($users));

        foreach ($users as $user) {
            try {
                // Create temporary PIN for user
                $temporaryPin = $this->pinAuthService->createTemporaryPin($user);

                $io->writeln(sprintf(
                    "\n[OK] Utilisateur %s (%s) - PIN temporaire: %s",
                    $user->getEmail(),
                    $user->getId(),
                    $temporaryPin
                ));

                // PIN is created. User should be notified about their credentials.
                
                $migrated++;
            } catch (\Exception $e) {
                $io->error(sprintf(
                    'Erreur pour l\'utilisateur %s: %s',
                    $user->getEmail(),
                    $e->getMessage()
                ));
                $errors++;
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        $io->newLine(2);
        $io->success(sprintf(
            'Migration terminée: %d utilisateurs migrés, %d erreurs',
            $migrated,
            $errors
        ));

        $io->warning([
            'IMPORTANT: Les codes PIN temporaires ont été affichés ci-dessus.',
            'En production, ces codes devraient être envoyés par email.',
            'Les utilisateurs devront changer leur PIN temporaire à la prochaine connexion.'
        ]);

        return Command::SUCCESS;
    }
}
