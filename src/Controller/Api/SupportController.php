<?php

namespace App\Controller\Api;

use App\Entity\ContactSupport;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;

class SupportController extends AbstractController
{
    public function __construct(
        private EmailService $emailService,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/api/support', name: 'api_support_submit', methods: ['POST'])]
    public function submit(
        Request $request,
        ValidatorInterface $validator
    ): JsonResponse {
        // Récupérer les données du formulaire
        $data = $request->request->all();
        $file = $request->files->get('attachment');
        
        // Valider les données
        $constraints = new Assert\Collection([
            'subject' => [
                new Assert\NotBlank(['message' => 'Le sujet est requis.']),
                new Assert\Choice([
                    'choices' => ['technical', 'payment', 'account', 'tontine', 'security', 'other'],
                    'message' => 'Le sujet sélectionné n\'est pas valide.'
                ])
            ],
            'message' => [
                new Assert\NotBlank(['message' => 'Le message est requis.']),
                new Assert\Length([
                    'min' => 10,
                    'minMessage' => 'Le message doit contenir au moins {{ limit }} caractères.'
                ])
            ],
            'email' => [
                new Assert\NotBlank(['message' => 'L\'email est requis.']),
                new Assert\Email(['message' => 'L\'email n\'est pas valide.'])
            ],
            'name' => [
                new Assert\NotBlank(['message' => 'Le nom est requis.']),
                new Assert\Length([
                    'min' => 2,
                    'max' => 100,
                    'minMessage' => 'Le nom doit contenir au moins {{ limit }} caractères.',
                    'maxMessage' => 'Le nom ne peut pas dépasser {{ limit }} caractères.'
                ])
            ]
        ]);

        $violations = $validator->validate($data, $constraints);

        // Valider le fichier si présent
        if ($file) {
            $fileConstraints = [
                new Assert\File([
                    'maxSize' => '5M',
                    'mimeTypes' => [
                        'image/*',
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                    ],
                    'mimeTypesMessage' => 'Veuillez télécharger un fichier valide (image, PDF ou document Word)',
                ])
            ];
            
            $fileViolations = $validator->validate($file, $fileConstraints);
            $violations->addAll($fileViolations);
        }

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $field = preg_replace('/[\[\]]/', '', $violation->getPropertyPath());
                $errors[$field] = $violation->getMessage();
            }
            
            return $this->json([
                'status' => 'error',
                'message' => 'Des erreurs de validation sont survenues',
                'errors' => $errors
            ], 400);
        }

        try {
            // Sauvegarder dans la base de données
            $contact = new ContactSupport();
            $contact->setSujet($data['subject']);
            $contact->setDescription($data['message']);
            $contact->setCreatedAt(new \DateTimeImmutable());

            // Associer l'utilisateur connecté s'il existe
            if ($this->getUser()) {
                $contact->setUtilisateur($this->getUser());
            }

            // Gérer le fichier joint s'il existe
            $fileName = null;
            if ($file) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/var/uploads/support';
                $newFilename = uniqid() . '.' . $file->guessExtension();
                
                // Créer le répertoire s'il n'existe pas
                if (!file_exists($uploadsDir)) {
                    mkdir($uploadsDir, 0777, true);
                }
                
                // Déplacer le fichier
                $file->move($uploadsDir, $newFilename);
                
                // Sauvegarder le chemin relatif du fichier
                $contact->setFichier('var/uploads/support/' . $newFilename);
                $fileName = $newFilename;
            }

            // Enregistrer en base de données
            $this->entityManager->persist($contact);
            $this->entityManager->flush();

            // Préparer le contenu HTML de l'email
            $htmlContent = $this->renderView('emails/support_notification.html.twig', [
                'name' => $data['name'],
                'email' => $data['email'],
                'subject' => $data['subject'],
                'message' => $data['message']
            ]);

            // Envoyer l'email via le service
            try {
                $this->emailService->send(
                    $this->getParameter('app.admin_email'),
                    sprintf('[Support] Nouvelle demande : %s', $data['subject']),
                    $htmlContent
                );
                error_log('Email sent successfully');
            } catch (\Exception $e) {
                error_log('Email sending failed: ' . $e->getMessage());
                error_log('Email sending failed. Trace: ' . $e->getTraceAsString());
                throw $e; // Re-throw to be caught by the outer try-catch
            }

            return $this->json([
                'status' => 'success',
                'message' => 'Votre demande a été envoyée avec succès. Notre équipe vous contactera bientôt.'
            ]);

        } catch (\Exception $e) {
            error_log('Email sending failed: ' . $e->getMessage());
            error_log('Email sending failed. Trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }
}
