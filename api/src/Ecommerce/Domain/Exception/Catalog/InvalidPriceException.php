<?php 

namespace App\Ecommerce\Domain\Exception\Catalog;

use App\Ecommerce\Domain\Exception\DomainExceptionInterface;
use DomainException;

class InvalidPriceException extends DomainException implements DomainExceptionInterface
{
    public function __construct(float $price)
    {
        parent::__construct(sprintf('Invalid price: %f. Price must be a non-negative value.', $price));
    }
}