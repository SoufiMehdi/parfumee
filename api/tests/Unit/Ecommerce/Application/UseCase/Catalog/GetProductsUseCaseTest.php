<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Application\UseCase\Catalog\GetProductsUseCase;
use App\Ecommerce\Domain\Model\Catalog\Attribute;
use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Domain\Repository\Catalog\ProductRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GetProductsUseCaseTest extends TestCase
{
    private ProductRepositoryInterface&MockObject $productRepository;
    private GetProductsUseCase $useCase;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->useCase = new GetProductsUseCase($this->productRepository);
    }

    public function testExecuteReturnsListOfProducts(): void
    {
        // 1. Arrange
        $category = new Category('cat-1', 'Parfums', 'parfums');
        $productList = [
            new Product('p1', 'Oud Royal', 'oud-royal', 120.0, $category, new Attribute()),
            new Product('p2', 'Rose Night', 'rose-night', 85.0, $category, new Attribute()),
        ];

        $this->productRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn($productList);

        // 2. Act
        $result = $this->useCase->execute();

        // 3. Assert
        $this->assertCount(2, $result);
        $this->assertSame($productList, $result);
        $this->assertInstanceOf(Product::class, $result[0]);
    }

    public function testExecuteReturnsEmptyArrayWhenNoProducts(): void
    {
        // 1. Arrange
        $this->productRepository
            ->method('findAll')
            ->willReturn([]);

        // 2. Act
        $result = $this->useCase->execute();

        // 3. Assert
        $this->assertEmpty($result);
    }
}
