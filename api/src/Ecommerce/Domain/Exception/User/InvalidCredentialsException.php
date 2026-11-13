<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Exception\User;

use App\Ecommerce\Domain\Exception\AuthenticationExceptionInterface;

/**
 * The message is the same whether the email is unknown or the password is
 * wrong, so that a caller cannot find out which accounts exist.
 */
final class InvalidCredentialsException extends \DomainException implements AuthenticationExceptionInterface
{
    public function __construct()
    {
        parent::__construct('Invalid credentials.');
    }
}
