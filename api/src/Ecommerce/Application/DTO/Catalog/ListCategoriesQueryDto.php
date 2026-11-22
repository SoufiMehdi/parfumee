<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\DTO\Catalog;

use App\Ecommerce\Domain\Repository\Catalog\CategorySortField;
use App\Ecommerce\Domain\Repository\Pagination;
use App\Ecommerce\Domain\Repository\SortDirection;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Query string of the category list of the back-office. Every parameter is optional.
 */
final readonly class ListCategoriesQueryDto
{
    public function __construct(
        /** Part of the name, whatever the case. */
        #[Assert\Length(max: 255)]
        public ?string $q = null,
        /** The children of this category only. */
        #[Assert\Uuid]
        public ?string $parentId = null,
        /** true: the top-level categories only. */
        public bool $topLevel = false,
        /** Number of the page, from 1. */
        #[Assert\Range(min: 1, max: Pagination::MAX_PAGE)]
        public int $page = 1,
        /** Maximum number of categories on a page. */
        #[Assert\Range(min: 1, max: Pagination::MAX_PER_PAGE)]
        public int $perPage = Pagination::DEFAULT_PER_PAGE,
        /** Field to sort by. Categories with the same value are then sorted by id, so that pages never overlap. */
        #[Assert\Choice(callback: [CategorySortField::class, 'values'])]
        public string $sort = CategorySortField::NAME->value,
        #[Assert\Choice(callback: [SortDirection::class, 'values'])]
        public string $order = SortDirection::ASC->value,
    ) {
    }
}
