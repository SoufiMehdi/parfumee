<?php

namespace App\Tests\Unit\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Application\DTO\Catalog\UpdateProductDto;
use App\Ecommerce\Application\UseCase\Catalog\UpdateProductUseCase;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Repository\Catalog\ProductRepositoryInterface;
use App\Ecommerce\Domain\Repository\Catalog\CategoryRepositoryInterface;
use App\Ecommerce\Domain\Model\Catalog\Attribute;
use App\Ecommerce\Application\Mapper\AttributesMapper;
use PHPUnit\Framework\TestCase;

class UpdateProductUseCaseTest extends TestCase
{
    public function testExecuteUpdatesProductSuccessfully(): void
    {
        // 1. Mocks
        $productRepo = $this->createMock(ProductRepositoryInterface::class);
        $categoryRepo = $this->createMock(CategoryRepositoryInterface::class);
        $attributeMapper = $this->createMock(AttributesMapper::class);
        $attributeMock = $this->createMock(Attribute::class);

        // 2. Préparation
        $category = new Category('cat-1', 'Bougies', 'bougies');
        // On suppose que Product possède un constructeur compatible
        $product = new Product('prod-1', 'Ancien Nom', 'ancien-nom', 10.0, $category, new Attribute());

        $dto = new UpdateProductDto(
            name: 'Nouveau Nom',
            price: 15.0,
            categoryId: 'cat-1',
            attributes: ['fragrance' => 'Lavande']    
        );

        // 3. Configuration
        $productRepo->expects($this->once())->method('findById')->willReturn($product);
        $categoryRepo->expects($this->once())->method('findById')->willReturn($category);
        
        $attributeMapper
            ->expects($this->once())
            ->method('fromDto')
            ->willReturn($attributeMock);

        $productRepo->expects($this->once())->method('save');

        // 4. Exécution (Injection du mapper ici)
        $useCase = new UpdateProductUseCase($productRepo, $categoryRepo, $attributeMapper);
        $result = $useCase->execute($dto, 'prod-1');

        // 5. Assertions
        $this->assertInstanceOf(Product::class, $result);
        $this->assertEquals('Nouveau Nom', $result->getName());
        $this->assertEquals(15.0, $result->getPrice());
    }
}