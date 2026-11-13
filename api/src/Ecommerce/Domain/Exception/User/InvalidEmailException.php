<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Exception\User;

use App\Ecommerce\Domain\Exception\ValidationExceptionInterface;

final class InvalidEmailException extends \DomainException implements ValidationExceptionInterface
{
    public function __construct(string $email)
    {
        parent::__construct(\sprintf('Invalid email format: %s', $email));
    }
}
