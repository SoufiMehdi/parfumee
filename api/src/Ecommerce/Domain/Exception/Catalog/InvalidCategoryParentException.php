<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Exception\Catalog;

use App\Ecommerce\Domain\Exception\ValidationExceptionInterface;

/**
 * A category cannot go under this parent: categories have two levels only.
 */
final class InvalidCategoryParentException extends \DomainException implements ValidationExceptionInterface
{
    public static function itself(): self
    {
        return new self('A category cannot be its own parent.');
    }

    public static function notTopLevel(string $parentId): self
    {
        return new self(\sprintf('The category "%s" is itself a child category: only a top-level category can hold others.', $parentId));
    }

    public static function hasChildren(string $categoryId): self
    {
        return new self(\sprintf('The category "%s" holds child categories: it must stay at the top level.', $categoryId));
    }
}
