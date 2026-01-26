<?php

namespace App\Controller\Dashboard;

use App\Entity\User;
use App\Service\CountryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\RequestStack;

#[Route('/dashboard/identity-verification')]
#[IsGranted('ROLE_USER')]
class IdentityVerificationController extends AbstractController
{
    private $entityManager;
    private $countryService;
    private $uploadDirectory;
    private $requestStack;

    public function __construct(EntityManagerInterface $entityManager, CountryService $countryService, string $uploadDirectory, RequestStack $requestStack)
    {
        $this->entityManager = $entityManager;
        $this->countryService = $countryService;
        $this->uploadDirectory = $uploadDirectory;
        $this->requestStack = $requestStack;
    }

  
    
    private function uploadFile(UploadedFile $file, string $directory): string
    {
        // Créer le répertoire s'il n'existe pas
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/' . $directory;
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Générer un nom de fichier unique
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $originalFilename);
        $fileName = $safeFilename . '_' . uniqid() . '.' . $file->guessExtension();
        
        try {
            // Déplacer le fichier vers le répertoire de destination
            $file->move($uploadDir, $fileName);
            
            // Retourner le chemin relatif pour le stockage en base de données
            return '/uploads/' . $directory . '/' . $fileName;
            
        } catch (FileException $e) {
            throw new \Exception('Une erreur est survenue lors du téléchargement du fichier.');
        }
    }
    
    /**
     * @Route("/delete-file", name="app_identity_verification_delete_file", methods={"POST"})
     */
    public function deleteFile(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $data = json_decode($request->getContent(), true);
        if (!isset($data['filename'])) {
            return $this->json(['success' => false, 'message' => 'Nom de fichier manquant'], 400);
        }
        
        $filename = $data['filename'];
        $filePath = $this->getParameter('kernel.project_dir') . '/public' . $filename;
        
        if (file_exists($filePath)) {
            try {
                unlink($filePath);
                return $this->json(['success' => true]);
            } catch (\Exception $e) {
                return $this->json(['success' => false, 'message' => 'Erreur lors de la suppression du fichier'], 500);
            }
        }
        
        return $this->json(['success' => false, 'message' => 'Fichier non trouvé'], 404);
    }

    /**
     * Handle the verification form submission
     */
    #[Route('/submit', name: 'app_verification_submit', methods: ['POST'])]
    public function submitVerification(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        // Get form data
        $data = json_decode($request->getContent(), true);
        $session = $this->requestStack->getSession();
        
        try {
            // Basic info validation
            if (empty($data['firstName']) || empty($data['lastName']) || empty($data['birthDate']) || empty($data['nationality'])) {
                throw new \Exception('Veuillez remplir tous les champs obligatoires.');
            }

            // Document validation
            if (empty($data['documentType']) || empty($data['documentNumber'])) {
                throw new \Exception('Veuvez fournir les informations du document d\'identité.');
            }

            // Save basic information
            $user->setFirstname($data['firstName']);
            $user->setLastname($data['lastName']);
            $user->setBirthDate(new \DateTime($data['birthDate']));
            $user->setNationality($data['nationality']);
            $user->setPhone($data['phone'] ?? null);
            $user->setAddress($data['address'] ?? null);
            $user->setNationality($data['country'] ?? null);

            // Save document information
            $user->setIdentityDocument($data['documentNumber']);
            
            // Mark verification as pending
            $user->setVerificationStatut('pending');
            $user->setVerificationSubmittedAt(new \DateTimeImmutable());

            // Handle file uploads if any
            if (!empty($data['documentFrontPath'])) {
                $user->setDocumentFront($data['documentFrontPath']);
            }
            
            if (!empty($data['documentBackPath'])) {
                $user->setDocumentBack($data['documentBackPath']);
            }
            
            if (!empty($data['selfiePath'])) {
                $user->setSelfie($data['selfiePath']);
            }

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Add flash message for the next request
            $this->addFlash('success', 'Votre demande de vérification a été soumise avec succès. Notre équipe va la traiter sous 24-48h.');

            return $this->json([
                'success' => true,
                'message' => 'Votre demande de vérification a été soumise avec succès.',
                'redirect' => $this->generateUrl('app_dashboard')
            ]);
            
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            
            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue : ' . $e->getMessage()
            ], 400);
        }
    }
}
