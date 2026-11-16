<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Presenter\Catalog\View;

/**
 * The characteristics of a product. Every one is optional.
 */
final readonly class AttributesView
{
    public function __construct(
        /** Weight in grams. */
        public ?float $weight,
        public ?string $fragrance,
        public ?string $size,
        public ?string $description,
        public ?string $presentation,
    ) {
    }
}
