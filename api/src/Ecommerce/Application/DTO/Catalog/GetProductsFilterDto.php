<?php

namespace App\Ecommerce\Application\DTO\Catalog;

class GetProductsFilterDto
{
    public function __construct(
        public ?string $categoryId = null,
        public ?float $minPrice = null,
        public ?float $maxPrice = null,
        public ?string $scent = null,  // Parfum
        public ?string $size = null    // Taille / Contenance
    ) {
    }
}
