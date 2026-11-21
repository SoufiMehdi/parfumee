<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Exception;

/**
 * A list was asked for with a page, a page size or a sort that does not exist.
 *
 * The query string DTOs refuse these values first, with a violation per field:
 * this exception is the last line of defence of the domain.
 */
class InvalidListQueryException extends \DomainException implements ValidationExceptionInterface
{
}
