<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Exception\Catalog;

use App\Ecommerce\Domain\Exception\NotFoundExceptionInterface;

final class CategoryNotFoundException extends \DomainException implements NotFoundExceptionInterface
{
    public function __construct(string $id)
    {
        parent::__construct(\sprintf('Category with id "%s" not found.', $id));
    }
}
