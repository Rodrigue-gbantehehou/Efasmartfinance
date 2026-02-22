<?php

namespace App\Controller;

use App\Form\ContactType;
use Symfony\Component\Mime\Email;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ContactController extends AbstractController
{
    public function __construct(
        private \App\Service\EmailService $emailService,
        private string $contactEmail
    ) {}

    #[Route('/contact', name: 'app_contact')]
    public function index(Request $request, NotifierInterface $notifier): Response
    {
        $form = $this->createForm(ContactType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            
            try {
                // Utilisation de EmailService pour l'envoi
                $this->emailService->send(
                    $this->contactEmail,
                    'Nouveau message de contact: ' . $data['subject'],
                    $this->renderView(
                        'emails/contact.html.twig',
                        ['data' => $data]
                    ),
                    $data['email'] // On utilise l'email de l'expéditeur comme 'From'
                );
                
                // Notification de succès
                $notifier->send(new Notification(
                    'Votre message a été envoyé avec succès ! Nous vous répondrons dans les plus brefs délais.',
                    ['browser']
                ));
                
                return $this->redirectToRoute('app_contact');
            } catch (\Exception $e) {
                // En cas d'erreur d'envoi d'email
                $this->addFlash('error', 'Une erreur est survenue lors de l\'envoi de votre message. Veuillez réessayer plus tard.');
            }
        }

        return $this->render('pages/contact.html.twig', [
            'form' => $form->createView(),
        ]);
    }

      #[Route('/faq', name: 'app_faq')]
    public function faq(): Response
    {
        return $this->render('pages/faq.html.twig');
    }

    #[Route('/a-propos', name: 'app_about')]
    public function about(): Response
    {
        return $this->render('pages/about.html.twig');
    }

    #[Route('/confidentialite', name: 'app_privacy')]
    public function privacy(): Response
    {
        return $this->render('pages/privacy.html.twig');
    }

    #[Route('/cgu', name: 'app_cgu')]
    public function cgu(): Response
    {
        return $this->render('pages/cgu.html.twig');
    }

    #[Route('/engagement-ethique', name: 'app_ethics')]
    public function ethics(): Response
    {
        return $this->render('pages/ethics.html.twig');
    }

    #[Route('/mentions-legales', name: 'app_mentions')]
    public function mentions(): Response
    {
        return $this->render('pages/mentions_legales.html.twig');
    }
}
