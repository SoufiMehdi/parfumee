<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Exception;

/**
 * Implemented by every business exception. Its message is written for the
 * API client and is always safe to expose.
 *
 * A business exception implements one of the more specific interfaces of
 * this namespace, which tell what kind of failure it is.
 */
interface DomainExceptionInterface extends \Throwable
{
}
