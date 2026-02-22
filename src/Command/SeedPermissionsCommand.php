<?php

namespace App\Command;

use App\Entity\AdminPermission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-permissions',
    description: 'Seeds the AdminPermission table with initial hardcoded permissions.',
)]
class SeedPermissionsCommand extends Command
{
    private const PERMISSIONS = [
        'dashboard' => [
            'ROLE_SUPPORT' => ['VIEW_MODULE'],
            'ROLE_CAISSIER' => ['VIEW_MODULE'],
            'ROLE_FINANCE' => ['VIEW_MODULE'],
            'ROLE_SUPERVISOR' => ['VIEW_MODULE'],
            'ROLE_COMPTABLE' => ['VIEW_MODULE'],
            'ROLE_MANAGER' => ['VIEW_MODULE'],
            'ROLE_ADMIN' => ['VIEW_MODULE', 'EDIT_MODULE'],
            'ROLE_SUPER_ADMIN' => ['VIEW_MODULE', 'EDIT_MODULE'],
        ],
        'users' => [
            'ROLE_SUPPORT' => ['VIEW_MODULE'],
            'ROLE_CAISSIER' => ['VIEW_MODULE'],
            'ROLE_FINANCE' => ['VIEW_MODULE'],
            'ROLE_SUPERVISOR' => ['VIEW_MODULE'],
            'ROLE_COMPTABLE' => ['VIEW_MODULE'],
            'ROLE_MANAGER' => ['VIEW_MODULE', 'EDIT_MODULE'],
            'ROLE_ADMIN' => ['VIEW_MODULE', 'EDIT_MODULE'],
            'ROLE_SUPER_ADMIN' => ['VIEW_MODULE', 'EDIT_MODULE'],
        ],
        'transactions' => [
            'ROLE_COMPTABLE' => ['VIEW_MODULE', 'EDIT_MODULE'],
            'ROLE_FINANCE' => ['VIEW_MODULE', 'EDIT_MODULE'],
            'ROLE_ADMIN' => ['VIEW_MODULE', 'EDIT_MODULE'],
            'ROLE_SUPER_ADMIN' => ['VIEW_MODULE', 'EDIT_MODULE'],
        ],
        'invoices' => [
            'ROLE_COMPTABLE' => ['VIEW_MODULE', 'EDIT_MODULE'],
            'ROLE_FINANCE' => ['VIEW_MODULE', 'EDIT_MODULE'],
            'ROLE_ADMIN' => ['VIEW_MODULE', 'EDIT_MODULE'],
            'ROLE_SUPER_ADMIN' => ['VIEW_MODULE', 'EDIT_MODULE'],
        ],
        'financial' => [
            'ROLE_COMPTABLE' => ['VIEW_MODULE'],
            'ROLE_FINANCE' => ['VIEW_MODULE'],
            'ROLE_ADMIN' => ['VIEW_MODULE', 'EDIT_MODULE'],
            'ROLE_SUPER_ADMIN' => ['VIEW_MODULE', 'EDIT_MODULE'],
        ],
        'support' => [
            'ROLE_SUPPORT' => ['VIEW_MODULE', 'EDIT_MODULE'],
            'ROLE_SUPERVISOR' => ['VIEW_MODULE'],
            'ROLE_MANAGER' => ['VIEW_MODULE', 'EDIT_MODULE'],
            'ROLE_FINANCE' => ['VIEW_MODULE'],
            'ROLE_ADMIN' => ['VIEW_MODULE', 'EDIT_MODULE'],
            'ROLE_SUPER_ADMIN' => ['VIEW_MODULE', 'EDIT_MODULE'],
        ],
        'audit' => [
            'ROLE_ADMIN' => ['VIEW_MODULE'],
            'ROLE_SUPER_ADMIN' => ['VIEW_MODULE', 'EDIT_MODULE'],
        ],
        'settings' => [
            'ROLE_SUPER_ADMIN' => ['VIEW_MODULE', 'EDIT_MODULE'],
        ],
        'tontines' => [
            'ROLE_SUPPORT' => ['VIEW_MODULE'],
            'ROLE_SUPERVISOR' => ['VIEW_MODULE'],
            'ROLE_MANAGER' => ['VIEW_MODULE', 'EDIT_MODULE'],
            'ROLE_FINANCE' => ['VIEW_MODULE'],
            'ROLE_ADMIN' => ['VIEW_MODULE', 'EDIT_MODULE'],
            'ROLE_SUPER_ADMIN' => ['VIEW_MODULE', 'EDIT_MODULE'],
        ],
        'withdrawals' => [
            'ROLE_COMPTABLE' => ['VIEW_MODULE', 'EDIT_MODULE'],
            'ROLE_FINANCE' => ['VIEW_MODULE', 'EDIT_MODULE'],
            'ROLE_ADMIN' => ['VIEW_MODULE', 'EDIT_MODULE'],
            'ROLE_SUPER_ADMIN' => ['VIEW_MODULE', 'EDIT_MODULE'],
        ],
        'fees' => [
            'ROLE_COMPTABLE' => ['VIEW_MODULE'],
            'ROLE_FINANCE' => ['VIEW_MODULE', 'EDIT_MODULE'],
            'ROLE_ADMIN' => ['VIEW_MODULE', 'EDIT_MODULE'],
            'ROLE_SUPER_ADMIN' => ['VIEW_MODULE', 'EDIT_MODULE'],
        ],
        'reports' => [
            'ROLE_COMPTABLE' => ['VIEW_MODULE'],
            'ROLE_MANAGER' => ['VIEW_MODULE'],
            'ROLE_FINANCE' => ['VIEW_MODULE'],
            'ROLE_ADMIN' => ['VIEW_MODULE', 'EDIT_MODULE'],
            'ROLE_SUPER_ADMIN' => ['VIEW_MODULE', 'EDIT_MODULE'],
        ],
        'broadcast' => [
            'ROLE_ADMIN' => ['VIEW_MODULE', 'EDIT_MODULE'],
            'ROLE_SUPER_ADMIN' => ['VIEW_MODULE', 'EDIT_MODULE'],
        ],
        'maintenance' => [
            'ROLE_SUPER_ADMIN' => ['VIEW_MODULE', 'EDIT_MODULE'],
        ],
        'caisse' => [
            'ROLE_CAISSIER' => ['VIEW_MODULE', 'EDIT_MODULE'],
            'ROLE_MANAGER' => ['VIEW_MODULE', 'EDIT_MODULE'],
            'ROLE_FINANCE' => ['VIEW_MODULE', 'EDIT_MODULE'],
            'ROLE_ADMIN' => ['VIEW_MODULE', 'EDIT_MODULE'],
            'ROLE_SUPER_ADMIN' => ['VIEW_MODULE', 'EDIT_MODULE'],
        ],
    ];

    public function __construct(private EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        foreach (self::PERMISSIONS as $module => $roles) {
            foreach ($roles as $role => $perms) {
                foreach ($perms as $perm) {
                    $adminPermission = new AdminPermission();
                    $adminPermission->setModule($module);
                    $adminPermission->setRole($role);
                    $adminPermission->setPermission($perm);
                    $this->entityManager->persist($adminPermission);
                }
            }
        }

        $this->entityManager->flush();

        $io->success('Initial permissions have been seeded into the database.');

        return Command::SUCCESS;
    }
}
