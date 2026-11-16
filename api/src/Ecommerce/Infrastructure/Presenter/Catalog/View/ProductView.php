<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Presenter\Catalog\View;

/**
 * A product, as returned by the API.
 */
final readonly class ProductView
{
    public function __construct(
        public string $id,
        public string $name,
        /** URL-safe name, unique in the catalogue. */
        public string $slug,
        /** Price in euros. */
        public float $price,
        public CategorySummaryView $category,
        public AttributesView $attributes,
        /** @var list<PictureView> */
        public array $pictures,
    ) {
    }
}
