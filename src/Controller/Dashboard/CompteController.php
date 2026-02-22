<?php

namespace App\Controller\Dashboard;

use App\Service\FileUploader;
use App\Entity\User;
use App\Entity\UserVerification;
use App\Form\VerificationIdentiteType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class CompteController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FileUploader $fileUploader
    ) {}

    #[Route('/mon-compte/verification-identite', name: 'app_compte_verification')]
    public function verificationIdentite(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Créer le formulaire
        $form = $this->createForm(VerificationIdentiteType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gestion des fichiers téléchargés
            $documentFront = $form->get('documentFront')->getData();
            $selfie = $form->get('selfie')->getData();

            // Traitement des fichiers téléchargés
            if ($documentFront) {
                try {
                    $frontPath = 'uploads/documents/' . $this->fileUploader->upload($documentFront, 'documents');
                    $selfiePath = $selfie ? 'uploads/selfies/' . $this->fileUploader->upload($selfie, 'selfies') : null;

                // Always create a NEW UserVerification for history
                $verification = new UserVerification();
                $user->addVerification($verification);

                // Mise à jour des chemins des fichiers
                $verification->setDocumentFront($frontPath);
                $verification->setSelfie($selfiePath);

                // Mise à jour des informations du document d'identité
                $documentInfo = [
                    'type' => $form->get('documentType')->getData(),
                    'number' => $form->get('documentNumber')->getData(),
                    'expiry' => $form->get('expiryDate')->getData() ? $form->get('expiryDate')->getData()->format('Y-m-d') : null
                ];

                $verification->setIdentityData(json_encode($documentInfo));
                $verification->setStatus('pending');
                $verification->setSubmittedAt(new \DateTimeImmutable());

                $this->entityManager->persist($verification);
                $this->entityManager->flush();

                $this->addFlash('success', 'Votre demande de vérification a été soumise avec succès. Notre équipe va la traiter sous 24-48h.');
                return $this->redirectToRoute('app_dashboard');
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors de l\'upload : ' . $e->getMessage());
                    return $this->redirectToRoute('app_compte_verification');
                }
            } else {
                $this->addFlash('error', 'Veuillez télécharger le recto de votre pièce d\'identité.');
            }
        } elseif ($form->isSubmitted() && !$form->isValid()) {
            // Afficher les erreurs de validation
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }
            $this->addFlash('error', 'Veuillez corriger les erreurs dans le formulaire : ' . implode(' ', $errors));
        }

        // Passer le formulaire à la vue
        // CompteController.php - dans la méthode verificationIdentite()
        return $this->render('dashboard/pages/compte/activecompte.html.twig', [
            'formcompte' => $form->createView(),
            'user' => $user, // Important: passer l'utilisateur aussi
        ]);
    }

}
