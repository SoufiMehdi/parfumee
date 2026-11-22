<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Application\UseCase\Catalog\GetCategoryUseCase;
use App\Ecommerce\Domain\Exception\Catalog\CategoryNotFoundException;
use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Repository\Catalog\CategoryRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class GetCategoryUseCaseTest extends TestCase
{
    public function testTheCategoryIsReturned(): void
    {
        $category = new Category('cat-1', 'Bougies', 'bougies');
        $repository = $this->createStub(CategoryRepositoryInterface::class);
        $repository->method('findById')->willReturn($category);

        self::assertSame($category, (new GetCategoryUseCase($repository))->execute('cat-1'));
    }

    public function testAnUnknownCategoryIsNotFound(): void
    {
        $repository = $this->createStub(CategoryRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $this->expectException(CategoryNotFoundException::class);

        (new GetCategoryUseCase($repository))->execute('cat-1');
    }
}
