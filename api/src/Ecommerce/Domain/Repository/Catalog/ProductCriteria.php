<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Repository\Catalog;

use App\Ecommerce\Domain\Model\Catalog\ProductStatus;
use App\Ecommerce\Domain\Repository\SortDirection;

/**
 * Which products a list holds, and in which order. Every filter is optional.
 */
final readonly class ProductCriteria
{
    /**
     * @param non-empty-list<ProductStatus> $statuses the shop asks for the published products only
     */
    public function __construct(
        public array $statuses = [ProductStatus::PUBLISHED],
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
