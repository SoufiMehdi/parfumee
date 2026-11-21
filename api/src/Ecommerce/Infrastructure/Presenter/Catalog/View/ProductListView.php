<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Presenter\Catalog\View;

use App\Ecommerce\Infrastructure\Presenter\Common\PaginationView;

/**
 * One page of products.
 */
final readonly class ProductListView
{
    public function __construct(
        /** @var list<ProductView> */
        public array $items,
        public PaginationView $pagination,
    ) {
    }
}
