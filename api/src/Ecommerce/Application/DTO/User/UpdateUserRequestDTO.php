<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\DTO\User;

readonly class UpdateUserRequestDTO
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?string $phoneNumber = null,
    ) {}
}
