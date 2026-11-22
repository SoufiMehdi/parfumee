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
use App\Ecommerce\Domain\Model\Catalog\ProductStatus;
use App\Ecommerce\Domain\Repository\Catalog\CategoryRepositoryInterface;
use App\Ecommerce\Domain\Repository\Catalog\ProductRepositoryInterface;
use App\Ecommerce\Domain\Service\Catalog\SlugGeneratorInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UpdateProductUseCaseTest extends TestCase
{
    public function testExecuteUpdatesProductSuccessfully(): void
    {
        // 1. Mocks
        $productRepo = $this->createMock(ProductRepositoryInterface::class);
        $categoryRepo = $this->createMock(CategoryRepositoryInterface::class);
        $attributeMapper = $this->createMock(AttributesMapper::class);
        $attributeMock = $this->createStub(Attribute::class);

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

        // The product as stored once saved: the save recorded its new updated_at.
        $stored = new Product('prod-1', 'Nouveau Nom', 'nouveau-nom', 15.0, $category, createdAt: new \DateTimeImmutable('2026-11-01T09:30:00+00:00'), updatedAt: new \DateTimeImmutable('2026-11-21T10:00:00+00:00'));

        // 3. Configuration
        $productRepo->expects($this->exactly(2))->method('findById')->with('prod-1')->willReturnOnConsecutiveCalls($product, $stored);
        $categoryRepo->expects($this->once())->method('findById')->willReturn($category);

        $attributeMapper
            ->expects($this->once())
            ->method('fromDto')
            ->willReturn($attributeMock);

        $productRepo->expects($this->once())->method('save')->with($this->callback(
            static fn (Product $saved): bool => $saved === $product
                && 'Nouveau Nom' === $saved->getName()
                && 'nouveau-nom' === $saved->getSlug()
                && 15.0 === $saved->getPrice(),
        ));

        // 4. Exécution (Injection du mapper ici)
        $slugGenerator = $this->createMock(SlugGeneratorInterface::class);
        $slugGenerator->expects($this->once())->method('generate')->with('Nouveau Nom')->willReturn('nouveau-nom');
        $useCase = new UpdateProductUseCase($productRepo, $categoryRepo, $attributeMapper, new ProductSlugResolver($slugGenerator, $productRepo));
        $result = $useCase->execute($dto, 'prod-1');

        // 5. Assertions: the product is read again after the save, with the dates the save recorded.
        $this->assertSame($stored, $result);
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

    /**
     * @return iterable<string, array{?string, ProductStatus}>
     */
    public static function statusChanges(): iterable
    {
        yield 'left out: the status is kept' => [null, ProductStatus::PUBLISHED];
        yield 'back to draft' => ['draft', ProductStatus::DRAFT];
        yield 'archived' => ['archived', ProductStatus::ARCHIVED];
    }

    #[DataProvider('statusChanges')]
    public function testTheStatusChangesOnlyWhenOneIsGiven(?string $status, ProductStatus $expected): void
    {
        $category = new Category('cat-1', 'Bougies', 'bougies');
        $product = new Product('prod-1', 'Ambre', 'ambre', 10.0, $category, status: ProductStatus::PUBLISHED);
        $productRepo = $this->createStub(ProductRepositoryInterface::class);
        $productRepo->method('findById')->willReturn($product);
        $categoryRepo = $this->createStub(CategoryRepositoryInterface::class);
        $categoryRepo->method('findById')->willReturn($category);

        $useCase = new UpdateProductUseCase($productRepo, $categoryRepo, new AttributesMapper(), new ProductSlugResolver($this->createStub(SlugGeneratorInterface::class), $productRepo));
        $useCase->execute(new UpdateProductDto(name: 'Ambre', price: 10.0, categoryId: 'cat-1', status: $status), 'prod-1');

        $this->assertSame($expected, $product->getStatus());
    }
}
