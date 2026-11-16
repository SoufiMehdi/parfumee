<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Presenter\User\View;

/**
 * An account, as returned by the API. It never carries the password.
 */
final readonly class UserView
{
    public function __construct(
        public string $id,
        public string $email,
        public string $firstName,
        public string $lastName,
        public ?string $phoneNumber,
        /** @var list<string> */
        public array $roles,
        /** @var list<AddressView> */
        public array $addresses,
    ) {
    }
}
