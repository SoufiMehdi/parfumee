<?php

namespace App\Tests\Unit\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Application\UseCase\Catalog\GetProductUseCase;
use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Domain\Repository\Catalog\ProductRepositoryInterface;
use PHPUnit\Framework\TestCase;

class GetProductUseCaseTest extends TestCase
{
    private $productRepository;
    private $useCase;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->useCase = new GetProductUseCase($this->productRepository);
    }

    public function testExecuteReturnsProductWhenFound(): void
    {
        $productId = 'uuid-123';
        $category = new Category('cat-1', 'Parfums', 'parfums');
        $expectedProduct = $this->createMock(Product::class);

        $this->productRepository
            ->expects($this->once())
            ->method('findById')
            ->with($productId)
            ->willReturn($expectedProduct);

        $result = $this->useCase->execute($productId);

        $this->assertSame($expectedProduct, $result);
    }

    public function testExecuteReturnsNullWhenProductNotFound(): void
    {
        $this->productRepository
            ->method('findById')
            ->willReturn(null);

        $this->assertNull($this->useCase->execute('non-existent-id'));
    }

    public function testExecuteReturnsNullWhenIdIsNull(): void
    {
        // On vérifie que le repository n'est jamais appelé si l'id est null
        $this->productRepository
            ->expects($this->never())
            ->method('findById');

        $this->assertNull($this->useCase->execute(null));
    }
}