<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Exception\Catalog;

use App\Ecommerce\Domain\Exception\ValidationExceptionInterface;

/**
 * A product status that does not exist.
 *
 * The DTOs refuse it first, with a violation on the field: this exception is the last line
 * of defence, for a caller that would build a DTO without validating it.
 */
final class InvalidProductStatusException extends \DomainException implements ValidationExceptionInterface
{
    public function __construct(string $status)
    {
        parent::__construct(\sprintf('"%s" is not a product status: draft, published or archived.', $status));
    }
}
