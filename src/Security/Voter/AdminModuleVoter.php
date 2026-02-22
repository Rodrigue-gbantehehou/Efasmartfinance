<?php

namespace App\Security\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class AdminModuleVoter extends Voter
{
    public const VIEW = 'VIEW_MODULE';
    public const EDIT = 'EDIT_MODULE';

    public function __construct(
        private \App\Repository\AdminPermissionRepository $permissionRepository
    ) {}

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

        // Super Admin has all permissions
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles())) {
            return true;
        }

        $module = strtolower($subject);
        $roles = $user->getRoles();

        return $this->permissionRepository->hasPermission($roles, $module, $attribute);
    }
}
