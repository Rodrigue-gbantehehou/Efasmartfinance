<?php

namespace App\Controller\Dashboard;

use App\Entity\User;
use App\Entity\UserVerification;
use App\Service\FileUploader;
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
    private $fileUploader;
    private $requestStack;

    public function __construct(
        EntityManagerInterface $entityManager,
        CountryService $countryService,
        RequestStack $requestStack,
        FileUploader $fileUploader
    ) {
        $this->entityManager = $entityManager;
        $this->countryService = $countryService;
        $this->fileUploader = $fileUploader;
        $this->requestStack = $requestStack;
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
        // Le filename est stocké sous la forme /uploads/documents/xxx.jpg
        // On enlève le /uploads/ initial pour FileUploader
        $path = str_replace('/uploads/', '', $filename);
        
        if ($this->fileUploader->delete($path)) {
            return $this->json(['success' => true]);
        }
        
        return $this->json(['success' => false, 'message' => 'Fichier non trouvé ou erreur lors de la suppression'], 404);
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
        
        try {
            // Basic info validation
            if (empty($data['firstName']) || empty($data['lastName']) || empty($data['birthDate']) || empty($data['nationality'])) {
                throw new \Exception('Veuillez remplir tous les champs obligatoires.');
            }

            // Document validation
            if (empty($data['documentType']) || empty($data['documentNumber'])) {
                throw new \Exception('Veuvez fournir les informations du document d\'identité.');
            }

            // Save basic information in User profile
            $user->setFirstname($data['firstName']);
            $user->setLastname($data['lastName']);
            $user->setBirthDate(new \DateTime($data['birthDate']));
            $user->setNationality($data['country'] ?? $data['nationality']);
            $user->setPhoneNumber($data['phone'] ?? null);
            $user->setAddress($data['address'] ?? null);

            // Always create a NEW UserVerification for history
            $verification = new \App\Entity\UserVerification();
            $user->addVerification($verification);
            
            // Save document information
            $identityData = [
                'type' => $data['documentType'],
                'number' => $data['documentNumber']
            ];
            $verification->setIdentityData(json_encode($identityData));
            
            // Mark verification as pending
            $verification->setStatus('pending');
            $verification->setSubmittedAt(new \DateTimeImmutable());

            // Handle file uploads
            if (!empty($data['documentFrontPath'])) {
                $verification->setDocumentFront($data['documentFrontPath']);
            }
            
            if (!empty($data['selfiePath'])) {
                $verification->setSelfie($data['selfiePath']);
            }

            $this->entityManager->persist($verification);
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Add flash message
            $this->addFlash('success', 'Votre demande de vérification a été soumise avec succès. Notre équipe va la traiter sous 24-48h.');

            return $this->json([
                'success' => true,
                'message' => 'Votre demande de vérification a été soumise avec succès.',
                'redirect' => $this->generateUrl('app_dashboard')
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Une erreur est survenue : ' . $e->getMessage()
            ], 400);
        }
    }
}
