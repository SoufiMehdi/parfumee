<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\DTO\Catalog;

use Symfony\Component\Validator\Constraints as Assert;

readonly class CreateProductDto
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 255)]
        public string $name,
        // The column is NUMERIC(10, 2): eight digits before the decimal point.
        #[Assert\PositiveOrZero]
        #[Assert\LessThan(100_000_000)]
        public float $price,
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Uuid]
        public string $categoryId,
        public array $attributes = [],
    ) {
    }
}
