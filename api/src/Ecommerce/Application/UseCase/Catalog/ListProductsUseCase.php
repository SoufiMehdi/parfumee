<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Application\DTO\Catalog\ListProductsQueryDto;
use App\Ecommerce\Domain\Exception\InvalidListQueryException;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Domain\Model\Catalog\ProductStatus;
use App\Ecommerce\Domain\Repository\Catalog\ProductCriteria;
use App\Ecommerce\Domain\Repository\Catalog\ProductRepositoryInterface;
use App\Ecommerce\Domain\Repository\Catalog\ProductSortField;
use App\Ecommerce\Domain\Repository\Page;
use App\Ecommerce\Domain\Repository\Pagination;
use App\Ecommerce\Domain\Repository\SortDirection;

/**
 * One page of the catalogue, filtered and sorted. Shared by the shop and the back-office.
 */
class ListProductsUseCase
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
    ) {
    }

    /**
     * @param non-empty-list<ProductStatus> $statuses the statuses the caller may see: the shop sees the published products only
     *
     * @return Page<Product>
     */
    public function execute(ListProductsQueryDto $query, array $statuses): Page
    {
        return $this->productRepository->findPage(
            new ProductCriteria(
                statuses: $statuses,
                categoryId: $query->categoryId,
                minPrice: $query->minPrice,
                maxPrice: $query->maxPrice,
                fragrance: $query->fragrance,
                size: $query->size,
                sortBy: ProductSortField::tryFrom($query->sort)
                    ?? throw new InvalidListQueryException(\sprintf('Products cannot be sorted by "%s".', $query->sort)),
                direction: SortDirection::tryFrom($query->order)
                    ?? throw new InvalidListQueryException(\sprintf('The sort order must be "asc" or "desc", not "%s".', $query->order)),
            ),
            new Pagination($query->page, $query->perPage),
        );
    }
}
