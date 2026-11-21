<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Repository\Catalog;

use App\Ecommerce\Domain\Repository\SortDirection;

/**
 * Which products a list holds, and in which order. Every filter is optional.
 */
final readonly class ProductCriteria
{
    public function __construct(
        public ?string $categoryId = null,
        /** Inclusive. */
        public ?float $minPrice = null,
        /** Inclusive. */
        public ?float $maxPrice = null,
        /** Exact value of the fragrance attribute. */
        public ?string $fragrance = null,
        /** Exact value of the size attribute. */
        public ?string $size = null,
        public ProductSortField $sortBy = ProductSortField::NAME,
        public SortDirection $direction = SortDirection::ASC,
    ) {
    }
}
