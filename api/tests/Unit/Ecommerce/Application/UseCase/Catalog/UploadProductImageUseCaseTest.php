<?php

namespace App\Tests\Unit\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Application\UseCase\Catalog\UploadProductImageUseCase;
use App\Ecommerce\Domain\Model\Catalog\Picture;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Domain\Repository\Catalog\PictureRepositoryInterface;
use App\Ecommerce\Domain\Repository\Catalog\ProductRepositoryInterface;
use App\Ecommerce\Domain\Storage\FileStorageInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploadProductImageUseCaseTest extends TestCase
{
    private ProductRepositoryInterface|MockObject $productRepository;
    private FileStorageInterface|MockObject $fileStorage;
    private PictureRepositoryInterface|MockObject $pictureRepository;
    private UploadProductImageUseCase $useCase;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->fileStorage = $this->createMock(FileStorageInterface::class);
        $this->pictureRepository = $this->createMock(PictureRepositoryInterface::class);

        $this->useCase = new UploadProductImageUseCase(
            $this->productRepository,
            $this->fileStorage,
            $this->pictureRepository
        );
    }

    public function testExecuteSuccessfully(): void
    {
        $productId = 'product-uuid';
        $filePath = 'products/image.jpg';
        
        $product = $this->createMock(Product::class);
        $file = $this->createMock(UploadedFile::class);

        $this->productRepository->expects($this->once())
            ->method('findById')
            ->with($productId)
            ->willReturn($product);

        $this->fileStorage->expects($this->once())
            ->method('store')
            ->with($file, 'products')
            ->willReturn($filePath);

        $product->expects($this->once())
            ->method('addPicture')
            ->with($this->isInstanceOf(Picture::class));

        $this->productRepository->expects($this->once())
            ->method('save')
            ->with($product);

        $this->pictureRepository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Picture::class));

        $result = $this->useCase->execute($productId, $file, 'Alt text');

        $this->assertInstanceOf(Picture::class, $result);
        $this->assertEquals($filePath, $result->url());
    }

    public function testExecuteThrowsExceptionWhenProductNotFound(): void
    {
        $this->productRepository->method('findById')->willReturn(null);
        
        $file = $this->createMock(UploadedFile::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Produit non trouvé");

        $this->useCase->execute('invalid-id', $file);
    }
}