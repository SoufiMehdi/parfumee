<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Repository\Catalog;

use App\Ecommerce\Domain\Repository\SortDirection;

/**
 * Which categories a list holds, and in which order.
 */
final readonly class CategoryCriteria
{
    public function __construct(
        /** Part of the name, whatever the case. */
        public ?string $search = null,
        /** The children of this category only. */
        public ?string $parentId = null,
        /** The top-level categories only. */
        public bool $topLevelOnly = false,
        public CategorySortField $sortBy = CategorySortField::NAME,
        public SortDirection $direction = SortDirection::ASC,
    ) {
    }
}
