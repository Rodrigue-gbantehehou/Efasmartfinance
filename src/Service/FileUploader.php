<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class FileUploader
{
    private string $targetDirectory;
    private SluggerInterface $slugger;

    public function __construct(string $targetDirectory, SluggerInterface $slugger)
    {
        $this->targetDirectory = $targetDirectory;
        $this->slugger = $slugger;
    }

    public function upload(UploadedFile $file, string $subDirectory = ''): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $fileName = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();
        
        $uploadDir = $this->targetDirectory . '/' . trim($subDirectory, '/');
        
        // Créer le répertoire s'il n'existe pas
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        try {
            $file->move($uploadDir, $fileName);
        } catch (FileException $e) {
            throw new \Exception('Une erreur est survenue lors du téléchargement du fichier.');
        }
        
        return $fileName;
    }
    
    public function getTargetDirectory(): string
    {
        return $this->targetDirectory;
    }
    
    public function delete(string $path): bool
    {
        $filePath = $this->targetDirectory . '/' . ltrim($path, '/');
        
        if (file_exists($filePath) && is_file($filePath)) {
            return unlink($filePath);
        }
        
        return false;
    }
}
