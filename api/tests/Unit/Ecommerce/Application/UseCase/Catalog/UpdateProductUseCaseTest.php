<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Application\DTO\Catalog\UpdateProductDto;
use App\Ecommerce\Application\Mapper\AttributesMapper;
use App\Ecommerce\Application\Service\Catalog\ProductSlugResolver;
use App\Ecommerce\Application\UseCase\Catalog\UpdateProductUseCase;
use App\Ecommerce\Domain\Model\Catalog\Attribute;
use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Domain\Repository\Catalog\CategoryRepositoryInterface;
use App\Ecommerce\Domain\Repository\Catalog\ProductRepositoryInterface;
use App\Ecommerce\Domain\Service\Catalog\SlugGeneratorInterface;
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
        $slugGenerator = $this->createMock(SlugGeneratorInterface::class);
        $slugGenerator->expects($this->once())->method('generate')->with('Nouveau Nom')->willReturn('nouveau-nom');
        $useCase = new UpdateProductUseCase($productRepo, $categoryRepo, $attributeMapper, new ProductSlugResolver($slugGenerator, $productRepo));
        $result = $useCase->execute($dto, 'prod-1');

        // 5. Assertions
        $this->assertInstanceOf(Product::class, $result);
        $this->assertEquals('Nouveau Nom', $result->getName());
        $this->assertSame('nouveau-nom', $result->getSlug());
        $this->assertEquals(15.0, $result->getPrice());
    }

    public function testTheSlugIsKeptWhenTheNameDoesNotChange(): void
    {
        $productRepo = $this->createMock(ProductRepositoryInterface::class);
        $categoryRepo = $this->createStub(CategoryRepositoryInterface::class);
        $slugGenerator = $this->createMock(SlugGeneratorInterface::class);

        $category = new Category('cat-1', 'Bougies', 'bougies');
        // "ambre-2": the suffix it got at creation must survive an edit of the price.
        $product = new Product('prod-1', 'Ambre', 'ambre-2', 10.0, $category, new Attribute());

        $productRepo->method('findById')->willReturn($product);
        $productRepo->expects($this->never())->method('slugExists');
        $productRepo->expects($this->once())->method('save');
        $categoryRepo->method('findById')->willReturn($category);
        $slugGenerator->expects($this->never())->method('generate');

        $useCase = new UpdateProductUseCase($productRepo, $categoryRepo, new AttributesMapper(), new ProductSlugResolver($slugGenerator, $productRepo));
        $result = $useCase->execute(new UpdateProductDto(name: 'Ambre', price: 12.0, categoryId: 'cat-1', attributes: []), 'prod-1');

        $this->assertSame('ambre-2', $result->getSlug());
        $this->assertSame(12.0, $result->getPrice());
    }
}
