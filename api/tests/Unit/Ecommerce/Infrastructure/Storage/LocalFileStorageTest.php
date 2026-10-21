<?php

namespace App\Tests\Unit\Ecommerce\Infrastructure\Storage;

use App\Ecommerce\Infrastructure\Storage\LocalFileStorage;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\String\UnicodeString;

class LocalFileStorageTest extends TestCase
{
    private string $uploadsDirectory;
    private SluggerInterface&MockObject $slugger;
    private LocalFileStorage $storage;

    protected function setUp(): void
    {
        $this->uploadsDirectory = sys_get_temp_dir() . '/uploads';
        $this->slugger = $this->createMock(SluggerInterface::class);
        $this->storage = new LocalFileStorage($this->uploadsDirectory, $this->slugger);

        if (!is_dir($this->uploadsDirectory)) {
            mkdir($this->uploadsDirectory, 0777, true);
        }
    }

    public function testStoreSuccessfully(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $file->expects($this->once())->method('getClientOriginalName')->willReturn('test image.jpg');
        $file->expects($this->once())->method('guessExtension')->willReturn('jpg');
        
        // Simuler le déplacement du fichier
        $file->expects($this->once())
            ->method('move')
            ->with($this->stringContains($this->uploadsDirectory . '/products'), $this->anything());

        $this->slugger->method('slug')->willReturn(new UnicodeString('test-image'));

        $result = $this->storage->store($file, 'products');

        $this->assertStringStartsWith('products/', $result);
        $this->assertStringEndsWith('.jpg', $result);
    }

    public function testDeleteExistingFile(): void
    {
        $filePath = 'test.txt';
        $fullPath = $this->uploadsDirectory . '/' . $filePath;
        touch($fullPath);

        $this->assertFileExists($fullPath);
        $this->storage->delete($filePath);
        $this->assertFileDoesNotExist($fullPath);
    }

    public function testDeleteNonExistentFileDoesNotThrowException(): void
    {
        $this->storage->delete('non-existent.txt');
        $this->assertTrue(true); // Pas d'exception attendue
    }
}