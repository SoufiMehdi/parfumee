<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Presenter\Catalog\View;

/**
 * The category of a product, as embedded in the product.
 */
final readonly class CategorySummaryView
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }
}
