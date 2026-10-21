<?php

namespace App\Tests\Unit\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Application\DTO\Catalog\CreateProductDto;
use App\Ecommerce\Application\Mapper\AttributesMapper;
use App\Ecommerce\Application\UseCase\Catalog\CreateProductUseCase;
use App\Ecommerce\Domain\Exception\Catalog\CategorieNotFoundException;
use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Domain\Repository\Catalog\CategoryRepositoryInterface;
use App\Ecommerce\Domain\Repository\Catalog\ProductRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class CreateProductUseCaseTest extends TestCase
{
    private $productRepository;
    private $categoryRepository;
    private $attributesMapper;
    private $useCase;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->categoryRepository = $this->createMock(CategoryRepositoryInterface::class);
        $this->attributesMapper = $this->createMock(AttributesMapper::class);

        $this->useCase = new CreateProductUseCase(
            $this->productRepository,
            $this->categoryRepository,
            $this->attributesMapper
        );
    }

    public function testExecuteSuccess(): void
    {
        // 1. Préparation (Arrange)
        $categoryId = Uuid::v4();
        $dto = new CreateProductDto(
            name: 'Bougie Parfumée',
            price: 25.50,
            categoryId: $categoryId->toRfc4122(),
            attributes: ['fragrance' => 'Vanille']
        );

        $category = new Category($categoryId, 'Bougies', 'bougies');

        $this->categoryRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($category);

        $this->productRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Product::class));

        // 2. Exécution (Act)
        $result = $this->useCase->execute($dto);

        // 3. Vérifications (Assert)
        $this->assertIsString($result);
        $this->assertTrue(Uuid::isValid($result));
    }

    public function testExecuteThrowsExceptionWhenCategoryNotFound(): void
    {
        // 1. Préparation
        $dto = new CreateProductDto(
            name: 'Produit Sans Catégorie',
            price: 10.0,
            categoryId: Uuid::v4()->toRfc4122(),
            attributes: []
        );

        $this->categoryRepository
            ->method('findById')
            ->willReturn(null);

        $this->productRepository
            ->expects($this->never())
            ->method('save');

        // 2 & 3. Vérification de l'exception
        $this->expectException(CategorieNotFoundException::class);
        
        $this->useCase->execute($dto);
    }
}