<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Exception\User;

use App\Ecommerce\Domain\Exception\ValidationExceptionInterface;

final class InvalidPhoneNumberException extends \DomainException implements ValidationExceptionInterface
{
    public function __construct()
    {
        parent::__construct('Invalid phone number format');
    }
}
