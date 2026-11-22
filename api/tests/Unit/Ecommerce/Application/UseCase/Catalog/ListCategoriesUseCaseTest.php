<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Application\DTO\Catalog\ListCategoriesQueryDto;
use App\Ecommerce\Application\UseCase\Catalog\ListCategoriesUseCase;
use App\Ecommerce\Domain\Exception\InvalidListQueryException;
use App\Ecommerce\Domain\Repository\Catalog\CategoryCriteria;
use App\Ecommerce\Domain\Repository\Catalog\CategoryRepositoryInterface;
use App\Ecommerce\Domain\Repository\Catalog\CategorySortField;
use App\Ecommerce\Domain\Repository\Page;
use App\Ecommerce\Domain\Repository\Pagination;
use App\Ecommerce\Domain\Repository\SortDirection;
use PHPUnit\Framework\TestCase;

final class ListCategoriesUseCaseTest extends TestCase
{
    private const string PARENT_ID = '11111111-1111-4111-8111-111111111111';

    public function testEveryParameterOfTheQueryReachesTheRepository(): void
    {
        $page = new Page([], 0, new Pagination(page: 2, perPage: 10));
        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->expects($this->once())->method('findPage')->with(
            new CategoryCriteria(search: 'bou', parentId: self::PARENT_ID, topLevelOnly: true, sortBy: CategorySortField::CREATED_AT, direction: SortDirection::DESC),
            new Pagination(page: 2, perPage: 10),
        )->willReturn($page);

        $result = (new ListCategoriesUseCase($repository))->execute(new ListCategoriesQueryDto(
            q: ' bou ',
            parentId: self::PARENT_ID,
            topLevel: true,
            page: 2,
            perPage: 10,
            sort: 'created_at',
            order: 'desc',
        ));

        self::assertSame($page, $result);
    }

    public function testWithoutParametersEveryCategoryIsSortedByName(): void
    {
        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->expects($this->once())->method('findPage')
            ->with(new CategoryCriteria(), new Pagination())
            ->willReturn(new Page([], 0, new Pagination()));

        // An empty search box sends "q=": no search.
        (new ListCategoriesUseCase($repository))->execute(new ListCategoriesQueryDto(q: ''));
    }

    public function testAnUnknownSortIsRefused(): void
    {
        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->expects($this->never())->method('findPage');

        $this->expectException(InvalidListQueryException::class);

        (new ListCategoriesUseCase($repository))->execute(new ListCategoriesQueryDto(sort: 'slug'));
    }
}
