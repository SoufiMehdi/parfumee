<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Exception;

/**
 * The operation conflicts with the current state (for example a duplicate).
 */
interface ConflictExceptionInterface extends DomainExceptionInterface
{
}
