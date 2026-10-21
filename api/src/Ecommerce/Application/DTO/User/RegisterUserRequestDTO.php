<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\DTO\User;

readonly class RegisterUserRequestDTO
{
    public function __construct(
        public string $email,
        public string $password,
        public string $firstName,
        public string $lastName,
        public ?string $phoneNumber = null,
        public ?array $address = null
    ) {}
}