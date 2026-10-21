<?php

namespace App\Ecommerce\Application\DTO\Catalog;

readonly class CreateProductDto
{
    public function __construct(
        public string $name,
        public float $price,
        public string $categoryId,
        public array $attributes = []
    ) {
    }
}