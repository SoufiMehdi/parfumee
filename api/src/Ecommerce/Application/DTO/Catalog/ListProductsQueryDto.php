<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\DTO\Catalog;

use App\Ecommerce\Domain\Repository\Catalog\ProductSortField;
use App\Ecommerce\Domain\Repository\Pagination;
use App\Ecommerce\Domain\Repository\SortDirection;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Query string of the product lists, in the shop and in the back-office. Every parameter is optional.
 */
final readonly class ListProductsQueryDto
{
    public function __construct(
        /** A category: a top-level one gives the products of its child categories too. */
        #[Assert\Uuid]
        public ?string $categoryId = null,
        /** Inclusive. */
        #[Assert\PositiveOrZero]
        public ?float $minPrice = null,
        /** Inclusive. */
        #[Assert\PositiveOrZero]
        #[Assert\GreaterThanOrEqual(propertyPath: 'minPrice')]
        public ?float $maxPrice = null,
        /** Exact value of the fragrance attribute. */
        #[Assert\Length(max: 255)]
        public ?string $fragrance = null,
        /** Exact value of the size attribute. */
        #[Assert\Length(max: 255)]
        public ?string $size = null,
        /** Number of the page, from 1. */
        #[Assert\Range(min: 1, max: Pagination::MAX_PAGE)]
        public int $page = 1,
        /** Maximum number of products on a page. */
        #[Assert\Range(min: 1, max: Pagination::MAX_PER_PAGE)]
        public int $perPage = Pagination::DEFAULT_PER_PAGE,
        /** Field to sort by. Products with the same value are then sorted by id, so that pages never overlap. */
        #[Assert\Choice(callback: [ProductSortField::class, 'values'])]
        public string $sort = ProductSortField::NAME->value,
        #[Assert\Choice(callback: [SortDirection::class, 'values'])]
        public string $order = SortDirection::ASC->value,
    ) {
    }
}
