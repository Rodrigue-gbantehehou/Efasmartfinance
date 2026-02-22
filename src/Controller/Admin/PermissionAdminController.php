<?php

namespace App\Controller\Admin;

use App\Entity\AdminPermission;
use App\Repository\AdminPermissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/permissions')]
#[IsGranted('ROLE_SUPER_ADMIN')]
class PermissionAdminController extends AbstractController
{
    private const MODULES = [
        'dashboard', 'users', 'transactions', 'invoices', 'financial',
        'support', 'audit', 'settings', 'tontines', 'withdrawals',
        'fees', 'reports', 'broadcast', 'maintenance', 'caisse'
    ];

    private const ROLES = [
        'ROLE_SUPPORT', 'ROLE_CAISSIER', 'ROLE_FINANCE', 'ROLE_SUPERVISOR',
        'ROLE_COMPTABLE', 'ROLE_MANAGER', 'ROLE_ADMIN'
    ];

    #[Route('', name: 'admin_permissions_index')]
    public function index(AdminPermissionRepository $repository): Response
    {
        $permissions = $repository->findAll();
        $matrix = [];

        foreach ($permissions as $p) {
            $matrix[$p->getModule()][$p->getRole()][] = $p->getPermission();
        }

        return $this->render('admin/pages/permissions/index.html.twig', [
            'modules' => self::MODULES,
            'roles' => self::ROLES,
            'matrix' => $matrix,
        ]);
    }

    #[Route('/toggle', name: 'admin_permissions_toggle', methods: ['POST'])]
    public function toggle(Request $request, AdminPermissionRepository $repository, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $role = $data['role'] ?? null;
        $module = $data['module'] ?? null;
        $permission = $data['permission'] ?? null;

        if (!$role || !$module || !$permission) {
            return new JsonResponse(['error' => 'Missing data'], 400);
        }

        $existing = $repository->findOneBy([
            'role' => $role,
            'module' => $module,
            'permission' => $permission
        ]);

        if ($existing) {
            $em->remove($existing);
            $action = 'removed';
        } else {
            $new = new AdminPermission();
            $new->setRole($role);
            $new->setModule($module);
            $new->setPermission($permission);
            $em->persist($new);
            $action = 'added';
        }

        $em->flush();

        return new JsonResponse(['status' => 'success', 'action' => $action]);
    }
}
