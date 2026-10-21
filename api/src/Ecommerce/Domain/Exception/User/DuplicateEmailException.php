<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Exception\User;

class DuplicateEmailException extends \DomainException
{
    public function __construct(string $email)
    {
        parent::__construct(sprintf('The email "%s" is already taken.', $email));
    }
}
