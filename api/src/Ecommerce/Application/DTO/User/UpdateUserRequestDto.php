<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\DTO\User;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateUserRequestDto
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 255)]
        public string $firstName,
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 255)]
        public string $lastName,
        #[Assert\Length(max: 20)]
        public ?string $phoneNumber = null,
    ) {
    }
}
