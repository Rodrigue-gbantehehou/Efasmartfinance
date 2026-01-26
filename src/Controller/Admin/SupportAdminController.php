<?php

namespace App\Controller\Admin;

use App\Service\EmailService;
use App\Entity\ContactSupport;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\ContactSupportRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Psr\Log\LoggerInterface;

#[Route('/admin/support')]
class SupportAdminController extends AbstractController
{
    #[Route('', name: 'admin_support')]
    public function index(ContactSupportRepository $contactSupportRepository): Response
    {
        $tickets = $contactSupportRepository->findBy([], ['createdAt' => 'DESC']);
        
        return $this->render('admin/pages/support/index.html.twig', [
            'tickets' => $tickets,
        ]);
    }

    #[Route('/ticket/{id}', name: 'admin_support_ticket_show', methods: ['GET'])]
    public function show(ContactSupport $ticket): Response
    {
        return $this->render('admin/pages/support/show.html.twig', [
            'ticket' => $ticket,
        ]);
    }

    #[Route('/ticket/{id}/respond', name: 'admin_support_ticket_respond', methods: ['POST'])]
    public function respond(Request $request, ContactSupport $ticket, EntityManagerInterface $entityManager, EmailService $emailService, LoggerInterface $logger): Response
    {
        $response = trim($request->request->get('response', ''));
        
        if (empty($response)) {
            $this->addFlash('error', 'La réponse ne peut pas être vide.');
            return $this->redirectToRoute('admin_support_ticket_show', ['id' => $ticket->getId()]);
        }
        
        // Enregistrer la réponse et l'administrateur qui a répondu
        $ticket->setReponse($response);
        $ticket->setRepliedBy($this->getUser());
        
        // Si c'est la première réponse, on peut aussi fermer le ticket automatiquement
        // ou laisser l'administrateur le fermer manuellement
        
        $entityManager->persist($ticket);
        $entityManager->flush();
        
        // Envoyer un email à l'utilisateur
        if ($ticket->getUtilisateur()) {
            try {
                $emailContent = $this->renderView('emails/support_response.html.twig', [
                    'ticket' => $ticket,
                ]);
                
                $userEmail = $ticket->getUtilisateur()->getEmail();
                $logger->info("Tentative d'envoi d'email à: " . $userEmail);
                
                $emailService->send(
                    $userEmail,
                    'Réponse à votre demande concernant: ' . $ticket->getSujet(),
                    $emailContent
                );
                
                $logger->info("Email envoyé avec succès à: " . $userEmail);
            } catch (\Exception $e) {
                $errorMessage = sprintf(
                    "Erreur lors de l'envoi de l'email à %s: %s",
                    $ticket->getUtilisateur()->getEmail(),
                    $e->getMessage()
                );
                $logger->error($errorMessage, ['exception' => $e]);
                $this->addFlash('warning', 'La réponse a été enregistrée mais une erreur est survenue lors de l\'envoi de l\'email de notification.');
            }
        }
        
        $this->addFlash('success', 'Votre réponse a été enregistrée avec succès.');
        return $this->redirectToRoute('admin_support_ticket_show', ['id' => $ticket->getId()]);
    }

    #[Route('/ticket/{id}/close', name: 'admin_support_ticket_close', methods: ['POST'])]
    public function close(ContactSupport $ticket, EntityManagerInterface $entityManager): Response
    {
        if ($ticket->isClosed()) {
            $ticket->reopen();
            $message = 'Le ticket a été rouvert avec succès.';
        } else {
            $ticket->close();
            $message = 'Le ticket a été fermé avec succès.';
        }
        
        $entityManager->persist($ticket);
        $entityManager->flush();
        
        $this->addFlash('success', $message);
        return $this->redirectToRoute('admin_support_ticket_show', ['id' => $ticket->getId()]);
    }
}
