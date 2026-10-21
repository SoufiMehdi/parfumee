<?php

namespace App\Ecommerce\Domain\Storage;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface FileStorageInterface
{
    public function store(UploadedFile $file, string $destination): string;
    public function delete(string $filePath): void;
}