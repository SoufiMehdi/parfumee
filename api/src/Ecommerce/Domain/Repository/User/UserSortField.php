<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Repository\User;

/**
 * What a list of accounts can be sorted by. The value is the name the API uses.
 */
enum UserSortField: string
{
    case EMAIL = 'email';
    case FIRST_NAME = 'first_name';
    case LAST_NAME = 'last_name';
    case CREATED_AT = 'created_at';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $field): string => $field->value, self::cases());
    }
}
