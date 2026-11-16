<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Exception\User;

use App\Ecommerce\Domain\Exception\ValidationExceptionInterface;

class UnknownRoleException extends \DomainException implements ValidationExceptionInterface
{
    public function __construct(string $role)
    {
        parent::__construct(\sprintf('The role "%s" does not exist.', $role));
    }
}
