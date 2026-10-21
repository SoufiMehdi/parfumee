<?php

namespace App\Ecommerce\Domain\Exception\Catalog;

use App\Ecommerce\Domain\Exception\DomainExceptionInterface;
use DomainException;

class CategorieNotFoundException extends DomainException implements DomainExceptionInterface
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Categorie with id %s not found', $id));
    }
}