<?php

namespace App\Ecommerce\Infrastructure\Storage;

use App\Ecommerce\Domain\Storage\FileStorageInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class LocalFileStorage implements FileStorageInterface
{
    public function __construct(
        private string $uploadsDirectory, // Configuré dans services.yaml
        private SluggerInterface $slugger
    ) {
    }

    public function store(UploadedFile $file, string $directory): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $fileName = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        $file->move($this->uploadsDirectory . '/' . $directory, $fileName);

        return $directory . '/' . $fileName;
    }

    public function delete(string $filePath): void
    {
        $fullPath = $this->uploadsDirectory . '/' . $filePath;
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }
}