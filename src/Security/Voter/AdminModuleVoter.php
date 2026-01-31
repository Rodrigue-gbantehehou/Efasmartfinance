<?php

namespace App\Security\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class AdminModuleVoter extends Voter
{
    public const VIEW = 'VIEW_MODULE';
    public const EDIT = 'EDIT_MODULE';

    // Module permissions matrix
    private const PERMISSIONS = [
        'dashboard' => [
            'ROLE_SUPPORT' => [self::VIEW],
            'ROLE_CAISSIER' => [self::VIEW],
            'ROLE_FINANCE' => [self::VIEW],
            'ROLE_ADMIN' => [self::VIEW, self::EDIT],
            'ROLE_SUPER_ADMIN' => [self::VIEW, self::EDIT],
        ],
        'users' => [
            'ROLE_SUPPORT' => [self::VIEW],
            'ROLE_CAISSIER' => [self::VIEW],
            'ROLE_FINANCE' => [self::VIEW],
            'ROLE_ADMIN' => [self::VIEW, self::EDIT],
            'ROLE_SUPER_ADMIN' => [self::VIEW, self::EDIT],
        ],
        'transactions' => [
            'ROLE_FINANCE' => [self::VIEW, self::EDIT],
            'ROLE_ADMIN' => [self::VIEW, self::EDIT],
            'ROLE_SUPER_ADMIN' => [self::VIEW, self::EDIT],
        ],
        'invoices' => [
            'ROLE_FINANCE' => [self::VIEW, self::EDIT],
            'ROLE_ADMIN' => [self::VIEW, self::EDIT],
            'ROLE_SUPER_ADMIN' => [self::VIEW, self::EDIT],
        ],
        'financial' => [
            'ROLE_FINANCE' => [self::VIEW],
            'ROLE_ADMIN' => [self::VIEW, self::EDIT],
            'ROLE_SUPER_ADMIN' => [self::VIEW, self::EDIT],
        ],
        'support' => [
            'ROLE_SUPPORT' => [self::VIEW, self::EDIT],
            'ROLE_FINANCE' => [self::VIEW],
            'ROLE_ADMIN' => [self::VIEW, self::EDIT],
            'ROLE_SUPER_ADMIN' => [self::VIEW, self::EDIT],
        ],
        'audit' => [
            'ROLE_ADMIN' => [self::VIEW],
            'ROLE_SUPER_ADMIN' => [self::VIEW, self::EDIT],
        ],
        'settings' => [
            'ROLE_SUPER_ADMIN' => [self::VIEW, self::EDIT],
        ],
        'tontines' => [
            'ROLE_SUPPORT' => [self::VIEW],
            'ROLE_FINANCE' => [self::VIEW],
            'ROLE_ADMIN' => [self::VIEW, self::EDIT],
            'ROLE_SUPER_ADMIN' => [self::VIEW, self::EDIT],
        ],
        'withdrawals' => [
            'ROLE_FINANCE' => [self::VIEW, self::EDIT],
            'ROLE_ADMIN' => [self::VIEW, self::EDIT],
            'ROLE_SUPER_ADMIN' => [self::VIEW, self::EDIT],
        ],
        'fees' => [
            'ROLE_FINANCE' => [self::VIEW, self::EDIT],
            'ROLE_ADMIN' => [self::VIEW, self::EDIT],
            'ROLE_SUPER_ADMIN' => [self::VIEW, self::EDIT],
        ],
        'reports' => [
            'ROLE_FINANCE' => [self::VIEW],
            'ROLE_ADMIN' => [self::VIEW, self::EDIT],
            'ROLE_SUPER_ADMIN' => [self::VIEW, self::EDIT],
        ],
        'broadcast' => [
            'ROLE_ADMIN' => [self::VIEW, self::EDIT],
            'ROLE_SUPER_ADMIN' => [self::VIEW, self::EDIT],
        ],
        'maintenance' => [
            'ROLE_SUPER_ADMIN' => [self::VIEW, self::EDIT],
        ],
        'caisse' => [
            'ROLE_CAISSIER' => [self::VIEW, self::EDIT],
            'ROLE_FINANCE' => [self::VIEW, self::EDIT],
            'ROLE_ADMIN' => [self::VIEW, self::EDIT],
            'ROLE_SUPER_ADMIN' => [self::VIEW, self::EDIT],
        ],
    ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Check if the attribute is one we support
        if (!in_array($attribute, [self::VIEW, self::EDIT])) {
            return false;
        }

        // Subject should be a module name (string)
        return is_string($subject);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // User must be logged in
        if (!$user instanceof UserInterface) {
            return false;
        }

        $module = strtolower($subject);

        // If module doesn't exist in permissions, deny access
        if (!isset(self::PERMISSIONS[$module])) {
            return false;
        }

        // Check each role the user has
        foreach ($user->getRoles() as $role) {
            if (isset(self::PERMISSIONS[$module][$role])) {
                $allowedActions = self::PERMISSIONS[$module][$role];
                if (in_array($attribute, $allowedActions)) {
                    return true;
                }
            }
        }

        return false;
    }
}
