<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Exception;

/**
 * A value breaks a business rule.
 */
interface ValidationExceptionInterface extends DomainExceptionInterface
{
}
