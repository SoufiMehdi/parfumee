<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Presenter\Catalog\View;

use App\Ecommerce\Infrastructure\Presenter\Common\PaginationView;

/**
 * One page of categories.
 */
final readonly class CategoryListView
{
    public function __construct(
        /** @var list<CategoryView> */
        public array $items,
        public PaginationView $pagination,
    ) {
    }
}
