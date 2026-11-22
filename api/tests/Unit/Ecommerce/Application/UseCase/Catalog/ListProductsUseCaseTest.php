<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Application\DTO\Catalog\ListProductsQueryDto;
use App\Ecommerce\Application\UseCase\Catalog\ListProductsUseCase;
use App\Ecommerce\Domain\Exception\InvalidListQueryException;
use App\Ecommerce\Domain\Model\Catalog\ProductStatus;
use App\Ecommerce\Domain\Repository\Catalog\ProductCriteria;
use App\Ecommerce\Domain\Repository\Catalog\ProductRepositoryInterface;
use App\Ecommerce\Domain\Repository\Catalog\ProductSortField;
use App\Ecommerce\Domain\Repository\Page;
use App\Ecommerce\Domain\Repository\Pagination;
use App\Ecommerce\Domain\Repository\SortDirection;
use PHPUnit\Framework\TestCase;

final class ListProductsUseCaseTest extends TestCase
{
    private const string CATEGORY_ID = '11111111-1111-4111-8111-111111111111';

    public function testEveryParameterOfTheQueryReachesTheRepository(): void
    {
        $page = new Page([], 0, new Pagination(page: 3, perPage: 50));
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->expects($this->once())->method('findPage')->with(
            new ProductCriteria(
                statuses: [ProductStatus::DRAFT, ProductStatus::ARCHIVED],
                categoryId: self::CATEGORY_ID,
                minPrice: 10.0,
                maxPrice: 30.0,
                fragrance: 'amber',
                size: 'M',
                sortBy: ProductSortField::PRICE,
                direction: SortDirection::DESC,
            ),
            new Pagination(page: 3, perPage: 50),
        )->willReturn($page);

        $result = (new ListProductsUseCase($repository))->execute(new ListProductsQueryDto(
            categoryId: self::CATEGORY_ID,
            minPrice: 10.0,
            maxPrice: 30.0,
            fragrance: 'amber',
            size: 'M',
            page: 3,
            perPage: 50,
            sort: 'price',
            order: 'desc',
        ), [ProductStatus::DRAFT, ProductStatus::ARCHIVED]);

        self::assertSame($page, $result);
    }

    public function testWithoutParametersTheFirstPageIsSortedByName(): void
    {
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->expects($this->once())->method('findPage')
            ->with(new ProductCriteria(statuses: [ProductStatus::PUBLISHED], sortBy: ProductSortField::NAME, direction: SortDirection::ASC), new Pagination())
            ->willReturn(new Page([], 0, new Pagination()));

        (new ListProductsUseCase($repository))->execute(new ListProductsQueryDto(), [ProductStatus::PUBLISHED]);
    }

    public function testAnUnknownSortIsRefused(): void
    {
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->expects($this->never())->method('findPage');

        $this->expectException(InvalidListQueryException::class);

        (new ListProductsUseCase($repository))->execute(new ListProductsQueryDto(sort: 'stock'), [ProductStatus::PUBLISHED]);
    }

    public function testAnUnknownOrderIsRefused(): void
    {
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->expects($this->never())->method('findPage');

        $this->expectException(InvalidListQueryException::class);

        (new ListProductsUseCase($repository))->execute(new ListProductsQueryDto(order: 'sideways'), [ProductStatus::PUBLISHED]);
    }
}
