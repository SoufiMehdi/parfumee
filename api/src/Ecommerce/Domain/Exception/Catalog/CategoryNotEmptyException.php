<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Exception\Catalog;

use App\Ecommerce\Domain\Exception\ConflictExceptionInterface;

/**
 * A category is deleted only once nothing is filed under it: neither a child category nor
 * a product, archived ones included (the orders that sold them still read their category).
 */
final class CategoryNotEmptyException extends \DomainException implements ConflictExceptionInterface
{
    public static function withChildren(string $id): self
    {
        return new self(\sprintf('The category "%s" holds child categories: delete or move them first.', $id));
    }

    public static function meanwhile(string $id): self
    {
        return new self(\sprintf('Something was filed under the category "%s" in the meantime: it is kept.', $id));
    }

    public static function withProducts(string $id): self
    {
        return new self(\sprintf('The category "%s" holds products, archived ones included: move them to another category first.', $id));
    }
}
