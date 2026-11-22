<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Application\UseCase\Catalog\DeleteCategoryUseCase;
use App\Ecommerce\Domain\Exception\Catalog\CategoryNotEmptyException;
use App\Ecommerce\Domain\Exception\Catalog\CategoryNotFoundException;
use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Repository\Catalog\CategoryRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class DeleteCategoryUseCaseTest extends TestCase
{
    private const string ID = 'cat-1';

    public function testAnEmptyCategoryIsDeleted(): void
    {
        $category = new Category(self::ID, 'Bougies', 'bougies');
        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->method('findById')->willReturn($category);
        $repository->method('hasChildren')->willReturn(false);
        $repository->method('hasProducts')->willReturn(false);
        $repository->expects($this->once())->method('delete')->with($category);

        (new DeleteCategoryUseCase($repository))->execute(self::ID);
    }

    public function testACategoryWithChildrenIsKept(): void
    {
        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->method('findById')->willReturn(new Category(self::ID, 'Bougies', 'bougies'));
        $repository->method('hasChildren')->with(self::ID)->willReturn(true);
        $repository->method('hasProducts')->willReturn(false);
        $repository->expects($this->never())->method('delete');

        $this->expectException(CategoryNotEmptyException::class);
        $this->expectExceptionMessage('The category "cat-1" holds child categories: delete or move them first.');

        (new DeleteCategoryUseCase($repository))->execute(self::ID);
    }

    public function testACategoryWithProductsIsKept(): void
    {
        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->method('findById')->willReturn(new Category(self::ID, 'Bougies', 'bougies'));
        $repository->method('hasChildren')->willReturn(false);
        $repository->method('hasProducts')->with(self::ID)->willReturn(true);
        $repository->expects($this->never())->method('delete');

        $this->expectException(CategoryNotEmptyException::class);
        $this->expectExceptionMessage('The category "cat-1" holds products, archived ones included: move them to another category first.');

        (new DeleteCategoryUseCase($repository))->execute(self::ID);
    }

    public function testAnUnknownCategoryIsNotFound(): void
    {
        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $repository->expects($this->never())->method('delete');

        $this->expectException(CategoryNotFoundException::class);

        (new DeleteCategoryUseCase($repository))->execute(self::ID);
    }
}
