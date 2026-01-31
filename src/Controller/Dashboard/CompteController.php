<?php

namespace App\Controller\Dashboard;

use App\Entity\User;
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
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/mon-compte/verification-identite', name: 'app_compte_verification')]
    public function verificationIdentite(Request $request): Response
    {
        // Debug: Vérifier que la méthode est appelée
        dump('Méthode verificationIdentite appelée');

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
                $frontPath = $this->uploadFile($documentFront, 'documents');
                $selfiePath = $selfie ? $this->uploadFile($selfie, 'selfies') : null;

                // Mise à jour des chemins des fichiers
                $user->setDocumentFront($frontPath);
                $user->setSelfie($selfiePath);

                // Mise à jour des informations du document d'identité
                $documentInfo = [
                    'type' => $form->get('documentType')->getData(),
                    'number' => $form->get('documentNumber')->getData(),
                    'expiry' => $form->get('expiryDate')->getData() ? $form->get('expiryDate')->getData()->format('Y-m-d') : null
                ];

                $user->setIdentityDocument(json_encode($documentInfo));
                $user->setIsVerified(true);
                $user->setVerificationStatut('pending');
                $user->setVerificationSubmittedAt(new \DateTimeImmutable());

                $this->entityManager->persist($user);
                $this->entityManager->flush();

                $this->addFlash('success', 'Votre demande de vérification a été soumise avec succès. Notre équipe va la traiter sous 24-48h.');
                return $this->redirectToRoute('app_dashboard');
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

    /**
     * Fonction utilitaire pour gérer le téléchargement des fichiers
     * 
     * @param UploadedFile $file Le fichier à télécharger
     * @param string $directory Le répertoire de destination
     * @return string|null Le chemin du fichier téléchargé ou null en cas d'erreur
     */
    private function uploadFile($file, $directory): ?string
    {
        if (!$file) {
            return null;
        }

        // Créer un nom de fichier unique
        $fileName = md5(uniqid()) . '.' . $file->guessExtension();

        // Déplacer le fichier vers le répertoire de destination
        try {
            $file->move(
                $this->getParameter('kernel.project_dir') . '/public/uploads/' . $directory,
                $fileName
            );

            return 'uploads/' . $directory . '/' . $fileName;
        } catch (FileException $e) {
            // Gérer l'erreur de téléchargement
            $this->addFlash('error', 'Une erreur est survenue lors du téléchargement du fichier : ' . $e->getMessage());
            return null;
        }
    }
}
