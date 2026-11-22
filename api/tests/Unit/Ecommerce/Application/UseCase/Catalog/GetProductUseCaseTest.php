<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Application\UseCase\Catalog\GetProductUseCase;
use App\Ecommerce\Domain\Exception\Catalog\ProductNotFoundException;
use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Domain\Model\Catalog\ProductStatus;
use App\Ecommerce\Domain\Repository\Catalog\ProductRepositoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GetProductUseCaseTest extends TestCase
{
    private const string PRODUCT_ID = 'uuid-123';

    public function testExecuteReturnsProductWhenFound(): void
    {
        $product = self::product(ProductStatus::PUBLISHED);
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->expects($this->once())->method('findById')->with(self::PRODUCT_ID)->willReturn($product);

        $result = (new GetProductUseCase($repository))->execute(self::PRODUCT_ID, [ProductStatus::PUBLISHED]);

        $this->assertSame($product, $result);
    }

    public function testExecuteThrowsWhenProductNotFound(): void
    {
        $repository = $this->createStub(ProductRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $this->expectException(ProductNotFoundException::class);
        $this->expectExceptionMessage('Product with id "non-existent-id" not found.');

        (new GetProductUseCase($repository))->execute('non-existent-id', ProductStatus::cases());
    }

    /**
     * The shop asks for the published products: a draft or an archived product must look
     * exactly like one that does not exist, message included.
     */
    #[DataProvider('hiddenStatuses')]
    public function testAProductInAStatusTheCallerMayNotSeeIsNotFound(ProductStatus $status): void
    {
        $repository = $this->createStub(ProductRepositoryInterface::class);
        $repository->method('findById')->willReturn(self::product($status));

        $this->expectException(ProductNotFoundException::class);
        $this->expectExceptionMessage('Product with id "uuid-123" not found.');

        (new GetProductUseCase($repository))->execute(self::PRODUCT_ID, [ProductStatus::PUBLISHED]);
    }

    /**
     * @return iterable<string, array{ProductStatus}>
     */
    public static function hiddenStatuses(): iterable
    {
        yield 'draft' => [ProductStatus::DRAFT];
        yield 'archived' => [ProductStatus::ARCHIVED];
    }

    public function testTheBackOfficeReadsAnArchivedProduct(): void
    {
        $product = self::product(ProductStatus::ARCHIVED);
        $repository = $this->createStub(ProductRepositoryInterface::class);
        $repository->method('findById')->willReturn($product);

        $this->assertSame($product, (new GetProductUseCase($repository))->execute(self::PRODUCT_ID, ProductStatus::cases()));
    }

    private static function product(ProductStatus $status): Product
    {
        return new Product(self::PRODUCT_ID, 'Bougie', 'bougie', 24.9, new Category('cat-1', 'Parfums', 'parfums'), status: $status);
    }
}
