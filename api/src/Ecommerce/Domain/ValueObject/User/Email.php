<?php

namespace App\Ecommerce\Domain\ValueObject\User;

class Email
{
    public function __construct(private string $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email format: $value");
        }
    }

    public function getValue(): string
    {
        return $this->value;
    }
}