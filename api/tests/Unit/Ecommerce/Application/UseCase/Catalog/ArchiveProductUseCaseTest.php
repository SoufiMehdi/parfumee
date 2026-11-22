<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Application\UseCase\Catalog\ArchiveProductUseCase;
use App\Ecommerce\Domain\Exception\Catalog\ProductNotFoundException;
use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Domain\Model\Catalog\ProductStatus;
use App\Ecommerce\Domain\Repository\Catalog\ProductRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class ArchiveProductUseCaseTest extends TestCase
{
    public function testTheProductIsArchivedAndSaved(): void
    {
        $product = new Product('prod-1', 'Ambre', 'ambre', 10.0, new Category('cat-1', 'Bougies', 'bougies'), status: ProductStatus::PUBLISHED);
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->method('findById')->willReturn($product);
        $repository->expects($this->once())->method('save')
            ->with($this->callback(static fn (Product $saved): bool => $saved === $product && ProductStatus::ARCHIVED === $saved->getStatus()));

        (new ArchiveProductUseCase($repository))->execute('prod-1');
    }

    public function testAnUnknownProductIsNotFoundAndNothingIsSaved(): void
    {
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $repository->expects($this->never())->method('save');

        $this->expectException(ProductNotFoundException::class);

        (new ArchiveProductUseCase($repository))->execute('unknown');
    }
}
