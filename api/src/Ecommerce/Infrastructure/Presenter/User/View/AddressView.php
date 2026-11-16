<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Presenter\User\View;

/**
 * A postal address of an account.
 */
final readonly class AddressView
{
    public function __construct(
        public string $id,
        /** "shipping" or "billing". */
        public string $type,
        public string $street,
        public string $city,
        public string $postalCode,
        public string $country,
        public bool $isDefault,
    ) {
    }
}
