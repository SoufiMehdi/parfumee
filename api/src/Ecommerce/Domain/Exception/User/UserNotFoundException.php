<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Exception\User;

class UserNotFoundException extends \DomainException
{
    public function __construct(string $userId)
    {
        parent::__construct(sprintf('User with ID "%s" not found.', $userId));
    }
}
