<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\DTO\User;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class LoginUserRequestDto
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        public string $email,
        #[Assert\NotBlank(normalizer: 'trim')]
        public string $password,
    ) {
    }
}
