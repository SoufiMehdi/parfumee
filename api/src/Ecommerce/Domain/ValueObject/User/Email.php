<?php

namespace App\Ecommerce\Domain\ValueObject\User;

use App\Ecommerce\Domain\Exception\User\InvalidEmailException;

class Email
{
    public function __construct(private string $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailException($value);
        }
    }

    public function getValue(): string
    {
        return $this->value;
    }
}