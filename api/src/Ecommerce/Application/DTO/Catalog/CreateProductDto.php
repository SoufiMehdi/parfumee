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
        // Unknown keys are ignored. A weight must be a JSON number: "180g" used
        // to be read as 180 without a word.
        #[Assert\Collection(
            fields: [
                'weight' => new Assert\Optional([new Assert\Type(['int', 'float'], message: 'This value should be a number.'), new Assert\PositiveOrZero()]),
                'fragrance' => new Assert\Optional([new Assert\Type('string'), new Assert\Length(max: 255)]),
                'size' => new Assert\Optional([new Assert\Type('string'), new Assert\Length(max: 255)]),
                'description' => new Assert\Optional([new Assert\Type('string'), new Assert\Length(max: 5000)]),
                'presentation' => new Assert\Optional([new Assert\Type('string'), new Assert\Length(max: 5000)]),
            ],
            allowExtraFields: true,
        )]
        public array $attributes = [],
    ) {
    }
}
