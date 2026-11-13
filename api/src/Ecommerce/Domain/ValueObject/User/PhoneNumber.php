<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\ValueObject\User;

use App\Ecommerce\Domain\Exception\User\InvalidPhoneNumberException;

readonly class PhoneNumber
{
    public function __construct(private string $value)
    {
        if (!preg_match('/^\+?[0-9\s\-]{8,20}$/', $value)) {
            throw new InvalidPhoneNumberException();
        }
    }

    public function getValue(): string { return $this->value; }
}