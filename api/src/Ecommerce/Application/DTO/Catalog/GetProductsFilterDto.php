<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\DTO\Catalog;

use Symfony\Component\Validator\Constraints as Assert;

class GetProductsFilterDto
{
    public function __construct(
        #[Assert\Uuid]
        public ?string $categoryId = null,
        #[Assert\PositiveOrZero]
        public ?float $minPrice = null,
        #[Assert\PositiveOrZero]
        #[Assert\GreaterThanOrEqual(propertyPath: 'minPrice')]
        public ?float $maxPrice = null,
        #[Assert\Length(max: 255)]
        public ?string $scent = null,
        #[Assert\Length(max: 255)]
        public ?string $size = null,
    ) {
    }
}
