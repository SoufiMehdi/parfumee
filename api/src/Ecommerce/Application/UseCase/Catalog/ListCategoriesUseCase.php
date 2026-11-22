<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Application\DTO\Catalog\ListCategoriesQueryDto;
use App\Ecommerce\Domain\Exception\InvalidListQueryException;
use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Repository\Catalog\CategoryCriteria;
use App\Ecommerce\Domain\Repository\Catalog\CategoryRepositoryInterface;
use App\Ecommerce\Domain\Repository\Catalog\CategorySortField;
use App\Ecommerce\Domain\Repository\Page;
use App\Ecommerce\Domain\Repository\Pagination;
use App\Ecommerce\Domain\Repository\SortDirection;

/**
 * One page of the categories, filtered and sorted.
 */
class ListCategoriesUseCase
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository,
    ) {
    }

    /**
     * @return Page<Category>
     */
    public function execute(ListCategoriesQueryDto $query): Page
    {
        // An empty search box sends "q=": it means no search, not a search for nothing.
        $search = trim($query->q ?? '');

        return $this->categoryRepository->findPage(
            new CategoryCriteria(
                search: '' === $search ? null : $search,
                parentId: $query->parentId,
                topLevelOnly: $query->topLevel,
                sortBy: CategorySortField::tryFrom($query->sort)
                    ?? throw new InvalidListQueryException(\sprintf('Categories cannot be sorted by "%s".', $query->sort)),
                direction: SortDirection::tryFrom($query->order)
                    ?? throw new InvalidListQueryException(\sprintf('The sort order must be "asc" or "desc", not "%s".', $query->order)),
            ),
            new Pagination($query->page, $query->perPage),
        );
    }
}
