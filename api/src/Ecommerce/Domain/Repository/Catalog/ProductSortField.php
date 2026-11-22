<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Repository\Catalog;

/**
 * What a list of products can be sorted by. The value is the name the API uses.
 */
enum ProductSortField: string
{
    case NAME = 'name';
    case PRICE = 'price';
    case CREATED_AT = 'created_at';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $field): string => $field->value, self::cases());
    }
}
