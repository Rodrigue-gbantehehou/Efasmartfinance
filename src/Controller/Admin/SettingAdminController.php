<?php

namespace App\Controller\Admin;

use App\Form\SettingType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/settings')]
class SettingAdminController extends AbstractController
{
    #[Route('', name: 'admin_settings')]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        // Récupérer les paramètres actuels (à adapter selon votre logique métier)
        $settings = [
            'site_name' => 'EFA SMART FINANCE',
            'contact_email' => 'contact@efasmartfinance.com',
            'items_per_page' => 10,
            'maintenance_mode' => false,
        ];

        $form = $this->createForm(SettingType::class, $settings);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Traiter la sauvegarde des paramètres
            // Ici, vous devriez enregistrer les paramètres en base de données
            
            $this->addFlash('success', 'Les paramètres ont été mis à jour avec succès.');
            return $this->redirectToRoute('admin_settings');
        }

        return $this->render('admin/settings/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/email', name: 'admin_settings_email')]
    public function email(): Response
    {
        // Page de configuration des emails
        return $this->render('admin/settings/email.html.twig');
    }

    #[Route('/notifications', name: 'admin_settings_notifications')]
    public function notifications(): Response
    {
        // Page de configuration des notifications
        return $this->render('admin/settings/notifications.html.twig');
    }
}
