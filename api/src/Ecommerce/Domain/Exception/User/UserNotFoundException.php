<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Exception\User;

use App\Ecommerce\Domain\Exception\NotFoundExceptionInterface;

class UserNotFoundException extends \DomainException implements NotFoundExceptionInterface
{
    public function __construct(string $userId)
    {
        parent::__construct(sprintf('User with ID "%s" not found.', $userId));
    }
}
