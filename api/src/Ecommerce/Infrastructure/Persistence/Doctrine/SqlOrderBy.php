<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Persistence\Doctrine;

use App\Ecommerce\Domain\Repository\SortDirection;

/**
 * Builds the ORDER BY of the paginated lists.
 */
final class SqlOrderBy
{
    /**
     * Sorts a text column as a French reader expects. The default collation of the
     * database compares bytes: "Zèbre" would come before "ambre", and "été" after "oeuf".
     * The ICU collations ship with the PostgreSQL image (dev, test, CI and production).
     */
    public static function frenchText(string $column): string
    {
        return $column.' COLLATE "fr-x-icu"';
    }

    /**
     * The id comes last, so that rows with the same value always come in the same
     * order: no row shows on two pages, or on none.
     *
     * @param string $column trusted SQL, never a value sent by the client
     */
    public static function clause(string $column, SortDirection $direction, string $idColumn): string
    {
        return \sprintf('%s %s, %s ASC', $column, SortDirection::DESC === $direction ? 'DESC' : 'ASC', $idColumn);
    }
}
