<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Exception\Catalog;

use App\Ecommerce\Domain\Exception\ValidationExceptionInterface;

class InvalidPriceException extends \DomainException implements ValidationExceptionInterface
{
    public function __construct(float $price)
    {
        parent::__construct(sprintf('Invalid price: %f. Price must be a non-negative value.', $price));
    }
}
