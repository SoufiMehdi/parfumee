<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\DTO\User;

readonly class LoginUserRequestDTO
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}
}
