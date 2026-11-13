<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Exception\User;

use App\Ecommerce\Domain\Exception\ConflictExceptionInterface;

class DuplicateEmailException extends \DomainException implements ConflictExceptionInterface
{
    public function __construct(string $email)
    {
        parent::__construct(sprintf('The email "%s" is already taken.', $email));
    }
}
