<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\DTO\Catalog;

use Symfony\Component\Validator\Constraints as Assert;

readonly class CreateCategoryDto
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 255)]
        public string $name,
        /** A top-level category to file this one under. Left out: a top-level category. */
        #[Assert\Uuid]
        public ?string $parentId = null,
    ) {
    }
}
